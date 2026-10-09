<?php

namespace App\Http\Controllers;

use App\Models\EgresoRequisicion;
use App\Models\EgresoRequisicionBitacora;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrdenCompraController extends Controller
{
    /** Diferencia máxima aceptada entre lo cotizado y el total del CFDI (redondeo de centavos). */
    private const TOLERANCIA = 0.01;

    public function store(Request $request, EgresoRequisicion $requisicion)
    {
        if ($requisicion->suficiencia !== true) {
            abort(403, 'Solo se genera orden de compra para requisiciones con suficiencia presupuestal.');
        }
        if ($requisicion->ordenCompra()->exists()) {
            return back()->with('error', 'Esta requisición ya tiene orden de compra.');
        }

        $validated = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha' => 'required|date',
            'observaciones' => 'nullable|string|max:3000',
        ]);

        $proveedor = Proveedor::findOrFail($validated['proveedor_id']);
        if (! $proveedor->activo) {
            throw ValidationException::withMessages(['proveedor_id' => 'El proveedor está desactivado.']);
        }

        DB::transaction(function () use ($requisicion, $validated, $proveedor) {
            $año = (int) date('Y', strtotime($validated['fecha']));
            $folio = (OrdenCompra::where('año', $año)->lockForUpdate()->max('folio') ?? 0) + 1;

            $orden = OrdenCompra::create([
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'proveedor_id' => $proveedor->id,
                'folio' => $folio,
                'año' => $año,
                'folio_completo' => sprintf('OC-%d-%04d', $año, $folio),
                'fecha' => $validated['fecha'],
                'importe' => round((float) $requisicion->total, 2),
                'observaciones' => $validated['observaciones'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $this->bitacora($requisicion, "Orden de compra {$orden->folio_completo} generada para {$proveedor->nombre} por $".number_format($orden->importe, 2).'.');
        });

        return back()->with('success', 'Orden de compra generada.');
    }

    public function pdf(OrdenCompra $ordenCompra)
    {
        $ordenCompra->load('proveedor', 'requisicion.tbEgresoRequisicionDetalles', 'requisicion.catEgresoUnidadAdministrativa');

        return Pdf::loadView('ordenes-compra.pdf', ['orden' => $ordenCompra])
            ->stream("{$ordenCompra->folio_completo}.pdf");
    }

    /**
     * Carga la factura (CFDI) de la orden de compra. El total del XML debe coincidir con lo
     * cotizado y el emisor debe ser el proveedor de la orden.
     */
    public function cargarXml(Request $request, OrdenCompra $ordenCompra)
    {
        if ($ordenCompra->xml_uuid) {
            return back()->with('error', 'Esta orden de compra ya tiene su factura cargada.');
        }

        $request->validate(['xml' => 'required|file|max:2048']);
        $contenido = file_get_contents($request->file('xml')->getRealPath());
        $cfdi = $this->leerCfdi($contenido);

        $ordenCompra->load('proveedor');
        $errores = [];

        if (abs($cfdi['total'] - (float) $ordenCompra->importe) > self::TOLERANCIA) {
            $errores[] = sprintf('El total de la factura ($%s) no coincide con lo cotizado en la orden de compra ($%s).',
                number_format($cfdi['total'], 2), number_format($ordenCompra->importe, 2));
        }
        if ($ordenCompra->proveedor->rfc && $cfdi['rfc_emisor'] && mb_strtoupper($cfdi['rfc_emisor']) !== $ordenCompra->proveedor->rfc) {
            $errores[] = "La factura la emite {$cfdi['rfc_emisor']}, pero el proveedor de la orden es {$ordenCompra->proveedor->rfc}.";
        }
        if (! $cfdi['uuid']) {
            $errores[] = 'El XML no trae timbre fiscal (UUID); no es un CFDI timbrado.';
        } elseif (OrdenCompra::where('xml_uuid', $cfdi['uuid'])->exists()) {
            $errores[] = "La factura {$cfdi['uuid']} ya se cargó en otra orden de compra.";
        }

        if ($errores) {
            throw ValidationException::withMessages(['xml' => implode(' ', $errores)]);
        }

        $ruta = "ordenes-compra/{$ordenCompra->año}/{$ordenCompra->folio_completo}_{$cfdi['uuid']}.xml";
        Storage::disk('local')->put($ruta, $contenido);

        DB::transaction(function () use ($ordenCompra, $cfdi, $ruta) {
            $ordenCompra->update([
                'xml_path' => $ruta,
                'xml_uuid' => $cfdi['uuid'],
                'xml_rfc_emisor' => $cfdi['rfc_emisor'],
                'xml_nombre_emisor' => $cfdi['nombre_emisor'],
                'xml_serie' => $cfdi['serie'],
                'xml_folio' => $cfdi['folio'],
                'xml_fecha' => $cfdi['fecha'],
                'xml_subtotal' => $cfdi['subtotal'],
                'xml_total' => $cfdi['total'],
                'xml_cargado_at' => now(),
                'xml_user_id' => auth()->id(),
            ]);

            $this->bitacora($ordenCompra->requisicion, "Factura {$cfdi['uuid']} por $".number_format($cfdi['total'], 2)." cargada a la orden {$ordenCompra->folio_completo}.");
        });

        return back()->with('success', 'Factura cargada: el total coincide con lo cotizado.');
    }

    public function descargarXml(OrdenCompra $ordenCompra)
    {
        abort_unless($ordenCompra->xml_path && Storage::disk('local')->exists($ordenCompra->xml_path), 404);

        return Storage::disk('local')->download($ordenCompra->xml_path, "{$ordenCompra->folio_completo}.xml");
    }

    /** Extrae los datos relevantes de un CFDI 3.3 / 4.0 sin depender de la versión del namespace. */
    private function leerCfdi(string $contenido): array
    {
        $dom = new DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($contenido, LIBXML_NONET);
        libxml_use_internal_errors($previo);

        $xpath = $ok ? new DOMXPath($dom) : null;
        $comprobante = $xpath?->query('/*[local-name()="Comprobante"]')->item(0);
        if (! $comprobante) {
            throw ValidationException::withMessages(['xml' => 'El archivo no es un CFDI válido (no se encontró el nodo Comprobante).']);
        }

        $emisor = $xpath->query('/*[local-name()="Comprobante"]/*[local-name()="Emisor"]')->item(0);
        $timbre = $xpath->query('//*[local-name()="TimbreFiscalDigital"]')->item(0);
        $attr = fn ($nodo, $nombre) => $nodo && $nodo->hasAttribute($nombre) ? trim($nodo->getAttribute($nombre)) : null;

        $total = $attr($comprobante, 'Total');
        if ($total === null || ! is_numeric($total)) {
            throw ValidationException::withMessages(['xml' => 'El CFDI no trae el atributo Total.']);
        }

        return [
            'total' => round((float) $total, 2),
            'subtotal' => is_numeric($attr($comprobante, 'SubTotal')) ? round((float) $attr($comprobante, 'SubTotal'), 2) : null,
            'serie' => $attr($comprobante, 'Serie'),
            'folio' => $attr($comprobante, 'Folio'),
            'fecha' => $attr($comprobante, 'Fecha') ? date('Y-m-d H:i:s', strtotime($attr($comprobante, 'Fecha'))) : null,
            'rfc_emisor' => $attr($emisor, 'Rfc'),
            'nombre_emisor' => $attr($emisor, 'Nombre'),
            'uuid' => $attr($timbre, 'UUID') ? mb_strtoupper($attr($timbre, 'UUID')) : null,
        ];
    }

    private function bitacora(EgresoRequisicion $requisicion, string $observaciones): void
    {
        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $requisicion->id_cat_egreso_requisicion_estado,
            'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
            'id_tb_usuarios' => 1,
            'observaciones' => $observaciones,
        ]);
    }
}
