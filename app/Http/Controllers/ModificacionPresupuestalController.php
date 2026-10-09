<?php

namespace App\Http\Controllers;

use App\Models\EgresoRequisicion;
use App\Models\ModificacionPresupuestal;
use App\Services\PresupuestoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Modificaciones presupuestales: el área solicita (traspaso / reducción / ampliación), se imprime
 * el formato y el Jefe de Control Presupuestal la autoriza o rechaza. Al autorizarse, el vigente
 * de las líneas cambia (PresupuestoService suma las modificaciones autorizadas).
 */
class ModificacionPresupuestalController extends Controller
{
    public function __construct(private PresupuestoService $presupuesto) {}

    /** Restringe la consulta a las modificaciones cuyas líneas son de las unidades del usuario. */
    private function aplicarAlcance($query, ?array $claves)
    {
        if ($claves === null) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereIn('origen_clave', $claves ?: ['__ninguna__'])
            ->orWhereIn('destino_clave', $claves ?: ['__ninguna__']));
    }

    public function index(Request $request)
    {
        $claves = PresupuestoService::clavesUnidadVisibles(auth()->user());
        $query = $this->aplicarAlcance(ModificacionPresupuestal::with('solicitante:id,name', 'autorizador:id,name'), $claves);

        foreach (['tipo', 'estado'] as $campo) {
            if ($filtro = $request->input($campo)) {
                $query->where($campo, $filtro);
            }
        }
        if ($filtro = $request->input('folio')) {
            $query->where('folio_completo', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('partida')) {
            $query->where(fn ($q) => $q->where('origen_partida', 'like', "%{$filtro}%")->orWhere('destino_partida', 'like', "%{$filtro}%")
                ->orWhere('origen_descripcion', 'like', "%{$filtro}%")->orWhere('destino_descripcion', 'like', "%{$filtro}%"));
        }

        return Inertia::render('modificaciones-presupuestales/Index', [
            'modificaciones' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $request->only(['tipo', 'estado', 'folio', 'partida']),
            'tipos' => ModificacionPresupuestal::TIPOS,
        ]);
    }

    public function create(Request $request)
    {
        $claves = PresupuestoService::clavesUnidadVisibles(auth()->user());
        $requisicion = $request->integer('requisicion') ? EgresoRequisicion::with('catEgresoUnidadAdministrativa')->find($request->integer('requisicion')) : null;

        // Si viene de una requisición sin suficiencia, se propone como destino la primera línea que no alcanza.
        $destinoSugerido = null;
        $faltanteSugerido = null;
        if ($requisicion && $requisicion->suficiencia === false) {
            $faltante = collect($this->presupuesto->validarRequisicion($requisicion)['partidas'])->firstWhere('suficiente', false);
            $destinoSugerido = $faltante['clave'] ?? null;
            $faltanteSugerido = $faltante['faltante'] ?? null;
        }

        return Inertia::render('modificaciones-presupuestales/Create', [
            'lineas' => $this->presupuesto->lineas($claves),
            'tipos' => ModificacionPresupuestal::TIPOS,
            'requisicion' => $requisicion?->only(['id_tb_egreso_requisicion', 'folio_completo']),
            'destinoSugerido' => $destinoSugerido,
            'importeSugerido' => $faltanteSugerido,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(ModificacionPresupuestal::TIPOS))],
            'origen' => 'nullable|required_if:tipo,traspaso,reduccion|string|max:400',
            'destino' => 'nullable|required_if:tipo,traspaso,ampliacion|string|max:400|different:origen',
            'importe' => 'required|numeric|min:0.01|max:9999999999',
            'justificacion' => 'required|string|max:5000',
            'id_tb_egreso_requisicion' => 'nullable|integer|exists:tb_egreso_requisicion,id_tb_egreso_requisicion',
        ], [
            'origen.required_if' => 'Selecciona la línea de la que sale el recurso.',
            'destino.required_if' => 'Selecciona la línea que recibe el recurso.',
            'destino.different' => 'El origen y el destino deben ser líneas distintas.',
        ]);

        $tipo = $validated['tipo'];
        $usaOrigen = in_array($tipo, ['traspaso', 'reduccion'], true);
        $usaDestino = in_array($tipo, ['traspaso', 'ampliacion'], true);

        $claves = PresupuestoService::clavesUnidadVisibles(auth()->user());
        $origen = $usaOrigen ? $this->lineaPermitida($validated['origen'], $claves, 'origen') : null;
        $destino = $usaDestino ? $this->lineaPermitida($validated['destino'], $claves, 'destino') : null;
        $importe = round((float) $validated['importe'], 2);

        if ($origen && $importe > $origen['disponible']) {
            throw ValidationException::withMessages(['importe' => 'El importe excede el disponible de la línea de origen ($'.number_format($origen['disponible'], 2).').']);
        }

        $modificacion = DB::transaction(function () use ($validated, $tipo, $origen, $destino, $importe) {
            $año = (int) date('Y');
            $folio = (ModificacionPresupuestal::where('año', $año)->lockForUpdate()->max('folio') ?? 0) + 1;

            return ModificacionPresupuestal::create([
                'folio' => $folio,
                'año' => $año,
                'folio_completo' => sprintf('MP-%d-%04d', $año, $folio),
                'tipo' => $tipo,
                'estado' => 'pendiente',
                ...$this->camposLinea('origen', $origen),
                ...$this->camposLinea('destino', $destino),
                'importe' => $importe,
                'justificacion' => $validated['justificacion'],
                'id_tb_egreso_requisicion' => $validated['id_tb_egreso_requisicion'] ?? null,
                'solicitante_id' => auth()->id(),
            ]);
        });

        return redirect()->route('modificaciones-presupuestales.show', $modificacion)->with('success', "Modificación {$modificacion->folio_completo} registrada. Imprime el formato para su autorización.");
    }

    public function show(ModificacionPresupuestal $modificacion)
    {
        $this->autorizarVer($modificacion);
        $modificacion->load('solicitante:id,name', 'autorizador:id,name', 'requisicion:id_tb_egreso_requisicion,folio_completo');

        return Inertia::render('modificaciones-presupuestales/Show', [
            'modificacion' => $modificacion,
            'tipos' => ModificacionPresupuestal::TIPOS,
            'origen' => ($c = $modificacion->claveLinea('origen')) ? $this->presupuesto->linea($c) : null,
            'destino' => ($c = $modificacion->claveLinea('destino')) ? $this->presupuesto->linea($c) : null,
        ]);
    }

    public function pdf(ModificacionPresupuestal $modificacion)
    {
        $this->autorizarVer($modificacion);
        $modificacion->load('solicitante:id,name', 'autorizador:id,name', 'requisicion:id_tb_egreso_requisicion,folio_completo');

        return Pdf::loadView('modificaciones-presupuestales.pdf', [
            'modificacion' => $modificacion,
            'tipos' => ModificacionPresupuestal::TIPOS,
            'origen' => ($c = $modificacion->claveLinea('origen')) ? $this->presupuesto->linea($c) : null,
            'destino' => ($c = $modificacion->claveLinea('destino')) ? $this->presupuesto->linea($c) : null,
        ])->stream("{$modificacion->folio_completo}.pdf");
    }

    public function autorizar(Request $request, ModificacionPresupuestal $modificacion)
    {
        $validated = $request->validate(['observaciones' => 'nullable|string|max:3000']);

        DB::transaction(function () use ($modificacion, $validated) {
            $mod = ModificacionPresupuestal::whereKey($modificacion->id)->lockForUpdate()->firstOrFail();
            if ($mod->estado !== 'pendiente') {
                throw ValidationException::withMessages(['observaciones' => 'Esta modificación ya fue resuelta.']);
            }

            $origen = ($c = $mod->claveLinea('origen')) ? $this->presupuesto->linea($c) : null;
            $destino = ($c = $mod->claveLinea('destino')) ? $this->presupuesto->linea($c) : null;

            if (($mod->claveLinea('origen') && ! $origen) || ($mod->claveLinea('destino') && ! $destino)) {
                throw ValidationException::withMessages(['observaciones' => 'Una de las líneas ya no existe en el presupuesto importado.']);
            }
            // El disponible pudo cambiar desde la solicitud (otras requisiciones o modificaciones).
            if ($origen && $mod->importe > $origen['disponible']) {
                throw ValidationException::withMessages(['observaciones' => 'El disponible actual de la línea de origen ($'.number_format($origen['disponible'], 2).') ya no cubre el importe.']);
            }

            $mod->update([
                'estado' => 'autorizada',
                'autorizador_id' => auth()->id(),
                'resuelto_at' => now(),
                'observaciones_resolucion' => $validated['observaciones'] ?? null,
                'origen_disponible_antes' => $origen['disponible'] ?? null,
                'destino_vigente_antes' => $destino['vigente'] ?? null,
            ]);
        });

        return back()->with('success', 'Modificación autorizada: el presupuesto quedó actualizado.');
    }

    public function rechazar(Request $request, ModificacionPresupuestal $modificacion)
    {
        $validated = $request->validate(['observaciones' => 'required|string|max:3000'], [
            'observaciones.required' => 'Indica el motivo por el que no procede.',
        ]);

        if ($modificacion->estado !== 'pendiente') {
            return back()->with('error', 'Esta modificación ya fue resuelta.');
        }

        $modificacion->update([
            'estado' => 'rechazada',
            'autorizador_id' => auth()->id(),
            'resuelto_at' => now(),
            'observaciones_resolucion' => $validated['observaciones'],
        ]);

        return back()->with('success', 'Modificación rechazada.');
    }

    private function lineaPermitida(string $clave, ?array $clavesUnidad, string $campo): array
    {
        $unidad = explode('|', $clave)[0];
        $linea = $this->presupuesto->linea($clave);

        if (! $linea || ($clavesUnidad !== null && ! in_array($unidad, $clavesUnidad, true))) {
            throw ValidationException::withMessages([$campo => 'Esa línea de presupuesto no existe o no pertenece a tu secretaría.']);
        }

        return $linea;
    }

    private function camposLinea(string $lado, ?array $linea): array
    {
        return [
            "{$lado}_clave" => $linea['unidad_clave'] ?? null,
            "{$lado}_fuente" => $linea['fuente'] ?? null,
            "{$lado}_proyecto" => $linea['proyecto'] ?? null,
            "{$lado}_partida" => $linea['partida'] ?? null,
            "{$lado}_descripcion" => $linea
                ? mb_substr("{$linea['unidad']} · {$linea['fuente']} · {$linea['proyecto']} · {$linea['partida']} {$linea['nombre_partida']}", 0, 500)
                : null,
        ];
    }

    private function autorizarVer(ModificacionPresupuestal $modificacion): void
    {
        $claves = PresupuestoService::clavesUnidadVisibles(auth()->user());
        if ($claves !== null && ! in_array($modificacion->origen_clave, $claves, true) && ! in_array($modificacion->destino_clave, $claves, true)) {
            abort(403, 'Esta modificación no pertenece a tu secretaría.');
        }
    }
}
