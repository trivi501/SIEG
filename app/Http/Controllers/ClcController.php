<?php

namespace App\Http\Controllers;

use App\Models\ClcRelacion;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\OrdenCompra;
use App\Services\TramiteClc;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Trámite de CLC (/clc): bandejas por área para recibir, enviar (relación), devolver y pagar. Las
 * reglas de cada paso están en App\Services\TramiteClc.
 */
class ClcController extends Controller
{
    /**
     * Control Presupuestal y Admin ven todas las CLC y todas las bandejas. Cada área (Recursos
     * Materiales, Sindicatura, Egresos) ve las CLC que están en su bandeja; el área solicitante, el
     * seguimiento de las de su secretaría.
     */
    public function index()
    {
        $user = auth()->user();
        $veTodo = TramiteClc::veTodo($user);
        $propias = EgresoUnidadAdministrativa::idsMismaClave($user->id_cat_egreso_unidad_administrativa);
        $estadosBandeja = TramiteClc::estadosDeSusBandejas($user);
        // Quien participa en el trámite (RM, CP, Sindicatura, Egresos) sigue todas las CLC, también las
        // pagadas; el área solicitante, solo las de su secretaría.
        $todas = $veTodo || $user->canAny(['clc-recursos-materiales', 'clc-control-presupuestal', 'clc-sindicatura', 'clc-egresos']);

        $clcs = OrdenCompra::query()->whereNotNull('clc_folio_completo')
            ->when(! $todas, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('clc_estado', $estadosBandeja ?: ['__ninguno__'])
                ->orWhereHas('requisicion', fn ($r) => $r->whereIn('id_cat_egreso_unidad_administrativa', $propias ?: [0]))))
            ->with(['proveedor:id,nombre', 'requisicion:id_tb_egreso_requisicion,folio_completo,id_cat_egreso_unidad_administrativa,observaciones',
                'requisicion.catEgresoUnidadAdministrativa:id_cat_egreso_unidad_administrativa,clave,nombre', 'relacionClc:id,folio_completo,origen,destino',
                'movimientosClc' => fn ($q) => $q->where('accion', 'devolver')->reorder()->latest('id')])
            ->orderByDesc('clc_generada_at')
            ->get()
            ->map(fn (OrdenCompra $o) => [
                'id' => $o->id,
                'clc' => $o->clc_folio_completo,
                'clc_fecha' => $o->clc_fecha?->toDateString(),
                'estado' => $o->clc_estado,
                'orden' => $o->folio_completo,
                'importe' => $o->importe,
                'proveedor' => $o->proveedor?->nombre,
                'requisicion_id' => $o->id_tb_egreso_requisicion,
                'requisicion' => $o->requisicion?->folio_completo,
                'concepto' => $o->requisicion?->observaciones,
                'unidad' => $o->requisicion?->catEgresoUnidadAdministrativa
                    ? trim($o->requisicion->catEgresoUnidadAdministrativa->clave.' '.$o->requisicion->catEgresoUnidadAdministrativa->nombre)
                    : null,
                'relacion' => $o->relacionClc?->folio_completo,
                'pago_fecha' => $o->pago_fecha?->toDateString(),
                'transferencia' => $o->transferencia_numero,
                'relacion_id' => $o->clc_relacion_id,
                'devolucion' => $o->clc_estado === 'devuelta' ? $o->movimientosClc->first()?->observaciones : null,
                // Entra en "Seguimiento": todas para Control Presupuestal / Admin; las de su secretaría para los demás.
                'seguimiento' => $todas || in_array((int) $o->requisicion?->id_cat_egreso_unidad_administrativa, $propias, true),
            ]);
        $verRelaciones = $veTodo || $user->can('clc-sindicatura');

        return Inertia::render('clc/Index', [
            'clcs' => $clcs,
            'veTodo' => $veTodo,
            'seguimientoTodas' => $todas,
            'relaciones' => ! $verRelaciones ? [] : ClcRelacion::withCount('ordenes')->with('usuario:id,name,titulo')->latest('id')->limit(100)->get()
                ->map(fn (ClcRelacion $r) => [
                    'id' => $r->id,
                    'folio' => $r->folio_completo,
                    'origen' => TramiteClc::AREAS[$r->origen] ?? $r->origen,
                    'destino' => TramiteClc::AREAS[$r->destino] ?? $r->destino,
                    'clcs' => $r->ordenes_count,
                    'usuario' => $r->usuario?->name,
                    'fecha' => $r->created_at?->toIso8601String(),
                ]),
            'estados' => collect(TramiteClc::ESTADOS)->map(fn ($e) => $e[0]),
            'acciones' => collect(TramiteClc::ACCIONES)->map(fn ($a) => [
                'etiqueta' => $a['etiqueta'],
                'permiso' => $a['permiso'],
                'envio' => isset($a['envio']) ? TramiteClc::AREAS[$a['envio'][1]] : null,
                'relacion' => $a['relacion'] ?? false,
                'recibe' => $a['recibe'] ?? false,
                'observaciones' => $a['observaciones'] ?? false,
                'pago' => $a['pago'] ?? false,
                'a' => TramiteClc::descripcion($a['a']),
            ]),
            'formasPago' => OrdenCompra::FORMAS_PAGO,
        ]);
    }

    public function accion(Request $request, TramiteClc $tramite)
    {
        $validated = $request->validate([
            'accion' => 'required|string|in:'.implode(',', array_keys(TramiteClc::ACCIONES)),
            'ordenes' => 'required|array|min:1',
            'ordenes.*' => 'integer',
            'observaciones' => 'nullable|string|max:2000',
            'pago_fecha' => 'nullable|date',
            'pago_forma' => 'nullable|string',
            'pago_referencia' => 'nullable|string|max:100',
        ], ['ordenes.required' => 'Selecciona al menos una CLC.']);

        $relacion = $tramite->ejecutar($validated['accion'], $validated['ordenes'], $request->user(), $validated);
        $cuantas = count(array_unique($validated['ordenes']));
        $mensaje = TramiteClc::ACCIONES[$validated['accion']]['etiqueta'].": {$cuantas} CLC.";

        return back()->with('success', $relacion ? "{$mensaje} Relación {$relacion->folio_completo} generada." : $mensaje)
            ->with('relacion', $relacion?->id);
    }

    /** Documento de la relación: las CLC que se envían de un área a otra, con firmas de quien entrega y quien recibe. */
    public function relacionPdf(ClcRelacion $relacion)
    {
        abort_unless(TramiteClc::veTodo(auth()->user()) || auth()->user()->can('clc-sindicatura'), 403);

        $relacion->load(['usuario:id,name,titulo', 'ordenes.proveedor:id,nombre', 'ordenes.requisicion:id_tb_egreso_requisicion,folio_completo,id_cat_egreso_unidad_administrativa',
            'ordenes.requisicion.catEgresoUnidadAdministrativa:id_cat_egreso_unidad_administrativa,clave,nombre']);

        $pdf = Pdf::loadView('clc.relacion', ['relacion' => $relacion, 'areas' => TramiteClc::AREAS])->setPaper('letter');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf->stream("{$relacion->folio_completo}.pdf");
    }
}
