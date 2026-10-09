<?php

namespace App\Http\Controllers;

use App\Models\EgresoRequisicion;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\ModificacionPresupuestal;
use App\Services\PresupuestoService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Resumen del presupuesto de egresos de las unidades que el usuario puede ver, y lo que
     * tiene pendiente cada etapa (revisión, Recursos Materiales, modificaciones por autorizar).
     */
    public function index(PresupuestoService $presupuesto)
    {
        $user = auth()->user();

        if (! $user->can('requisiciones-index')) {
            return Inertia::render('dashboard', ['resumen' => null]);
        }

        $claves = PresupuestoService::clavesUnidadVisibles($user);
        $lineas = $presupuesto->lineas($claves);

        $totales = collect(['asignado', 'modificado', 'vigente', 'comprometido', 'en_tramite', 'disponible'])
            ->mapWithKeys(fn ($campo) => [$campo => round($lineas->sum($campo), 2)]);

        $requisiciones = EgresoRequisicion::query()
            ->when($claves !== null, fn ($q) => $q->whereIn(
                'id_cat_egreso_unidad_administrativa',
                EgresoUnidadAdministrativa::whereIn('clave', $claves ?: ['__ninguna__'])->pluck('id_cat_egreso_unidad_administrativa')
            ));

        return Inertia::render('dashboard', [
            'resumen' => [
                'totales' => $totales,
                'lineas' => $lineas->count(),
                'lineasSinDisponible' => $lineas->where('disponible', '<=', 0)->count(),
                'pendientes' => [
                    'pendientes' => (clone $requisiciones)->whereIn('id_cat_egreso_requisicion_estado', [1, 4])->count(),
                    'en_revision' => (clone $requisiciones)->where('id_cat_egreso_requisicion_estado', 2)->count(),
                    'recursos_materiales' => (clone $requisiciones)->whereIn('id_cat_egreso_requisicion_estado', [5, 6])->where(fn ($q) => $q->whereNull('suficiencia')->orWhere('suficiencia', false))->count(),
                    'sin_orden_compra' => (clone $requisiciones)->where('suficiencia', true)->whereDoesntHave('ordenCompra')->count(),
                    'modificaciones' => ModificacionPresupuestal::where('estado', 'pendiente')
                        ->when($claves !== null, fn ($q) => $q->where(fn ($q2) => $q2->whereIn('origen_clave', $claves ?: ['__ninguna__'])->orWhereIn('destino_clave', $claves ?: ['__ninguna__'])))
                        ->count(),
                ],
            ],
        ]);
    }
}
