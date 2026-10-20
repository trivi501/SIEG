<?php

namespace App\Http\Controllers;

use App\Models\CorridaSaldo;
use App\Models\EgresoRequisicion;
use App\Models\EgresoRequisicionBitacora;
use App\Models\EgresoRequisicionDetalle;
use App\Models\EgresoRequisicionEstado;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\EjercicioFiscal;
use App\Models\ModificacionPresupuestal;
use App\Models\PresupuestoEgreso;
use App\Models\Proveedor;
use App\Models\VehiculoMantenimiento;
use App\Services\ExcelGasto;
use App\Services\PresupuestoService;
use App\Services\SaldosMensuales;
use App\Services\TramiteClc;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EgresoRequisicionController extends Controller
{
    /**
     * Ids de cat_egreso_unidad_administrativa visibles para el usuario: null (todas) si es Admin o Control Presupuestal;
     * si no, la de su secretaría o dirección (en todos los años); [] si no tiene asignada.
     */
    private function unidadesVisibles($user, bool $isAdmin): ?array
    {
        // Admin y Control Presupuestal ven todas; las áreas, solo las de su unidad.
        if ($isAdmin || self::veTodas($user)) {
            return null;
        }

        return EgresoUnidadAdministrativa::idsMismaClave($user->id_cat_egreso_unidad_administrativa);
    }

    /** Admin o Control Presupuestal (autoriza modificaciones): ve y trabaja las requisiciones de todas las áreas. */
    private static function veTodas($user): bool
    {
        return PresupuestoService::clavesUnidadVisibles($user) === null;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = self::veTodas($user);

        $query = EgresoRequisicion::with(['catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa:id_cat_egreso_unidad_administrativa,clave,nombre',
            'ordenCompra:id,id_tb_egreso_requisicion,folio_completo,cancelada_at,clc_folio_completo,clc_estado,clc_cancelada_at']);

        $unidades = $this->unidadesVisibles($user, $isAdmin);
        if ($unidades !== null) {
            $query->whereIn('id_cat_egreso_unidad_administrativa', $unidades);
        }

        if ($filtro = $request->input('folio')) {
            $query->where('folio', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('folio_completo')) {
            $query->where('folio_completo', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('concepto')) {
            $query->where('observaciones', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('estado')) {
            $query->where('id_cat_egreso_requisicion_estado', $filtro);
        }
        if ($filtro = $request->input('año')) {
            $query->where('año', $filtro);
        }
        if ($filtro = $request->input('unidad')) {
            $query->whereHas('catEgresoUnidadAdministrativa', fn ($q) => $q->where(fn ($w) => $w->where('clave', 'like', "%{$filtro}%")->orWhere('nombre', 'like', "%{$filtro}%")));
        }

        return Inertia::render('requisiciones/Index', [
            'requisiciones' => $query->orderBy('id_tb_egreso_requisicion', 'desc')->paginate(15)->withQueryString(),
            'estados' => EgresoRequisicionEstado::all(['id_cat_egreso_requisicion_estado', 'descripcion']),
            'filters' => $request->only(['folio', 'folio_completo', 'unidad', 'concepto', 'estado', 'año']),
            'isAdmin' => $isAdmin,
            'estadosClc' => collect(TramiteClc::ESTADOS)->map(fn ($e) => $e[0]),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $isAdmin = self::veTodas($user);
        $mantenimiento = $this->mantenimientoParaRequisicion($request->input('mantenimiento'));

        $unidadesQuery = EgresoUnidadAdministrativa::where('activo', 1);
        $idsVisibles = $this->unidadesVisibles($user, $isAdmin);
        if ($mantenimiento) {
            // Mantenimiento vehicular: la requisición va a nombre de la secretaría de resguardo del vehículo.
            $unidadesQuery->whereKey($mantenimiento->id_cat_egreso_unidad_administrativa);
        } elseif ($idsVisibles !== null) {
            $unidadesQuery->whereIn('id_cat_egreso_unidad_administrativa', $idsVisibles);
        }
        $unidades = $unidadesQuery->orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'clave', 'nombre']);
        $unidadDefault = ! $isAdmin || $mantenimiento ? $unidades->first()?->id_cat_egreso_unidad_administrativa : null;

        $hoy = now('America/Mexico_City');
        $periodo = EjercicioFiscal::periodoDelMes($hoy);

        return Inertia::render('requisiciones/Create', [
            'catalogs' => [
                'unidades' => $unidades,
                'lineas' => $this->lineasParaCaptura($unidades),
            ],
            'unidadDefault' => $unidadDefault,
            'mantenimiento' => $mantenimiento ? [
                'id' => $mantenimiento->id,
                'folio' => $mantenimiento->folio_completo,
                'vehiculo' => $mantenimiento->textoVehiculo(),
                'area' => $mantenimiento->departamento?->nombre,
                'tipo' => VehiculoMantenimiento::TIPOS[$mantenimiento->tipo] ?? $mantenimiento->tipo,
                'descripcion' => $mantenimiento->descripcion,
            ] : null,
            'periodoCaptura' => [
                'inicio' => $periodo['inicio']->format('d/m/Y'),
                'fin' => $periodo['fin']->format('d/m/Y'),
                'abierto' => EjercicioFiscal::enPeriodoDeCaptura($hoy),
            ],
        ]);
    }

    /**
     * Líneas de presupuesto (fuente + proyecto + partida en una sola opción) de las unidades
     * dadas, con su disponible, para el selector de cada concepto.
     */
    private function lineasParaCaptura($unidades): array
    {
        return app(PresupuestoService::class)
            ->lineas($unidades->pluck('clave')->map(fn ($c) => trim((string) $c))->all())
            ->map(fn ($l) => collect($l)->only([
                'clave', 'unidad_clave', 'fuente', 'nombre_fuente', 'proyecto', 'nombre_proyecto', 'partida', 'nombre_partida', 'disponible',
            ]))
            ->values()
            ->all();
    }

    /** Estados en los que la requisición todavía se puede modificar. */
    private function puedeEditarse(EgresoRequisicion $requisicion): bool
    {
        return match ($requisicion->id_cat_egreso_requisicion_estado) {
            1, 4 => true,                                     // pendiente / no válida (rechazada)
            6 => $requisicion->suficiencia !== true,          // cotización, hasta que tenga suficiencia
            default => false,                                 // en revisión, validada, en Recursos Materiales
        };
    }

    private const IVA_FACTOR = 0.16;

    /**
     * Calcula cantidad/precio/iva/subtotal/total de cada fila; el total de la requisición
     * se deriva siempre de esta suma, nunca se captura a mano, para que nunca puedan quedar distintos.
     */
    /**
     * Reglas de la captura de una requisición (alta y edición): el concepto es obligatorio y debe
     * llevar al menos un renglón con su descripción.
     *
     * @return array{0: array, 1: array, 2: array} reglas, mensajes y nombres de atributos para validate()
     */
    /**
     * Mantenimiento vehicular del que se hace la requisición: en el taller, sin requisición y solo para
     * quien recibe vehículos (vehiculos-mantenimiento) o Admin.
     */
    private function mantenimientoParaRequisicion($id): ?VehiculoMantenimiento
    {
        $user = auth()->user();
        if (! $id || ! ($user->can('vehiculos-mantenimiento') || $user->hasRole(['Super Admin', 'Admin']))) {
            return null;
        }

        return VehiculoMantenimiento::with('vehiculo', 'departamento')->whereKey($id)
            ->where('estado', 'en_taller')->whereNull('id_tb_egreso_requisicion')->first();
    }

    private function reglasCaptura(): array
    {
        return [
            [
                'id_cat_egreso_unidad_administrativa' => 'required|integer',
                'concepto' => 'required|string|max:3000',
                'fecha_tramite' => 'required|date',
                'incluye_iva' => 'boolean',
                'filas' => 'required|array|min:1',
                'filas.*.fuente' => 'nullable|string|max:200',
                'filas.*.proyecto' => 'nullable|string|max:200',
                'filas.*.partida' => 'nullable|string|max:200',
                'filas.*.cantidad' => 'nullable|numeric',
                'filas.*.descripcion' => 'required|string|max:3000',
            ],
            [
                'concepto.required' => 'Captura el concepto de la requisición.',
                'filas.required' => 'Agrega al menos un renglón.',
                'filas.min' => 'Agrega al menos un renglón.',
                'filas.*.descripcion.required' => 'Describe el artículo, material o servicio del renglón.',
            ],
            ['id_cat_egreso_unidad_administrativa' => 'unidad administrativa', 'fecha_tramite' => 'fecha de trámite'],
        ];
    }

    private function calcularFilas(array $filas, bool $incluyeIva): array
    {
        $ivaFactor = $incluyeIva ? self::IVA_FACTOR : 0;
        $calculadas = [];
        $totales = ['sub_total' => 0.0, 'iva' => 0.0, 'total' => 0.0];

        foreach ($filas as $fila) {
            $cantidad = (float) ($fila['cantidad'] ?? 1);
            $precio = isset($fila['precio_unitario']) && $fila['precio_unitario'] !== '' ? (float) $fila['precio_unitario'] : null;
            $subTotal = $precio !== null ? round($precio * $cantidad, 4) : null;
            $iva = $subTotal !== null ? round($subTotal * $ivaFactor, 4) : null;
            $total = $subTotal !== null ? round($subTotal + $iva, 4) : null;

            $calculadas[] = $fila + [
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'sub_total' => $subTotal,
                'iva' => $iva,
                'iva_factor' => $ivaFactor,
                'total' => $total,
            ];

            $totales['sub_total'] += $subTotal ?? 0;
            $totales['iva'] += $iva ?? 0;
            $totales['total'] += $total ?? 0;
        }

        return [$calculadas, $totales];
    }

    /**
     * Cada concepto capturado debe ir a una línea (fuente + proyecto + partida) que exista
     * en el presupuesto de la unidad administrativa elegida.
     */
    private function validarLineasPresupuesto(array $filas, ?EgresoUnidadAdministrativa $unidad): void
    {
        $existentes = PresupuestoEgreso::where('CLAVE', $unidad?->clave)
            ->get(['CLAVE', 'CLAVE3', 'CLAVE6', 'PARTIDA'])
            ->map(fn ($p) => PresupuestoService::clave($p->CLAVE, $p->CLAVE3, $p->CLAVE6, $p->PARTIDA))
            ->flip();

        $errores = [];
        foreach ($filas as $i => $fila) {
            if (empty($fila['descripcion'])) {
                continue;
            }
            $clave = PresupuestoService::clave($unidad?->clave, $fila['fuente'] ?? null, $fila['proyecto'] ?? null, $fila['partida'] ?? null);
            if (! isset($existentes[$clave])) {
                $errores["filas.{$i}.partida"] = 'Selecciona una fuente / proyecto / partida del presupuesto de la unidad (concepto '.($i + 1).').';
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate(...$this->reglasCaptura());

        $user = auth()->user();
        $mantenimiento = $this->mantenimientoParaRequisicion($request->input('mantenimiento_id'));
        if ($request->filled('mantenimiento_id') && ! $mantenimiento) {
            throw ValidationException::withMessages(['concepto' => 'Ese mantenimiento ya tiene requisición o ya se entregó el vehículo.']);
        }
        if ($mantenimiento) {
            if ((int) $validated['id_cat_egreso_unidad_administrativa'] !== (int) $mantenimiento->id_cat_egreso_unidad_administrativa) {
                abort(403, 'La requisición del mantenimiento va a nombre de la secretaría de resguardo del vehículo.');
            }
        } else {
            $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
            if ($idsVisibles !== null && ! in_array((int) $validated['id_cat_egreso_unidad_administrativa'], $idsVisibles, true)) {
                abort(403, 'Esa unidad administrativa no pertenece a tu secretaría.');
            }
        }

        // El folio se numera por el año en curso: si ese ejercicio está cerrado no se admiten requisiciones.
        if (EjercicioFiscal::estaCerrado((int) date('Y'))) {
            throw ValidationException::withMessages(['fecha_tramite' => 'El ejercicio fiscal '.date('Y').' está cerrado; ya no admite requisiciones nuevas.']);
        }

        $unidad = EgresoUnidadAdministrativa::find($validated['id_cat_egreso_unidad_administrativa']);
        $this->validarLineasPresupuesto($validated['filas'] ?? [], $unidad);
        $prefijo = $unidad?->prefijo ?: 'REQ';

        $maxFolio = EgresoRequisicion::where('año', date('Y'))->max('folio') ?? 0;
        $nuevoFolio = $maxFolio + 1;
        $folioCompleto = $prefijo.'-'.str_pad($nuevoFolio, 4, '0', STR_PAD_LEFT);

        $newId = (EgresoRequisicion::max('id_tb_egreso_requisicion') ?? 0) + 1;

        [$filasCalculadas, $totales] = $this->calcularFilas($validated['filas'] ?? [], (bool) ($validated['incluye_iva'] ?? false));

        // Fuera del periodo ordinario de captura queda extemporánea (no avanza sin autorización).
        $extemporanea = ! EjercicioFiscal::enPeriodoDeCaptura(now('America/Mexico_City'));

        DB::transaction(function () use ($user, $validated, $newId, $folioCompleto, $nuevoFolio, $filasCalculadas, $totales, $extemporanea, $mantenimiento) {
            $requisicion = EgresoRequisicion::create([
                'id_tb_egreso_requisicion' => $newId,
                'id_cat_egreso_requisicion_estado' => 1, // pendiente
                'id_cat_egreso_solicitud_tipo' => 1,
                'id_tb_egreso_presupuesto' => 1,
                'id_cat_egreso_unidad_administrativa' => $validated['id_cat_egreso_unidad_administrativa'],
                'id_cat_area_x_nombre_y_puesto_Solicita' => 1,
                'id_cat_area_x_nombre_y_puesto_Autoriza' => 1,
                'id_tb_usuarios' => 1,
                'id_user_laravel' => $user->id,
                'id_tb_egreso_presupuesto_detalle' => null,
                'id_tb_egreso_proveedor' => null,
                'folio' => $nuevoFolio,
                'año' => date('Y'),
                'folio_completo' => $folioCompleto,
                'solicitado' => $validated['fecha_tramite'],
                'observaciones' => $validated['concepto'] ?? null,
                'sub_total' => $totales['sub_total'],
                'isr' => null,
                'iva' => $totales['iva'],
                'descuento' => 0,
                'total' => $totales['total'],
                'monto_requisicion' => $totales['total'],
                'sub_direccion' => null,
                'departamento' => $mantenimiento?->departamento?->nombre,
                'beneficiario' => null,
                'beneficiario_referencia' => null,
                'extemporanea' => $extemporanea,
            ]);

            if ($mantenimiento) {
                $mantenimiento->update(['id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion]);
            }

            // Create detalle records from filas
            if (! empty($filasCalculadas)) {
                $detalleId = (EgresoRequisicionDetalle::max('id_tb_egreso_requisicion_detalle') ?? 0) + 1;
                foreach ($filasCalculadas as $fila) {
                    if (empty($fila['descripcion'])) {
                        continue;
                    }
                    EgresoRequisicionDetalle::create([
                        'id_tb_egreso_requisicion_detalle' => $detalleId++,
                        'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                        'id_cat_egreso_producto' => 1,
                        'cantidad' => $fila['cantidad'],
                        'descripcion' => $fila['descripcion'] ?? '',
                        'fuente' => $fila['fuente'] ?? null,
                        'proyecto' => $fila['proyecto'] ?? null,
                        'partida' => $fila['partida'] ?? null,
                        'articulo' => $fila['descripcion'] ?? null,
                        'precio_unitario' => $fila['precio_unitario'],
                        'sub_total' => $fila['sub_total'],
                        'iva' => $fila['iva'],
                        'iva_factor' => $fila['iva_factor'],
                        'descuento' => 0,
                        'total' => $fila['total'],
                        'id_tb_egreso_presupuesto_detalle' => null,
                        'id_tb_egreso_presupuesto' => null,
                    ]);
                }
            }

            // Bitácora - creación
            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => 1,
                'id_cat_egreso_requisicion_estado_nuevo' => 1,
                'id_tb_usuarios' => 1,
                'observaciones' => $extemporanea
                    ? 'Requisición creada fuera del periodo de captura (extemporánea): requiere autorización de la Subdirección Administrativa.'
                    : 'Requisición creada.',
            ]);
        });

        return redirect()->route('requisiciones.index')->with(
            $extemporanea ? 'error' : 'success',
            $extemporanea
                ? 'Requisición creada como EXTEMPORÁNEA: la Subdirección Administrativa debe autorizarla; al autorizarla se envía sola a revisión.'
                : 'Requisición creada.',
        );
    }

    public function show(EgresoRequisicion $requisicion)
    {
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa', 'tbEgresoRequisicionBitacoras', 'ordenCompra.proveedor', 'ordenCompra.movimientosClc.usuario:id,name', 'ordenCompra.canceladaPor:id,name', 'ordenCompra.refacturas.usuario:id,name', 'ordenCompra.clcCanceladaPor:id,name', 'extemporaneaAutorizador:id,name', 'canceladaPor:id,name');

        $this->autorizarUnidad($requisicion);

        // fuente/proyecto/partida en el detalle son códigos en texto libre; se resuelve su nombre
        // contra el catálogo de presupuesto importado, acotado a la unidad de esta requisición.
        $unidadClave = $requisicion->catEgresoUnidadAdministrativa?->clave;
        $nombresPresupuesto = PresupuestoEgreso::when($unidadClave, fn ($q) => $q->where('CLAVE', $unidadClave))
            ->get(['CLAVE3', 'FUENTE_DE_FINANCIAMIENTO', 'CLAVE6', 'PROYECTO', 'PARTIDA', 'NOMBRE_PARTIDA'])
            ->reduce(function ($acc, $p) {
                if ($p->CLAVE3) {
                    $acc['fuentes'][$p->CLAVE3] ??= trim($p->FUENTE_DE_FINANCIAMIENTO ?? '');
                }
                if ($p->CLAVE6) {
                    $acc['proyectos'][$p->CLAVE6] ??= trim($p->PROYECTO ?? '');
                }
                if ($p->PARTIDA) {
                    $acc['partidas'][$p->PARTIDA] ??= trim($p->NOMBRE_PARTIDA ?? '');
                }

                return $acc;
            }, ['fuentes' => [], 'proyectos' => [], 'partidas' => []]);

        $user = auth()->user();

        return Inertia::render('requisiciones/Show', [
            'requisicion' => $requisicion,
            'isAdmin' => $user->hasRole(['Super Admin', 'Admin']),
            'nombresPresupuesto' => $nombresPresupuesto,
            'validacion' => app(PresupuestoService::class)->validarRequisicion($requisicion),
            'puedeEditarse' => $this->puedeEditarse($requisicion),
            // Periodo de captura del mes en que se registró (registro: MySQL en hora local).
            'periodoCaptura' => (function () use ($requisicion) {
                $p = EjercicioFiscal::periodoDelMes($requisicion->registro ? Carbon::parse($requisicion->registro, 'America/Mexico_City') : now('America/Mexico_City'));

                return [$p['inicio']->format('d/m/Y'), $p['fin']->format('d/m/Y')];
            })(),
            'puedeCambiarFecha' => $this->puedeCambiarFecha($requisicion),
            'puedeCancelar' => $this->puedeCancelar($requisicion),
            'estadosClc' => collect(TramiteClc::ESTADOS)->map(fn ($e) => $e[0]),
            // Si viene de un mantenimiento vehicular (taller).
            'mantenimiento' => ($m = VehiculoMantenimiento::with('vehiculo', 'departamento')->where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->first())
                ? ['folio' => $m->folio_completo, 'vehiculo' => $m->textoVehiculo(), 'area' => $m->departamento?->nombre, 'estado' => VehiculoMantenimiento::ESTADOS[$m->estado] ?? $m->estado]
                : null,
            'puedeCambiarFechaOrden' => $user->can('ordenes-compra-fecha') && ! $requisicion->ordenCompra?->cancelada_at,
            // Cancelación escalonada: primero la CLC, luego la orden de compra (puedeCancelar es la requisición).
            'puedeCancelarClc' => $requisicion->ordenCompra && TramiteClc::impedimentoParaCancelar($requisicion->ordenCompra, $user) === null,
            // Si la CLC está vigente pero ya no le toca cancelarla, por qué (se muestra junto al botón).
            'motivoNoCancelarClc' => $requisicion->ordenCompra?->clcActiva() && $user->can('clc-cancelar')
                ? TramiteClc::impedimentoParaCancelar($requisicion->ordenCompra, $user) : null,
            'puedeCancelarOrden' => $user->can('ordenes-compra-cancelar') && $requisicion->ordenCompra
                && ! $requisicion->ordenCompra->cancelada_at && ! $requisicion->ordenCompra->clcActiva(),
            // Para generar la orden de compra o reasignar su proveedor (mientras no tenga factura).
            'proveedores' => $user->can('ordenes-compra-create') && (
                ($requisicion->id_cat_egreso_requisicion_estado === EgresoRequisicion::COMPROMETIDA && ! $requisicion->ordenCompra)
                || ($requisicion->ordenCompra && ! $requisicion->ordenCompra->xml_uuid && ! $requisicion->ordenCompra->cancelada_at))
                ? Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'rfc', 'vigencia'])
                    ->map(fn (Proveedor $p) => [...$p->only('id', 'nombre', 'rfc'), 'vigencia' => $p->vigencia?->format('d/m/Y'), 'vencido' => ! $p->vigente()])
                : [],
            'modificaciones' => ModificacionPresupuestal::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)
                ->orderByDesc('id')
                ->get(['id', 'folio_completo', 'tipo', 'estado', 'importe']),
        ]);
    }

    /** Fuera de Recursos Materiales, solo se ve lo de las unidades de la propia secretaría. */
    private function autorizarUnidad(EgresoRequisicion $requisicion): void
    {
        $user = auth()->user();
        if ($user->can('ordenes-compra-create') || $user->can('requisiciones-suficiencia')) {
            return;
        }
        if ($user->can('vehiculos-mantenimiento') && VehiculoMantenimiento::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->exists()) {
            return;
        }

        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
        if ($idsVisibles !== null && ! in_array((int) $requisicion->id_cat_egreso_unidad_administrativa, $idsVisibles, true)) {
            abort(403, 'Esta requisición no pertenece a tu secretaría.');
        }
    }

    public function edit(EgresoRequisicion $requisicion)
    {
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa');

        if (! $this->puedeEditarse($requisicion)) {
            return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)
                ->with('error', 'Esta requisición ya no se puede modificar en su estado actual.');
        }
        abort_if($requisicion->id_cat_egreso_requisicion_estado === 6 && ! auth()->user()->can('requisiciones-cotizar'), 403, 'La cotización la captura Recursos Materiales.');

        $user = auth()->user();
        $unidadesQuery = EgresoUnidadAdministrativa::where('activo', 1);
        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
        if ($idsVisibles !== null && $requisicion->id_cat_egreso_requisicion_estado !== 6) {
            $unidadesQuery->whereIn('id_cat_egreso_unidad_administrativa', $idsVisibles);
        }
        $unidades = $unidadesQuery->orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'clave', 'nombre']);
        // En cotización la unidad ya no cambia; basta con sus líneas para mostrar los nombres.
        if ($requisicion->id_cat_egreso_requisicion_estado === 6) {
            $unidades = $unidades->where('id_cat_egreso_unidad_administrativa', $requisicion->id_cat_egreso_unidad_administrativa)->values();
        }

        return Inertia::render('requisiciones/Edit', [
            'requisicion' => $requisicion,
            'catalogs' => [
                'unidades' => $unidades,
                'lineas' => $this->lineasParaCaptura($unidades),
            ],
        ]);
    }

    public function update(Request $request, EgresoRequisicion $requisicion)
    {
        if (! $this->puedeEditarse($requisicion)) {
            abort(403, 'Esta requisición ya no se puede modificar en su estado actual.');
        }

        $esCotizacion = $requisicion->id_cat_egreso_requisicion_estado === 6;
        abort_if($esCotizacion && ! $request->user()->can('requisiciones-cotizar'), 403, 'La cotización la captura Recursos Materiales.');

        if ($esCotizacion) {
            $validated = $request->validate([
                'fecha_tramite' => 'required|date',
                'filas' => 'nullable|array',
                'filas.*.precio_unitario' => 'nullable|numeric|min:0|decimal:0,4', // la cotización guarda hasta 4 decimales
                // El IVA se decide aquí, en Recursos Materiales, al cotizar, y por renglón.
                'filas.*.lleva_iva' => 'boolean',
            ]);

            // Uno por uno (no un update masivo) para que quede en la bitácora de auditoría.
            $detalles = EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->orderBy('id_tb_egreso_requisicion_detalle')->get();
            foreach ($validated['filas'] ?? [] as $i => $fila) {
                if (! isset($detalles[$i])) {
                    continue;
                }
                $precio_val = $fila['precio_unitario'] ?? '';
                $precio = ($precio_val !== '' && is_numeric($precio_val)) ? (float) $precio_val : null;
                $ivaFactor = ! empty($fila['lleva_iva']) ? self::IVA_FACTOR : 0;
                $cant = (float) $detalles[$i]->cantidad;
                $subTotal = $precio !== null ? round($precio * $cant, 4) : null;
                $iva = $subTotal !== null ? round($subTotal * $ivaFactor, 4) : null;
                $detalles[$i]->update([
                    'precio_unitario' => $precio,
                    'iva_factor' => $ivaFactor,
                    'sub_total' => $subTotal,
                    'iva' => $iva,
                    'total' => $subTotal !== null ? round($subTotal + $iva, 4) : null,
                ]);
            }

            // El total de la requisición siempre es la suma de sus conceptos, nunca se captura aparte.
            $detalles = EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->get();
            $requisicion->update([
                'solicitado' => $validated['fecha_tramite'],
                'sub_total' => $detalles->sum('sub_total'),
                'iva' => $detalles->sum('iva'),
                'total' => $detalles->sum('total'),
                'monto_requisicion' => $detalles->sum('total'),
                // Si cambian los precios hay que volver a validar la suficiencia.
                'suficiencia' => null,
                'suficiencia_fecha' => null,
                'suficiencia_user_id' => null,
            ]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_tb_usuarios' => 1,
                'observaciones' => 'Cotización actualizada: precios unitarios modificados.',
            ]);
        } else {
            $validated = $request->validate(...$this->reglasCaptura());

            $user = auth()->user();
            $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
            if ($idsVisibles !== null && ! in_array((int) $validated['id_cat_egreso_unidad_administrativa'], $idsVisibles, true)) {
                abort(403, 'Esa unidad administrativa no pertenece a tu secretaría.');
            }

            $this->validarLineasPresupuesto($validated['filas'] ?? [], EgresoUnidadAdministrativa::find($validated['id_cat_egreso_unidad_administrativa']));

            [$filasCalculadas, $totales] = $this->calcularFilas($validated['filas'] ?? [], (bool) ($validated['incluye_iva'] ?? false));

            DB::transaction(function () use ($requisicion, $validated, $filasCalculadas, $totales) {
                $requisicion->update([
                    'id_cat_egreso_unidad_administrativa' => $validated['id_cat_egreso_unidad_administrativa'],
                    'observaciones' => $validated['concepto'] ?? null,
                    'solicitado' => $validated['fecha_tramite'],
                    'sub_total' => $totales['sub_total'],
                    'iva' => $totales['iva'],
                    'total' => $totales['total'],
                    'monto_requisicion' => $totales['total'],
                ]);

                EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)
                    ->get()
                    ->each(fn ($detalle) => $detalle->delete());

                if (! empty($filasCalculadas)) {
                    $detalleId = (EgresoRequisicionDetalle::max('id_tb_egreso_requisicion_detalle') ?? 0) + 1;
                    foreach ($filasCalculadas as $fila) {
                        if (empty($fila['descripcion'])) {
                            continue;
                        }
                        EgresoRequisicionDetalle::create([
                            'id_tb_egreso_requisicion_detalle' => $detalleId++,
                            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                            'id_cat_egreso_producto' => 1,
                            'cantidad' => $fila['cantidad'],
                            'descripcion' => $fila['descripcion'] ?? '',
                            'fuente' => $fila['fuente'] ?? null,
                            'proyecto' => $fila['proyecto'] ?? null,
                            'partida' => $fila['partida'] ?? null,
                            'articulo' => $fila['descripcion'] ?? null,
                            'precio_unitario' => $fila['precio_unitario'],
                            'sub_total' => $fila['sub_total'],
                            'iva' => $fila['iva'],
                            'iva_factor' => $fila['iva_factor'],
                            'descuento' => 0,
                            'total' => $fila['total'],
                            'id_tb_egreso_presupuesto_detalle' => null,
                            'id_tb_egreso_presupuesto' => null,
                        ]);
                    }
                }

                $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
                EgresoRequisicionBitacora::create([
                    'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                    'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                    'id_cat_egreso_requisicion_estado_anterior' => $requisicion->id_cat_egreso_requisicion_estado,
                    'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                    'id_tb_usuarios' => 1,
                    'observaciones' => 'Requisición modificada: unidad admin y partidas actualizadas.',
                ]);
            });
        }

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición actualizada.');
    }

    public function destroy(EgresoRequisicion $requisicion)
    {
        // Los conceptos se borran uno por uno primero para que la bitácora guarde lo que tenían.
        DB::transaction(function () use ($requisicion) {
            $requisicion->tbEgresoRequisicionDetalles()->get()->each(fn ($detalle) => $detalle->delete());
            $requisicion->delete();
        });

        return redirect()->route('requisiciones.index')->with('success', 'Requisición eliminada.');
    }

    public function enviarRevision(EgresoRequisicion $requisicion)
    {
        if ($requisicion->extemporaneaPendiente()) {
            return back()->with('error', 'Requisición extemporánea: la Subdirección Administrativa debe autorizarla antes de enviarla a revisión.');
        }

        $estadoAnterior = $requisicion->id_cat_egreso_requisicion_estado;
        $requisicion->update(['id_cat_egreso_requisicion_estado' => 2]);

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $estadoAnterior,
            'id_cat_egreso_requisicion_estado_nuevo' => 2,
            'id_tb_usuarios' => 1,
            'observaciones' => 'Enviado a revisión.',
        ]);

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición enviada a revisión.');
    }

    /**
     * Recursos Materiales y Control Presupuestal (permiso requisiciones-fecha) cambian la fecha de
     * cualquier requisición; el enlace de cada secretaría, la de las suyas.
     */
    private function puedeCambiarFecha(EgresoRequisicion $requisicion): bool
    {
        $user = auth()->user();
        if ($user->can('requisiciones-fecha')) {
            return true;
        }

        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));

        return $user->can('requisiciones-create')
            && ($idsVisibles === null || in_array((int) $requisicion->id_cat_egreso_unidad_administrativa, $idsVisibles, true));
    }

    /**
     * Antes de la orden de compra se puede cancelar: con el permiso requisiciones-cancelar en
     * cualquiera de esos estados; el enlace de la secretaría, las suyas mientras no se envíen
     * (pendiente o rechazada).
     */
    private function puedeCancelar(EgresoRequisicion $requisicion): bool
    {
        $estado = (int) $requisicion->id_cat_egreso_requisicion_estado;
        if ($estado === EgresoRequisicion::CANCELADA) {
            return false;
        }

        // Con orden de compra se cancela de lo último a lo primero: CLC, orden de compra y al final la requisición.
        $orden = $requisicion->ordenCompra;
        if ($orden) {
            return $orden->cancelada_at !== null && auth()->user()->can('requisiciones-cancelar');
        }
        if (! in_array($estado, EgresoRequisicion::CANCELABLES, true)) {
            return false;
        }

        $user = auth()->user();
        if ($user->can('requisiciones-cancelar')) {
            return true;
        }

        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));

        return in_array($estado, [1, 4], true) && $user->can('requisiciones-create')
            && ($idsVisibles === null || in_array((int) $requisicion->id_cat_egreso_unidad_administrativa, $idsVisibles, true));
    }

    /** Cancela la requisición con su motivo; deja de contar en el presupuesto y queda en la bitácora. */
    public function cancelar(Request $request, EgresoRequisicion $requisicion)
    {
        abort_unless($this->puedeCancelar($requisicion), 403, 'Esta requisición ya no se puede cancelar o no tienes permiso.');

        $validated = $request->validate(
            ['motivo' => 'required|string|min:5|max:1000'],
            ['motivo.required' => 'Indica el motivo de la cancelación.', 'motivo.min' => 'El motivo debe tener al menos 5 caracteres.'],
        );

        $user = auth()->user();
        $anterior = $requisicion->id_cat_egreso_requisicion_estado;

        DB::transaction(function () use ($requisicion, $validated, $user, $anterior) {
            $requisicion->update([
                'id_cat_egreso_requisicion_estado' => EgresoRequisicion::CANCELADA,
                'cancelada_at' => now(),
                'cancelada_user_id' => $user->id,
                'motivo_cancelacion' => $validated['motivo'],
            ]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $anterior,
                'id_cat_egreso_requisicion_estado_nuevo' => EgresoRequisicion::CANCELADA,
                'id_tb_usuarios' => 1,
                'observaciones' => "Requisición cancelada por {$user->name}. Motivo: {$validated['motivo']}",
            ]);
        });

        return back()->with('success', "Requisición {$requisicion->folio_completo} cancelada.");
    }

    /** Cambia la fecha de trámite de la requisición (la que imprime el FUR); queda en la bitácora. */
    public function cambiarFecha(Request $request, EgresoRequisicion $requisicion)
    {
        abort_unless($this->puedeCambiarFecha($requisicion), 403, 'No puedes cambiar la fecha de esta requisición.');

        $año = (int) ($requisicion->año ?: date('Y'));
        $validated = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', function ($attr, $valor, $fallo) use ($año) {
                if ((int) date('Y', strtotime($valor)) !== $año) {
                    $fallo("La fecha debe ser del ejercicio {$año}, como el folio de la requisición.");
                }
            }],
        ], ['fecha.before_or_equal' => 'La fecha no puede ser futura.']);

        $anterior = $requisicion->solicitado ? substr((string) $requisicion->solicitado, 0, 10) : null;
        if ($anterior === $validated['fecha']) {
            return back();
        }

        DB::transaction(function () use ($requisicion, $validated, $anterior) {
            $requisicion->update(['solicitado' => $validated['fecha']]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_tb_usuarios' => 1,
                'observaciones' => 'Fecha de trámite cambiada'.($anterior ? " del {$anterior}" : '')." al {$validated['fecha']} por ".auth()->user()->name.'.',
            ]);
        });

        return back()->with('success', 'Fecha de la requisición actualizada.');
    }

    /**
     * La Subdirección Administrativa (Adquisiciones) autoriza una requisición capturada fuera del
     * periodo; queda registrado quién y cuándo.
     */
    public function autorizarExtemporanea(EgresoRequisicion $requisicion)
    {
        if (! $requisicion->extemporaneaPendiente()) {
            return back()->with('error', 'Esta requisición no es extemporánea o ya fue autorizada.');
        }

        $user = auth()->user();

        // Al autorizarla se envía sola a revisión (si aún estaba pendiente o rechazada).
        $anterior = $requisicion->id_cat_egreso_requisicion_estado;
        $aRevision = in_array($anterior, [1, 4], true);

        DB::transaction(function () use ($requisicion, $user, $anterior, $aRevision) {
            $requisicion->update([
                'extemporanea_user_id' => $user->id,
                'extemporanea_autorizada_at' => now(),
                ...($aRevision ? ['id_cat_egreso_requisicion_estado' => 2] : []),
            ]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $anterior,
                'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_tb_usuarios' => 1,
                'observaciones' => "Requisición extemporánea autorizada por {$user->name}.".($aRevision ? ' Enviada a revisión.' : ''),
            ]);
        });

        return back()->with('success', $aRevision
            ? 'Requisición extemporánea autorizada y enviada a revisión.'
            : 'Requisición extemporánea autorizada.');
    }

    /**
     * Mueve la requisición al estado $nuevo si está en $desde y lo deja en su bitácora.
     * Devuelve false si no estaba en el estado esperado (doble clic, otra pestaña, otro usuario).
     */
    private function cambiarEstado(EgresoRequisicion $requisicion, int $desde, int $nuevo, string $observaciones): bool
    {
        if ($requisicion->id_cat_egreso_requisicion_estado !== $desde) {
            return false;
        }

        DB::transaction(function () use ($requisicion, $desde, $nuevo, $observaciones) {
            $requisicion->update(['id_cat_egreso_requisicion_estado' => $nuevo]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $desde,
                'id_cat_egreso_requisicion_estado_nuevo' => $nuevo,
                'id_tb_usuarios' => 1,
                'observaciones' => $observaciones,
            ]);
        });

        return true;
    }

    public function aprobar(EgresoRequisicion $requisicion)
    {
        if (! $this->cambiarEstado($requisicion, 2, 3, 'Requisición aprobada.')) {
            return back()->with('error', 'Solo se puede validar una requisición en revisión.');
        }

        return redirect()->route('requisiciones.index')->with('success', "Requisición {$requisicion->folio_completo} aprobada.");
    }

    /** "No válida": el motivo es obligatorio y queda en la bitácora para que el área lo corrija. */
    public function rechazar(Request $request, EgresoRequisicion $requisicion)
    {
        $validated = $request->validate(
            ['motivo' => 'required|string|min:5|max:1000'],
            ['motivo.required' => 'Escribe el motivo del rechazo.', 'motivo.min' => 'Explica el motivo del rechazo.'],
        );

        if (! $this->cambiarEstado($requisicion, 2, 4, 'Requisición rechazada. Motivo: '.trim($validated['motivo']))) {
            return back()->with('error', 'Solo se puede rechazar una requisición en revisión.');
        }

        return redirect()->route('requisiciones.index')->with('success', "Requisición {$requisicion->folio_completo} rechazada.");
    }

    /**
     * Recursos Materiales regresa la requisición al área (p. ej. un producto que no está en stock) para que
     * la modifique y la vuelva a enviar. Queda como No válida (4), editable por el área, con el motivo.
     */
    public function devolverArea(Request $request, EgresoRequisicion $requisicion)
    {
        $validated = $request->validate(
            ['motivo' => 'required|string|min:5|max:1000'],
            ['motivo.required' => 'Escribe por qué se regresa al área.', 'motivo.min' => 'Explica por qué se regresa al área.'],
        );
        if ($requisicion->suficiencia === true) {
            return back()->with('error', 'Ya tiene suficiencia presupuestal: ya no se regresa al área.');
        }

        $observaciones = 'Regresada al área por Recursos Materiales ('.auth()->user()->name.') para que la modifique. Motivo: '.trim($validated['motivo']);
        if (! $this->cambiarEstado($requisicion, 5, 4, $observaciones) && ! $this->cambiarEstado($requisicion, 6, 4, $observaciones)) {
            return back()->with('error', 'Solo se regresa al área una requisición en Recursos Materiales o en cotización.');
        }

        return redirect()->route('recursos-materiales.index')->with('success', "Requisición {$requisicion->folio_completo} regresada al área para que la modifique.");
    }

    public function enviarRecursosMateriales(EgresoRequisicion $requisicion)
    {
        if (! $this->cambiarEstado($requisicion, 3, 5, 'Enviado a Recursos Materiales. PDF firmado por el titular.')) {
            return back()->with('error', 'Solo se envía a Recursos Materiales una requisición aprobada.');
        }

        return back()->with('success', "Requisición {$requisicion->folio_completo} enviada a Recursos Materiales.");
    }

    public function recibido(EgresoRequisicion $requisicion)
    {
        if (! $this->cambiarEstado($requisicion, 5, 6, 'Recibido en Recursos Materiales. Inicia proceso de cotización.')) {
            return back()->with('error', 'Solo se recibe una requisición enviada a Recursos Materiales.');
        }

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición recibida. En cotización.');
    }

    /** En Recursos Materiales, En Cotización, Comprometida, Orden de Compra, Con Factura, CLC Generada, Pagada. */
    private const ESTADOS_RECURSOS_MATERIALES = [5, 6, 7, 8, 9, 10, 11];

    public function recursosMateriales(Request $request)
    {
        // Recursos Materiales recibe de todas las secretarías: aquí no se filtra por unidad administrativa.
        $query = EgresoRequisicion::with(['catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa', 'ordenCompra:id,id_tb_egreso_requisicion,folio_completo,xml_uuid'])
            ->whereIn('id_cat_egreso_requisicion_estado', self::ESTADOS_RECURSOS_MATERIALES);

        if ($filtro = $request->input('folio_completo')) {
            $query->where('folio_completo', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('concepto')) {
            $query->where('observaciones', 'like', "%{$filtro}%");
        }
        if ($filtro = $request->input('estado')) {
            $query->where('id_cat_egreso_requisicion_estado', $filtro);
        }

        $requisiciones = $query->orderBy('id_tb_egreso_requisicion', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('recursos-materiales/Index', [
            'requisiciones' => $requisiciones,
            'estados' => EgresoRequisicionEstado::whereIn('id_cat_egreso_requisicion_estado', self::ESTADOS_RECURSOS_MATERIALES)->get(['id_cat_egreso_requisicion_estado', 'descripcion']),
            'filters' => $request->only(['folio_completo', 'concepto', 'estado']),
        ]);
    }

    /**
     * Recursos Materiales manda a validar la cotización contra el presupuesto: si cada línea
     * alcanza a cubrir lo cotizado, la requisición queda con suficiencia (y su monto comprometido).
     */
    public function validarSuficiencia(EgresoRequisicion $requisicion)
    {
        if ($requisicion->id_cat_egreso_requisicion_estado !== 6 || $requisicion->suficiencia === true) {
            abort(403, 'Solo se valida la suficiencia de una requisición en cotización que aún no la tiene.');
        }

        $requisicion->load('tbEgresoRequisicionDetalles');
        $sinPrecio = $requisicion->tbEgresoRequisicionDetalles->filter(fn ($d) => $d->precio_unitario === null || (float) $d->precio_unitario <= 0);
        if ($requisicion->tbEgresoRequisicionDetalles->isEmpty() || $sinPrecio->isNotEmpty()) {
            return back()->with('error', 'Captura el precio unitario de todos los conceptos antes de validar la suficiencia.');
        }

        $resultado = DB::transaction(function () use ($requisicion) {
            // Bloquea la requisición para que dos validaciones simultáneas no comprometan el mismo saldo.
            EgresoRequisicion::whereKey($requisicion->id_tb_egreso_requisicion)->lockForUpdate()->first();

            $resultado = app(PresupuestoService::class)->validarRequisicion($requisicion);

            // Con suficiencia la requisición deja de estar "En Cotización": queda comprometida.
            $anterior = $requisicion->id_cat_egreso_requisicion_estado;
            $requisicion->update([
                'suficiencia' => $resultado['suficiente'],
                'suficiencia_fecha' => now(),
                'suficiencia_user_id' => auth()->id(),
                ...($resultado['suficiente'] ? ['id_cat_egreso_requisicion_estado' => EgresoRequisicion::COMPROMETIDA] : []),
            ]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $anterior,
                'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_tb_usuarios' => 1,
                'observaciones' => $resultado['suficiente']
                    ? 'Validada contra presupuesto: CON SUFICIENCIA por $'.number_format($resultado['total'], 2).'. Requisición comprometida.'
                    : 'Validada contra presupuesto: SIN SUFICIENCIA. Se requiere modificación presupuestal.',
            ]);

            return $resultado;
        });

        return back()->with($resultado['suficiente'] ? 'success' : 'error', $resultado['suficiente'] ? 'Con suficiencia presupuestal: la requisición queda comprometida.' : 'Sin suficiencia presupuestal.');
    }

    /**
     * "Ejercicio y control del gasto": por línea de presupuesto, lo asignado, las modificaciones
     * autorizadas, lo comprometido, en trámite, devengado, pagado y lo disponible.
     */
    public function gasto(Request $request)
    {
        [$filas, $unidadesDisponibles] = $this->lineasDelGasto($request);
        $hoy = now(SaldosMensuales::ZONA);
        $corrida = CorridaSaldo::with('usuario:id,name')->where('ejercicio', $hoy->year)->where('mes', $hoy->month)->first();

        return Inertia::render('requisiciones/Gasto', [
            'filas' => $filas,
            'unidadesDisponibles' => $unidadesDisponibles,
            'unidadSeleccionada' => $request->input('unidad'),
            'conMovimiento' => $request->boolean('con_movimiento'),
            'saldos' => [
                'mes' => SaldosMensuales::MESES[$hoy->month],
                'mesAnterior' => $hoy->month > 1 ? SaldosMensuales::MESES[$hoy->month - 1] : null,
                'corrida' => $corrida ? [
                    'fecha' => $corrida->created_at->toIso8601String(),
                    'origen' => $corrida->origen,
                    'usuario' => $corrida->usuario?->name,
                    'total' => $corrida->total_corrido,
                ] : null,
                'puedeCorrer' => ! $corrida && auth()->user()->can('presupuesto-correr-saldos'),
            ],
        ]);
    }

    /**
     * Detalle de una partida en el ejercicio del gasto: requisiciones que la usan (con su orden de
     * compra, CLC, proveedor, importe de la partida y fechas) y modificaciones presupuestales.
     */
    public function gastoDetalle(Request $request)
    {
        $clave = (string) $request->input('clave');
        $partes = explode('|', $clave);
        abort_unless(count($partes) === 4, 404);
        [$unidad, $fuente, $proyecto, $partida] = $partes;

        $user = auth()->user();
        $ids = $this->unidadesVisibles($user, PresupuestoService::clavesUnidadVisibles($user) === null);
        $idsUnidad = EgresoUnidadAdministrativa::whereRaw('TRIM(clave) = ?', [$unidad])
            ->when($ids !== null, fn ($q) => $q->whereIn('id_cat_egreso_unidad_administrativa', $ids))
            ->pluck('id_cat_egreso_unidad_administrativa');
        abort_if($idsUnidad->isEmpty(), 403, 'Esta partida no pertenece a tu unidad administrativa.');

        // Importe de la partida en cada requisición (una requisición puede usar varias partidas).
        $importes = DB::table('tb_egreso_requisicion_detalle as d')
            ->join('tb_egreso_requisicion as r', 'r.id_tb_egreso_requisicion', '=', 'd.id_tb_egreso_requisicion')
            ->whereIn('r.id_cat_egreso_unidad_administrativa', $idsUnidad)
            ->whereRaw('TRIM(d.fuente) = ?', [$fuente])
            ->whereRaw('TRIM(d.proyecto) = ?', [$proyecto])
            ->whereRaw('TRIM(d.partida) = ?', [$partida])
            ->groupBy('r.id_tb_egreso_requisicion')
            ->selectRaw('r.id_tb_egreso_requisicion as id, SUM(COALESCE(d.total, d.sub_total, 0)) as importe')
            ->pluck('importe', 'id');

        $requisiciones = EgresoRequisicion::with('catEgresoRequisicionEstado', 'ordenCompra.proveedor:id,nombre,rfc')
            ->whereIn('id_tb_egreso_requisicion', $importes->keys())
            ->orderByDesc('id_tb_egreso_requisicion')
            ->get()
            ->map(function (EgresoRequisicion $r) use ($importes) {
                $oc = $r->ordenCompra;
                $estado = (int) $r->id_cat_egreso_requisicion_estado;

                return [
                    'id' => $r->id_tb_egreso_requisicion,
                    'folio' => $r->folio_completo,
                    'fecha' => $r->registro,
                    'estado_id' => $estado,
                    'estado' => $r->catEgresoRequisicionEstado?->descripcion,
                    'momento' => match (true) {
                        in_array($estado, [4, EgresoRequisicion::CANCELADA], true) => null,
                        $oc && ! $oc->cancelada_at && ! $oc->clc_cancelada_at && $oc->pago_fecha => 'Pagado',
                        $oc && ! $oc->cancelada_at && $oc->xml_uuid => 'Devengado',
                        (bool) $r->suficiencia => 'Comprometido',
                        default => 'En trámite',
                    },
                    'importe' => round((float) $importes[$r->id_tb_egreso_requisicion], 2),
                    'suficiencia_fecha' => $r->suficiencia_fecha?->toIso8601String(),
                    'cancelada_at' => $r->cancelada_at?->toIso8601String(),
                    'orden' => $oc ? [
                        'id' => $oc->id,
                        'folio' => $oc->folio_completo,
                        'fecha' => $oc->fecha?->format('Y-m-d'),
                        'importe' => $oc->importe,
                        'proveedor' => $oc->proveedor?->nombre,
                        'factura' => $oc->xml_uuid ? (trim(($oc->xml_serie ?? '').' '.($oc->xml_folio ?? '')) ?: $oc->xml_uuid) : null,
                        'factura_fecha' => $oc->xml_fecha?->toIso8601String(),
                        'clc' => $oc->clc_folio_completo,
                        'clc_fecha' => $oc->clc_fecha?->format('Y-m-d'),
                        'clc_estado' => $oc->clc_folio_completo ? TramiteClc::descripcion($oc->clc_estado) : null,
                        'pago_fecha' => $oc->clc_cancelada_at ? null : $oc->pago_fecha?->format('Y-m-d'),
                        'cancelada' => $oc->cancelada_at !== null,
                    ] : null,
                ];
            })->values();

        $lado = fn ($q, string $l) => $q->where("{$l}_clave", $unidad)->where("{$l}_fuente", $fuente)
            ->where("{$l}_proyecto", $proyecto)->where("{$l}_partida", $partida);

        $modificaciones = ModificacionPresupuestal::query()
            ->where(fn ($q) => $q->where(fn ($q) => $lado($q, 'origen'))->orWhere(fn ($q) => $lado($q, 'destino')))
            ->orderByDesc('id')
            ->get()
            ->map(function (ModificacionPresupuestal $m) use ($clave) {
                $esOrigen = $m->claveLinea('origen') === $clave;
                $mes = $m->{$esOrigen ? 'origen_mes' : 'destino_mes'};

                return [
                    'id' => $m->id,
                    'folio' => $m->folio_completo,
                    'tipo' => ModificacionPresupuestal::TIPOS[$m->tipo] ?? $m->tipo,
                    'estado' => $m->estado,
                    'lado' => $esOrigen ? 'Emisor' : 'Receptor',
                    'contraparte' => $esOrigen ? $m->destino_descripcion : $m->origen_descripcion,
                    'mes' => $mes ? SaldosMensuales::MESES[$mes] : null,
                    // Con signo: lo que suma o resta a esta partida.
                    'importe' => round(($esOrigen ? -1 : 1) * $m->importe, 2),
                    'fecha' => $m->created_at?->toIso8601String(),
                    'resuelto_at' => $m->resuelto_at?->toIso8601String(),
                ];
            })->values();

        return response()->json(['requisiciones' => $requisiciones, 'modificaciones' => $modificaciones]);
    }

    /** La misma consulta de la pantalla (unidad, solo con movimiento y búsqueda) en un Excel. */
    public function exportarGasto(Request $request)
    {
        [$filas, $unidadesDisponibles] = $this->lineasDelGasto($request);

        $buscar = trim((string) $request->input('buscar'));
        $unidad = $request->input('unidad')
            ? $unidadesDisponibles->firstWhere('id_cat_egreso_unidad_administrativa', (int) $request->input('unidad'))?->nombre
            : null;

        $filtros = array_filter([
            $unidad ? "Unidad: {$unidad}" : 'Todas las unidades visibles',
            $request->boolean('con_movimiento') ? 'Solo partidas con movimiento' : null,
            $buscar !== '' ? "Búsqueda: {$buscar}" : null,
        ]);

        $libro = (new ExcelGasto)->libro($filas, $filtros);

        return response()->streamDownload(
            fn () => (new Xlsx($libro))->save('php://output'),
            'ejercicio_y_control_del_gasto_'.now('America/Mexico_City')->format('Ymd_Hi').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Líneas visibles para el usuario con los filtros de la pantalla del gasto.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function lineasDelGasto(Request $request): array
    {
        $user = auth()->user();
        // Admin y Control Presupuestal (autoriza modificaciones) ven todas las unidades, como en el presupuesto.
        $isAdmin = PresupuestoService::clavesUnidadVisibles($user) === null;

        $unidadesQuery = EgresoUnidadAdministrativa::where('activo', 1);
        $idsVisibles = $this->unidadesVisibles($user, $isAdmin);
        if ($idsVisibles !== null) {
            $unidadesQuery->whereIn('id_cat_egreso_unidad_administrativa', $idsVisibles);
        }
        $unidadesDisponibles = $unidadesQuery->orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'clave', 'nombre']);

        $unidadSeleccionada = $request->input('unidad');
        $unidades = $unidadSeleccionada
            ? $unidadesDisponibles->where('id_cat_egreso_unidad_administrativa', (int) $unidadSeleccionada)->values()
            : $unidadesDisponibles;

        $filas = app(PresupuestoService::class)->lineas($unidades->pluck('clave')->map(fn ($c) => trim((string) $c))->all());

        // Saldo corrido de meses anteriores y disponible del mes actual de cada línea.
        $hoy = now(SaldosMensuales::ZONA);
        $situacion = app(SaldosMensuales::class)->situacion($hoy->year, $hoy->month);
        $filas = $filas->map(fn ($l) => [
            ...$l,
            'saldo_corrido' => $situacion[$l['clave']]['saldo_corrido'] ?? 0.0,
            'disponible_mes' => $situacion[$l['clave']]['disponible_mes'] ?? 0.0,
        ]);

        if ($request->boolean('con_movimiento')) {
            $filas = $filas->filter(fn ($l) => $l['modificado'] != 0 || $l['comprometido'] != 0 || $l['devengado'] != 0 || $l['pagado'] != 0)->values();
        }

        // Misma búsqueda que la pantalla: todas las palabras deben aparecer en unidad, fuente, proyecto o partida.
        $terminos = array_filter(preg_split('/\s+/', mb_strtolower(trim((string) $request->input('buscar')))));

        if ($terminos !== []) {
            $filas = $filas->filter(function ($l) use ($terminos) {
                $texto = mb_strtolower("{$l['unidad_clave']} {$l['unidad']} {$l['fuente']} {$l['nombre_fuente']} {$l['proyecto']} {$l['nombre_proyecto']} {$l['partida']} {$l['nombre_partida']}");

                return collect($terminos)->every(fn ($t) => str_contains($texto, $t));
            })->values();
        }

        return [$filas, $unidadesDisponibles];
    }

    public function pdf(EgresoRequisicion $requisicion)
    {
        if (! in_array($requisicion->id_cat_egreso_requisicion_estado, [3, ...self::ESTADOS_RECURSOS_MATERIALES], true)) {
            // Una cancelada (12) tampoco se imprime.
            abort(403, 'El PDF solo está disponible para requisiciones validadas y no canceladas.');
        }
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoUnidadAdministrativa');
        $pdf = Pdf::loadView('requisiciones.pdf', compact('requisicion'))->setPaper('letter');

        // Pie: "Página N de M" y el folio (dompdf no conoce el total de páginas desde el CSS).
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $fuente = $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica');
        $canvas->page_text($canvas->get_width() - 185, $canvas->get_height() - 27, "Página {PAGE_NUM} de {PAGE_COUNT}     FUR {$requisicion->folio_completo}", $fuente, 7.5);

        return $pdf->stream("FUR-{$requisicion->folio_completo}.pdf");
    }
}
