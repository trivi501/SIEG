<?php

namespace App\Http\Controllers;

use App\Models\EgresoRequisicion;
use App\Models\EgresoRequisicionBitacora;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Services\EstadoCfdiSat;
use App\Services\TramiteClc;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\UniqueConstraintViolationException;
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
        if ($requisicion->suficiencia !== true || $requisicion->id_cat_egreso_requisicion_estado !== EgresoRequisicion::COMPROMETIDA) {
            abort(403, 'Solo se genera orden de compra para requisiciones comprometidas (con suficiencia presupuestal).');
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
        if ($impedimento = $proveedor->impedimentoParaOrden()) {
            throw ValidationException::withMessages(['proveedor_id' => $impedimento]);
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

            $this->bitacora($requisicion, EgresoRequisicion::CON_ORDEN_COMPRA, "Orden de compra {$orden->folio_completo} generada para {$proveedor->nombre} por $".number_format($orden->importe, 2).'.');
        });

        return back()->with('success', 'Orden de compra generada.');
    }

    /**
     * Reasigna el proveedor de la orden de compra mientras no tenga factura cargada (la factura la emite el
     * proveedor de la orden). Queda en la bitácora de la requisición.
     */
    public function cambiarProveedor(Request $request, OrdenCompra $ordenCompra)
    {
        if ($ordenCompra->cancelada_at) {
            return back()->with('error', 'La orden de compra está cancelada.');
        }
        if ($ordenCompra->xml_uuid) {
            return back()->with('error', 'El proveedor ya no se cambia: la orden de compra tiene su factura cargada.');
        }

        $validated = $request->validate(['proveedor_id' => ['required', 'integer', 'exists:proveedores,id']]);
        $nuevo = Proveedor::findOrFail($validated['proveedor_id']);
        if ($nuevo->id === $ordenCompra->proveedor_id) {
            return back();
        }
        if ($impedimento = $nuevo->impedimentoParaOrden()) {
            throw ValidationException::withMessages(['proveedor_id' => $impedimento]);
        }

        $anterior = $ordenCompra->proveedor;
        DB::transaction(function () use ($ordenCompra, $nuevo, $anterior) {
            $ordenCompra->update(['proveedor_id' => $nuevo->id]);
            $requisicion = $ordenCompra->requisicion;
            $this->bitacora($requisicion, $requisicion->id_cat_egreso_requisicion_estado,
                "Proveedor de la orden {$ordenCompra->folio_completo} cambiado de ".($anterior?->nombre ?? '—')." a {$nuevo->nombre} por ".auth()->user()->name.'.');
        });

        return back()->with('success', "Proveedor de la orden {$ordenCompra->folio_completo} cambiado a {$nuevo->nombre}.");
    }

    /**
     * Recursos Materiales y Control Presupuestal corrigen la fecha de la orden de compra: debe ser del
     * mismo año del folio, no futura y no posterior al pago registrado.
     */
    public function cambiarFecha(Request $request, OrdenCompra $ordenCompra)
    {
        if ($ordenCompra->cancelada_at) {
            return back()->with('error', 'La orden de compra está cancelada.');
        }

        $validated = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', function ($attr, $valor, $fallo) use ($ordenCompra) {
                if ((int) date('Y', strtotime($valor)) !== (int) $ordenCompra->año) {
                    $fallo("La fecha debe ser del {$ordenCompra->año}, como el folio {$ordenCompra->folio_completo}.");
                }
                if ($ordenCompra->pago_fecha && $valor > $ordenCompra->pago_fecha->toDateString()) {
                    $fallo('La fecha no puede ser posterior al pago registrado ('.$ordenCompra->pago_fecha->toDateString().').');
                }
            }],
        ], ['fecha.before_or_equal' => 'La fecha no puede ser futura.']);

        $anterior = $ordenCompra->fecha?->toDateString();
        if ($anterior === $validated['fecha']) {
            return back();
        }

        DB::transaction(function () use ($ordenCompra, $validated, $anterior) {
            $ordenCompra->update(['fecha' => $validated['fecha']]);
            $requisicion = $ordenCompra->requisicion;
            $this->bitacora($requisicion, $requisicion->id_cat_egreso_requisicion_estado,
                "Fecha de la orden {$ordenCompra->folio_completo} cambiada del {$anterior} al {$validated['fecha']} por ".auth()->user()->name.'.');
        });

        return back()->with('success', 'Fecha de la orden de compra actualizada.');
    }

    public function pdf(OrdenCompra $ordenCompra)
    {
        abort_if($ordenCompra->cancelada_at, 403, "La orden de compra {$ordenCompra->folio_completo} está cancelada: ya no se imprime.");

        $ordenCompra->load('proveedor', 'requisicion.tbEgresoRequisicionDetalles', 'requisicion.catEgresoUnidadAdministrativa');

        $pdf = Pdf::loadView('ordenes-compra.pdf', ['orden' => $ordenCompra])->setPaper('letter');

        // Pie: "Página N de M" y el folio (dompdf no conoce el total de páginas desde el CSS).
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 200, $canvas->get_height() - 28, "Página {PAGE_NUM} de {PAGE_COUNT}      {$ordenCompra->folio_completo}",
            $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf->stream("{$ordenCompra->folio_completo}.pdf");
    }

    /**
     * Carga la factura (CFDI) de la orden de compra. El total del XML debe coincidir con lo
     * cotizado y el emisor debe ser el proveedor de la orden.
     */
    public function cargarXml(Request $request, OrdenCompra $ordenCompra, EstadoCfdiSat $estadoSat)
    {
        if ($ordenCompra->cancelada_at) {
            return back()->with('error', 'La orden de compra está cancelada.');
        }
        if ($ordenCompra->xml_uuid) {
            return back()->with('error', 'Esta orden de compra ya tiene su factura cargada.');
        }

        $request->validate(['xml' => 'required|file|max:2048']);
        $contenido = file_get_contents($request->file('xml')->getRealPath());
        $cfdi = $this->leerCfdi($contenido);
        $xml = EstadoCfdiSat::datosDelXml($contenido);
        $cfdi['isr'] = $xml['isr'];

        $ordenCompra->load('proveedor');
        $errores = [];

        // El ISR retenido no cambia el importe del gasto: el Total del XML ya viene sin el ISR, así que lo
        // cotizado se compara con Total + ISR (al proveedor se le paga el Total).
        $importeFactura = round($cfdi['total'] + $cfdi['isr'], 2);
        if (abs($importeFactura - (float) $ordenCompra->importe) > self::TOLERANCIA) {
            $errores[] = $cfdi['isr'] > 0
                ? sprintf('El importe de la factura ($%s = total $%s + ISR retenido $%s) no coincide con lo cotizado en la orden de compra ($%s).',
                    number_format($importeFactura, 2), number_format($cfdi['total'], 2), number_format($cfdi['isr'], 2), number_format($ordenCompra->importe, 2))
                : sprintf('El total de la factura ($%s) no coincide con lo cotizado en la orden de compra ($%s).',
                    number_format($cfdi['total'], 2), number_format($ordenCompra->importe, 2));
        }
        if ($ordenCompra->proveedor->rfc && $cfdi['rfc_emisor'] && mb_strtoupper($cfdi['rfc_emisor']) !== $ordenCompra->proveedor->rfc) {
            $errores[] = "La factura la emite {$cfdi['rfc_emisor']}, pero el proveedor de la orden es {$ordenCompra->proveedor->rfc}.";
        }
        if (! $cfdi['uuid']) {
            $errores[] = 'El XML no trae timbre fiscal (UUID); no es un CFDI timbrado.';
        } else {
            $errores = [...$errores, ...$this->duplicidades($ordenCompra, $cfdi)];
        }

        if ($errores) {
            throw ValidationException::withMessages(['xml' => implode(' ', $errores)]);
        }

        // Estado en el SAT: solo se acepta (y se guarda) una factura vigente. Cancelada, no encontrada o sin
        // respuesta del SAT no se guarda.
        $sat = $estadoSat->consultar($cfdi['rfc_emisor'] ?? '', $xml['rfc_receptor'] ?? '', $cfdi['total'], $cfdi['uuid'], $xml['sello']);
        if (! $sat) {
            throw ValidationException::withMessages(['xml' => 'No se pudo verificar la factura en el SAT (el servicio no respondió), así que no se guardó. Intenta de nuevo en unos minutos.']);
        }
        if ($error = EstadoCfdiSat::error($sat, $cfdi['uuid'])) {
            throw ValidationException::withMessages(['xml' => $error]);
        }

        $ruta = "ordenes-compra/{$ordenCompra->año}/{$ordenCompra->folio_completo}_{$cfdi['uuid']}.xml";
        Storage::disk('local')->put($ruta, $contenido);

        try {
            $this->guardarFactura($ordenCompra, $cfdi, $ruta);
        } catch (UniqueConstraintViolationException) {
            // Otra persona cargó el mismo UUID al mismo tiempo: el índice único lo detiene.
            Storage::disk('local')->delete($ruta);
            throw ValidationException::withMessages(['xml' => implode(' ', $this->duplicidades($ordenCompra, $cfdi))
                ?: "La factura {$cfdi['uuid']} ya se cargó en otra orden de compra."]);
        }
        EstadoCfdiSat::guardar($ordenCompra, $sat);

        return back()->with('success', 'Factura aceptada (vigente en el SAT): el gasto pasa de comprometido a devengado.')
            ->with('warning', $sat['efos'] ? self::avisoEfos($cfdi['rfc_emisor']) : null);
    }

    /**
     * Vuelve a consultar en el SAT la factura de la orden (el proveedor pudo cancelarla después de entregarla).
     */
    public function verificarSat(OrdenCompra $ordenCompra, EstadoCfdiSat $estadoSat)
    {
        if (! $ordenCompra->xml_uuid) {
            return back()->with('error', 'La orden de compra no tiene factura cargada.');
        }

        $sat = $estadoSat->consultarOrden($ordenCompra);
        if (! $sat) {
            return back()->with('error', 'El SAT no respondió; intenta más tarde.');
        }

        $anterior = $ordenCompra->sat_estado;
        EstadoCfdiSat::guardar($ordenCompra, $sat);

        if ($anterior !== $sat['estado']) {
            $requisicion = $ordenCompra->requisicion;
            $this->bitacora($requisicion, $requisicion->id_cat_egreso_requisicion_estado,
                "Factura {$ordenCompra->xml_uuid} verificada en el SAT por ".auth()->user()->name.": {$sat['estado']}"
                .($sat['estatus_cancelacion'] ? " ({$sat['estatus_cancelacion']})" : '').'.');
        }

        $redirect = back()->with('warning', $sat['efos'] ? self::avisoEfos($ordenCompra->xml_rfc_emisor) : null);

        return $sat['estado'] === EstadoCfdiSat::VIGENTE
            ? $redirect->with('success', 'Factura vigente en el SAT.'.($sat['estatus_cancelacion'] ? " Estatus de cancelación: {$sat['estatus_cancelacion']}." : ''))
            : $redirect->with('error', EstadoCfdiSat::error($sat, $ordenCompra->xml_uuid));
    }

    private static function avisoEfos(?string $rfc): string
    {
        return "Atención: el emisor {$rfc} aparece en la lista de EFOS del SAT (empresas que facturan operaciones simuladas). Revisa antes de pagar.";
    }

    /**
     * Evita cargar dos veces la misma factura: por folio fiscal (UUID) y por serie + folio del mismo
     * emisor (la misma factura cancelada y vuelta a timbrar trae otro UUID).
     *
     * @return array<int, string>
     */
    private function duplicidades(OrdenCompra $ordenCompra, array $cfdi): array
    {
        $donde = fn (OrdenCompra $o) => "la orden {$o->folio_completo}".($o->requisicion ? " (requisición {$o->requisicion->folio_completo})" : '');
        $errores = [];

        $mismoUuid = OrdenCompra::with('requisicion:id_tb_egreso_requisicion,folio_completo')
            ->where('xml_uuid', $cfdi['uuid'])->first();
        if ($mismoUuid) {
            $errores[] = "La factura {$cfdi['uuid']} ya está cargada en {$donde($mismoUuid)}.";
        }

        if ($cfdi['folio'] && $cfdi['rfc_emisor']) {
            $mismoFolio = OrdenCompra::with('requisicion:id_tb_egreso_requisicion,folio_completo')
                ->whereKeyNot($ordenCompra->getKey())
                ->when($mismoUuid, fn ($q) => $q->whereKeyNot($mismoUuid->getKey()))
                ->whereRaw('UPPER(xml_rfc_emisor) = ?', [mb_strtoupper($cfdi['rfc_emisor'])])
                ->where('xml_folio', $cfdi['folio'])
                ->where(fn ($q) => $cfdi['serie'] ? $q->where('xml_serie', $cfdi['serie']) : $q->whereNull('xml_serie')->orWhere('xml_serie', ''))
                ->first();
            if ($mismoFolio) {
                $factura = trim(($cfdi['serie'] ? "serie {$cfdi['serie']} " : '')."folio {$cfdi['folio']}");
                $errores[] = "La factura {$factura} de {$cfdi['rfc_emisor']} ya está cargada en {$donde($mismoFolio)} (con el folio fiscal {$mismoFolio->xml_uuid}). Si el proveedor la volvió a timbrar, se debe cancelar la anterior.";
            }
        }

        return $errores;
    }

    private function guardarFactura(OrdenCompra $ordenCompra, array $cfdi, string $ruta): void
    {
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
                'xml_isr' => $cfdi['isr'],
                'xml_cargado_at' => now(),
                'xml_user_id' => auth()->id(),
            ]);

            $this->bitacora($ordenCompra->requisicion, EgresoRequisicion::CON_FACTURA, "Factura {$cfdi['uuid']} por $".number_format($cfdi['total'], 2)." cargada a la orden {$ordenCompra->folio_completo}.");
        });
    }

    /**
     * Registra el pago de una orden (momento contable "pagado"). Es el último paso del trámite de la
     * CLC: solo cuando Egresos ya la recibió (TramiteClc, acción "pagar").
     */
    public function registrarPago(Request $request, OrdenCompra $ordenCompra, TramiteClc $tramite)
    {
        if ($ordenCompra->pago_fecha) {
            return back()->with('error', 'Esta orden de compra ya tiene su pago registrado.');
        }
        if ($ordenCompra->clc_estado !== 'recibida_egresos') {
            return back()->with('error', 'El pago se registra cuando Egresos ya recibió la CLC (Trámite de CLC). Hoy está: '.TramiteClc::descripcion($ordenCompra->clc_estado).'.');
        }

        $tramite->ejecutar('pagar', [$ordenCompra->id], $request->user(), $request->only('pago_fecha', 'pago_forma', 'pago_referencia'));

        return back()->with('success', 'Pago registrado.');
    }

    /**
     * El área genera la CLC (cuenta por liquidar certificada) de una orden de compra con factura:
     * es la solicitud de pago al proveedor. Folio consecutivo por año: CLC-2026-0001.
     */
    public function generarClc(OrdenCompra $ordenCompra)
    {
        $requisicion = $ordenCompra->requisicion;
        $this->autorizarArea($requisicion);

        if (! $ordenCompra->xml_uuid) {
            return back()->with('error', 'La CLC se genera cuando la orden de compra ya tiene su factura (XML).');
        }
        if ($ordenCompra->cancelada_at) {
            return back()->with('error', 'La orden de compra está cancelada.');
        }
        if ($ordenCompra->clc_folio_completo) {
            // También si se canceló: lo que sigue es cancelar la orden de compra y la requisición.
            return back()->with('error', "Esta orden de compra ya tiene la CLC {$ordenCompra->clc_folio_completo}"
                .($ordenCompra->clc_cancelada_at ? ' (cancelada); sigue cancelar la orden de compra.' : '.'));
        }

        DB::transaction(function () use ($ordenCompra, $requisicion) {
            $año = (int) now('America/Mexico_City')->format('Y');
            $folio = (OrdenCompra::where('clc_año', $año)->lockForUpdate()->max('clc_folio') ?? 0) + 1;

            $ordenCompra->update([
                'clc_folio' => $folio,
                'clc_año' => $año,
                'clc_folio_completo' => sprintf('CLC-%d-%04d', $año, $folio),
                'clc_fecha' => now('America/Mexico_City')->toDateString(),
                'clc_user_id' => auth()->id(),
                'clc_generada_at' => now(),
            ]);

            $this->bitacora($requisicion, EgresoRequisicion::CLC_GENERADA, "CLC {$ordenCompra->clc_folio_completo} generada por $".number_format($ordenCompra->importe, 2)." a favor de {$ordenCompra->proveedor?->nombre}.");
            app(TramiteClc::class)->generada($ordenCompra, auth()->user());
        });

        return back()->with('success', "CLC {$ordenCompra->clc_folio_completo} generada.");
    }

    /**
     * Cancelación escalonada, de lo último a lo primero: CLC → orden de compra → requisición. Cancelar la CLC
     * (en cualquier paso, aun pagada) la saca del trámite; después sigue cancelar la orden de compra.
     */
    public function cancelarClc(Request $request, OrdenCompra $ordenCompra, TramiteClc $tramite)
    {
        if (! $ordenCompra->clcActiva()) {
            return back()->with('error', 'Esta orden de compra no tiene una CLC vigente.');
        }
        $motivo = $this->motivo($request);
        $user = $request->user();
        $pagada = $ordenCompra->pago_fecha !== null;

        DB::transaction(function () use ($ordenCompra, $tramite, $user, $motivo, $pagada) {
            $tramite->cancelar($ordenCompra, $user, $motivo);

            $this->bitacora($ordenCompra->requisicion, EgresoRequisicion::CON_FACTURA,
                "CLC {$ordenCompra->clc_folio_completo} cancelada por {$user->name}"
                .($pagada ? ' (el pago'.($ordenCompra->transferencia_numero ? " {$ordenCompra->transferencia_numero}" : '').' queda anulado)' : '')
                .". Motivo: {$motivo}. Sigue cancelar la orden de compra {$ordenCompra->folio_completo}.");
        });

        return back()->with('success', "CLC {$ordenCompra->clc_folio_completo} cancelada. Sigue cancelar la orden de compra.");
    }

    /** Cancela la orden de compra (con su factura); antes debe estar cancelada su CLC. Sigue la requisición. */
    public function cancelar(Request $request, OrdenCompra $ordenCompra)
    {
        if ($ordenCompra->cancelada_at) {
            return back()->with('error', 'Esta orden de compra ya está cancelada.');
        }
        if ($ordenCompra->clcActiva()) {
            return back()->with('error', "Primero cancela la CLC {$ordenCompra->clc_folio_completo}: se cancela de lo último a lo primero.");
        }
        $motivo = $this->motivo($request);
        $user = $request->user();

        DB::transaction(function () use ($ordenCompra, $user, $motivo) {
            $ordenCompra->update(['cancelada_at' => now(), 'cancelada_user_id' => $user->id, 'motivo_cancelacion' => $motivo]);

            $requisicion = $ordenCompra->requisicion;
            $this->bitacora($requisicion, EgresoRequisicion::COMPROMETIDA,
                "Orden de compra {$ordenCompra->folio_completo} cancelada por {$user->name}"
                .($ordenCompra->xml_uuid ? " (con su factura {$ordenCompra->xml_uuid})" : '')
                .". Motivo: {$motivo}. Sigue cancelar la requisición {$requisicion->folio_completo}.");
        });

        return back()->with('success', "Orden de compra {$ordenCompra->folio_completo} cancelada. Sigue cancelar la requisición.");
    }

    private function motivo(Request $request): string
    {
        return $request->validate(
            ['motivo' => 'required|string|min:5|max:1000'],
            ['motivo.required' => 'Indica el motivo de la cancelación.', 'motivo.min' => 'El motivo debe tener al menos 5 caracteres.'],
        )['motivo'];
    }

    public function clcPdf(OrdenCompra $ordenCompra)
    {
        abort_unless($ordenCompra->clc_folio_completo, 404);
        $this->autorizarArea($ordenCompra->requisicion);

        $ordenCompra->load('proveedor', 'requisicion.tbEgresoRequisicionDetalles', 'requisicion.catEgresoUnidadAdministrativa');

        $pdf = Pdf::loadView('ordenes-compra.clc', ['orden' => $ordenCompra])->setPaper('letter', 'landscape');

        // "Página N de M" en el pie (dompdf no conoce el total de páginas desde el CSS).
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf->stream("{$ordenCompra->clc_folio_completo}.pdf");
    }

    /** La CLC es del área que hizo la requisición (Admin y Recursos Materiales ven todas). */
    private function autorizarArea(EgresoRequisicion $requisicion): void
    {
        $user = auth()->user();
        if ($user->hasRole(['Super Admin', 'Admin']) || $user->can('ordenes-compra-create')) {
            return;
        }

        $unidad = $user->id_cat_egreso_unidad_administrativa;
        abort_unless($unidad && (int) $unidad === (int) $requisicion->id_cat_egreso_unidad_administrativa, 403, 'Esta requisición no pertenece a tu área.');
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

    /** Pasa la requisición al estado $nuevo y lo deja en su bitácora. */
    private function bitacora(EgresoRequisicion $requisicion, int $nuevo, string $observaciones): void
    {
        $anterior = $requisicion->id_cat_egreso_requisicion_estado;
        if ($anterior !== $nuevo) {
            $requisicion->update(['id_cat_egreso_requisicion_estado' => $nuevo]);
        }

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $anterior,
            'id_cat_egreso_requisicion_estado_nuevo' => $nuevo,
            'id_tb_usuarios' => 1,
            'observaciones' => $observaciones,
        ]);
    }
}
