<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use App\Models\VehiculoMantenimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Mantenimiento vehicular: se recibe el vehículo en el taller (queda "En taller"), desde ahí se hace la
 * requisición a nombre de su secretaría de resguardo y al terminar se entrega (vuelve a "En servicio").
 */
class VehiculoMantenimientoController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->only('buscar', 'estado');

        $mantenimientos = VehiculoMantenimiento::query()
            ->with(['vehiculo', 'unidadAdministrativa:id_cat_egreso_unidad_administrativa,clave,nombre', 'departamento:id,nombre',
                'requisicion:id_tb_egreso_requisicion,folio_completo,id_cat_egreso_requisicion_estado', 'requisicion.catEgresoRequisicionEstado', 'usuario:id,name'])
            ->when(filled($filtros['estado'] ?? null), fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(filled($filtros['buscar'] ?? null), function ($q) use ($filtros) {
                foreach (preg_split('/\s+/', trim($filtros['buscar'])) as $t) {
                    $like = "%{$t}%";
                    $q->where(fn ($w) => $w->where('folio_completo', 'like', $like)->orWhere('descripcion', 'like', $like)
                        ->orWhereHas('vehiculo', fn ($v) => $v->where('numero_economico', 'like', $like)->orWhere('placas', 'like', $like)
                            ->orWhere('marca', 'like', $like)->orWhere('linea', 'like', $like))
                        ->orWhereHas('unidadAdministrativa', fn ($u) => $u->where('nombre', 'like', $like)->orWhere('clave', 'like', $like)));
                }
            })
            ->orderByDesc('id')->paginate(25)->withQueryString()
            ->through(fn (VehiculoMantenimiento $m) => [
                'id' => $m->id,
                'folio' => $m->folio_completo,
                'vehiculo' => $m->textoVehiculo(),
                'secretaria' => $m->unidadAdministrativa ? trim($m->unidadAdministrativa->clave.' '.$m->unidadAdministrativa->nombre) : null,
                'area' => $m->departamento?->nombre,
                'fecha_recepcion' => $m->fecha_recepcion?->toDateString(),
                'kilometraje' => $m->kilometraje,
                'tipo' => VehiculoMantenimiento::TIPOS[$m->tipo] ?? $m->tipo,
                'descripcion' => $m->descripcion,
                'entrega' => $m->entrega,
                'recibio' => $m->usuario?->name,
                'estado' => $m->estado,
                'fecha_entrega' => $m->fecha_entrega?->toDateString(),
                'recibe_entrega' => $m->recibe_entrega,
                'requisicion_id' => $m->id_tb_egreso_requisicion,
                'requisicion' => $m->requisicion?->folio_completo,
                'requisicion_estado' => $m->requisicion?->catEgresoRequisicionEstado?->descripcion,
            ]);

        // Vehículos que se pueden recibir: activos, con secretaría de resguardo y que no estén ya en el taller.
        $vehiculos = Vehiculo::where('activo', true)->whereNotNull('id_cat_egreso_unidad_administrativa')
            ->whereDoesntHave('mantenimientos', fn ($q) => $q->where('estado', 'en_taller'))
            ->with(['unidadAdministrativa:id_cat_egreso_unidad_administrativa,clave,nombre', 'departamento:id,nombre'])
            ->orderBy('numero_economico')->get()
            ->map(fn (Vehiculo $v) => [
                'id' => $v->id,
                'texto' => trim("ECO {$v->numero_economico}".($v->placas ? " · {$v->placas}" : '').' · '.trim("{$v->marca} {$v->linea} {$v->modelo}")),
                'secretaria' => $v->unidadAdministrativa ? trim($v->unidadAdministrativa->clave.' '.$v->unidadAdministrativa->nombre) : null,
                'area' => $v->departamento?->nombre,
            ]);

        return Inertia::render('vehiculos/Mantenimientos', [
            'mantenimientos' => $mantenimientos,
            'vehiculos' => $vehiculos,
            'filtros' => $filtros,
            'tipos' => VehiculoMantenimiento::TIPOS,
            'puedeRequisicion' => $request->user()->can('requisiciones-create'),
        ]);
    }

    /** Recibe el vehículo en el taller (folio MV-AÑO-0001); el vehículo queda "En taller". */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'vehiculo_id' => 'required|integer|exists:vehiculos,id',
            'fecha_recepcion' => 'required|date|before_or_equal:today',
            'kilometraje' => 'nullable|integer|min:0|max:9999999',
            'tipo' => 'required|in:'.implode(',', array_keys(VehiculoMantenimiento::TIPOS)),
            'descripcion' => 'required|string|min:5|max:2000',
            'entrega' => 'nullable|string|max:200',
        ], ['fecha_recepcion.before_or_equal' => 'La fecha no puede ser futura.', 'descripcion.required' => 'Describe la falla o el servicio solicitado.']);

        $vehiculo = Vehiculo::findOrFail($datos['vehiculo_id']);
        if (! $vehiculo->id_cat_egreso_unidad_administrativa) {
            throw ValidationException::withMessages(['vehiculo_id' => 'El vehículo no tiene secretaría de resguardo en el padrón.']);
        }
        if ($vehiculo->mantenimientos()->where('estado', 'en_taller')->exists()) {
            throw ValidationException::withMessages(['vehiculo_id' => 'Ese vehículo ya está en el taller.']);
        }

        $mantenimiento = DB::transaction(function () use ($datos, $vehiculo, $request) {
            $año = (int) substr($datos['fecha_recepcion'], 0, 4);
            $folio = (VehiculoMantenimiento::where('año', $año)->lockForUpdate()->max('folio') ?? 0) + 1;
            $m = VehiculoMantenimiento::create([
                ...$datos,
                'folio' => $folio, 'año' => $año, 'folio_completo' => sprintf('MV-%d-%04d', $año, $folio),
                'id_cat_egreso_unidad_administrativa' => $vehiculo->id_cat_egreso_unidad_administrativa,
                'departamento_id' => $vehiculo->departamento_id,
                'user_id' => $request->user()->id, 'estado' => 'en_taller',
            ]);
            $vehiculo->update(['estado' => 'taller']);

            return $m;
        });

        return back()->with('success', "Vehículo recibido en el taller ({$mantenimiento->folio_completo}).");
    }

    /** Termina el mantenimiento: se entrega el vehículo y vuelve a "En servicio". */
    public function entregar(Request $request, VehiculoMantenimiento $mantenimiento)
    {
        if ($mantenimiento->estado !== 'en_taller') {
            return back()->with('error', 'Ese vehículo ya se entregó.');
        }
        $datos = $request->validate([
            'fecha_entrega' => 'required|date|before_or_equal:today|after_or_equal:'.$mantenimiento->fecha_recepcion->toDateString(),
            'recibe_entrega' => 'required|string|max:200',
            'observaciones_entrega' => 'nullable|string|max:2000',
        ], ['fecha_entrega.after_or_equal' => 'La entrega no puede ser antes de la recepción.', 'recibe_entrega.required' => 'Indica quién recibe el vehículo.']);

        DB::transaction(function () use ($mantenimiento, $datos) {
            $mantenimiento->update([...$datos, 'estado' => 'entregado']);
            $mantenimiento->vehiculo->update(['estado' => 'en_servicio']);
        });

        return back()->with('success', "Vehículo del {$mantenimiento->folio_completo} entregado.");
    }
}
