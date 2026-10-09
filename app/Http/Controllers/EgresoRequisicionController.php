<?php

namespace App\Http\Controllers;

use App\Models\EgresoRequisicion;
use App\Models\EgresoRequisicionDetalle;
use App\Models\EgresoRequisicionBitacora;
use App\Models\EgresoRequisicionEstado;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\EjercicioFiscal;
use App\Models\PresupuestoEgreso;
use App\Models\Proveedor;
use App\Models\ModificacionPresupuestal;
use App\Services\PresupuestoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class EgresoRequisicionController extends Controller
{
    /**
     * Ids de cat_egreso_unidad_administrativa visibles para el usuario: todas si es Admin,
     * solo las de su secretaría en caso contrario (null = la secretaría no tiene ninguna asignada).
     */
    private function unidadesVisibles($user, bool $isAdmin): ?array
    {
        if ($isAdmin) {
            return null;
        }

        if (!$user->secretaria_id) {
            return [];
        }

        return EgresoUnidadAdministrativa::where('secretaria_id', $user->secretaria_id)
            ->pluck('id_cat_egreso_unidad_administrativa')
            ->all();
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['Super Admin', 'Admin']);

        $query = EgresoRequisicion::with(['catEgresoRequisicionEstado', 'ordenCompra:id,id_tb_egreso_requisicion,folio_completo,xml_uuid']);

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

        return Inertia::render('requisiciones/Index', [
            'requisiciones' => $query->orderBy('id_tb_egreso_requisicion', 'desc')->paginate(15)->withQueryString(),
            'estados' => EgresoRequisicionEstado::all(['id_cat_egreso_requisicion_estado', 'descripcion']),
            'filters' => $request->only(['folio', 'folio_completo', 'concepto', 'estado', 'año']),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['Super Admin', 'Admin']);

        $unidadesQuery = EgresoUnidadAdministrativa::where('activo', 1);
        $idsVisibles = $this->unidadesVisibles($user, $isAdmin);
        if ($idsVisibles !== null) {
            $unidadesQuery->whereIn('id_cat_egreso_unidad_administrativa', $idsVisibles);
        }
        $unidades = $unidadesQuery->orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'clave', 'nombre']);
        $unidadDefault = !$isAdmin ? $unidades->first()?->id_cat_egreso_unidad_administrativa : null;

        return Inertia::render('requisiciones/Create', [
            'catalogs' => [
                'unidades' => $unidades,
                'lineas' => $this->lineasParaCaptura($unidades),
            ],
            'unidadDefault' => $unidadDefault,
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
            if (!isset($existentes[$clave])) {
                $errores["filas.{$i}.partida"] = 'Selecciona una fuente / proyecto / partida del presupuesto de la unidad (concepto '.($i + 1).').';
            }
        }

        if ($errores) {
            throw \Illuminate\Validation\ValidationException::withMessages($errores);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                'id_cat_egreso_unidad_administrativa' => 'required|integer',
                'concepto' => 'nullable|string',
                'fecha_tramite' => 'required|date',
                'incluye_iva' => 'boolean',
                'filas' => 'nullable|array',
                'filas.*.fuente' => 'nullable|string|max:200',
                'filas.*.proyecto' => 'nullable|string|max:200',
                'filas.*.partida' => 'nullable|string|max:200',
                'filas.*.cantidad' => 'nullable|numeric',
                'filas.*.descripcion' => 'nullable|string|max:3000',
            ]);

        $user = auth()->user();
        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
        if ($idsVisibles !== null && !in_array((int) $validated['id_cat_egreso_unidad_administrativa'], $idsVisibles, true)) {
            abort(403, 'Esa unidad administrativa no pertenece a tu secretaría.');
        }

        // El folio se numera por el año en curso: si ese ejercicio está cerrado no se admiten requisiciones.
        if (EjercicioFiscal::estaCerrado((int) date('Y'))) {
            throw ValidationException::withMessages(['fecha_tramite' => 'El ejercicio fiscal '.date('Y').' está cerrado; ya no admite requisiciones nuevas.']);
        }

        $unidad = EgresoUnidadAdministrativa::find($validated['id_cat_egreso_unidad_administrativa']);
        $this->validarLineasPresupuesto($validated['filas'] ?? [], $unidad);
        $prefijo = $unidad?->secretaria?->prefijo ?: 'REQ';

        $maxFolio = EgresoRequisicion::where('año', date('Y'))->max('folio') ?? 0;
        $nuevoFolio = $maxFolio + 1;
        $folioCompleto = $prefijo . '-' . str_pad($nuevoFolio, 4, '0', STR_PAD_LEFT);

        $newId = (EgresoRequisicion::max('id_tb_egreso_requisicion') ?? 0) + 1;

        [$filasCalculadas, $totales] = $this->calcularFilas($validated['filas'] ?? [], (bool) ($validated['incluye_iva'] ?? false));

        DB::transaction(function () use ($user, $validated, $newId, $folioCompleto, $nuevoFolio, $filasCalculadas, $totales) {
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
            'departamento' => null,
            'beneficiario' => null,
            'beneficiario_referencia' => null,
        ]);

        // Create detalle records from filas
        if (!empty($filasCalculadas)) {
            $detalleId = (EgresoRequisicionDetalle::max('id_tb_egreso_requisicion_detalle') ?? 0) + 1;
            foreach ($filasCalculadas as $fila) {
                if (empty($fila['descripcion'])) continue;
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
            'observaciones' => 'Requisición creada.',
        ]);
        });

        return redirect()->route('requisiciones.index')->with('success', 'Requisición creada.');
    }

    public function show(EgresoRequisicion $requisicion)
    {
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa', 'tbEgresoRequisicionBitacoras', 'ordenCompra.proveedor');

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
            'proveedores' => $user->can('ordenes-compra-create') && $requisicion->suficiencia === true && !$requisicion->ordenCompra
                ? Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'rfc'])
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

        $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
        if ($idsVisibles !== null && !in_array((int) $requisicion->id_cat_egreso_unidad_administrativa, $idsVisibles, true)) {
            abort(403, 'Esta requisición no pertenece a tu secretaría.');
        }
    }

    public function edit(EgresoRequisicion $requisicion)
    {
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa');

        if (!$this->puedeEditarse($requisicion)) {
            return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)
                ->with('error', 'Esta requisición ya no se puede modificar en su estado actual.');
        }

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
        if (!$this->puedeEditarse($requisicion)) {
            abort(403, 'Esta requisición ya no se puede modificar en su estado actual.');
        }

        $esCotizacion = $requisicion->id_cat_egreso_requisicion_estado === 6;

        if ($esCotizacion) {
            $validated = $request->validate([
                'fecha_tramite' => 'required|date',
                'incluye_iva' => 'boolean',
                'filas' => 'nullable|array',
                'filas.*.precio_unitario' => 'nullable|numeric',
            ]);

            // El IVA se decide aquí, en Recursos Materiales, al cotizar (no al crear la requisición).
            $ivaFactor = ($validated['incluye_iva'] ?? false) ? self::IVA_FACTOR : 0;
            EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)
                ->update(['iva_factor' => $ivaFactor]);

            if (!empty($validated['filas'])) {
                $detalles = EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->orderBy('id_tb_egreso_requisicion_detalle')->get();
                foreach ($validated['filas'] as $i => $fila) {
                    if (!isset($detalles[$i])) continue;
                    $precio_val = $fila['precio_unitario'] ?? '';
                    $precio = ($precio_val !== '' && is_numeric($precio_val)) ? (float) $precio_val : null;
                    $cant = (float) $detalles[$i]->cantidad;
                    $subTotal = $precio !== null ? round($precio * $cant, 4) : null;
                    $iva = $subTotal !== null ? round($subTotal * $ivaFactor, 4) : null;
                    $detalles[$i]->update([
                        'precio_unitario' => $precio,
                        'sub_total' => $subTotal,
                        'iva' => $iva,
                        'total' => $subTotal !== null ? round($subTotal + $iva, 4) : null,
                    ]);
                }
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
            $validated = $request->validate([
                'id_cat_egreso_unidad_administrativa' => 'required|integer',
                'concepto' => 'nullable|string',
                'fecha_tramite' => 'required|date',
                'incluye_iva' => 'boolean',
                'filas' => 'nullable|array',
                'filas.*.fuente' => 'nullable|string|max:200',
                'filas.*.proyecto' => 'nullable|string|max:200',
                'filas.*.partida' => 'nullable|string|max:200',
                'filas.*.cantidad' => 'nullable|numeric',
                'filas.*.descripcion' => 'nullable|string|max:3000',
            ]);

            $user = auth()->user();
            $idsVisibles = $this->unidadesVisibles($user, $user->hasRole(['Super Admin', 'Admin']));
            if ($idsVisibles !== null && !in_array((int) $validated['id_cat_egreso_unidad_administrativa'], $idsVisibles, true)) {
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

            EgresoRequisicionDetalle::where('id_tb_egreso_requisicion', $requisicion->id_tb_egreso_requisicion)->delete();

            if (!empty($filasCalculadas)) {
                $detalleId = (EgresoRequisicionDetalle::max('id_tb_egreso_requisicion_detalle') ?? 0) + 1;
                foreach ($filasCalculadas as $fila) {
                    if (empty($fila['descripcion'])) continue;
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
        $requisicion->delete();
        return redirect()->route('requisiciones.index')->with('success', 'Requisición eliminada.');
    }

    public function enviarRevision(EgresoRequisicion $requisicion)
    {
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

    public function aprobar(EgresoRequisicion $requisicion)
    {
        $estadoAnterior = $requisicion->id_cat_egreso_requisicion_estado;
        $requisicion->update(['id_cat_egreso_requisicion_estado' => 3]);

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $estadoAnterior,
            'id_cat_egreso_requisicion_estado_nuevo' => 3,
            'id_tb_usuarios' => 1,
            'observaciones' => 'Requisición aprobada.',
        ]);

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición aprobada.');
    }

    public function rechazar(EgresoRequisicion $requisicion)
    {
        $estadoAnterior = $requisicion->id_cat_egreso_requisicion_estado;
        $requisicion->update(['id_cat_egreso_requisicion_estado' => 4]);

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $estadoAnterior,
            'id_cat_egreso_requisicion_estado_nuevo' => 4,
            'id_tb_usuarios' => 1,
            'observaciones' => 'Requisición rechazada.',
        ]);

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición rechazada.');
    }

    public function enviarRecursosMateriales(EgresoRequisicion $requisicion)
    {
        $estadoAnterior = $requisicion->id_cat_egreso_requisicion_estado;
        $requisicion->update(['id_cat_egreso_requisicion_estado' => 5]);

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $estadoAnterior,
            'id_cat_egreso_requisicion_estado_nuevo' => 5,
            'id_tb_usuarios' => 1,
            'observaciones' => 'Enviado a Recursos Materiales. PDF firmado por el titular.',
        ]);

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Enviado a Recursos Materiales.');
    }

    public function recibido(EgresoRequisicion $requisicion)
    {
        $estadoAnterior = $requisicion->id_cat_egreso_requisicion_estado;
        $requisicion->update(['id_cat_egreso_requisicion_estado' => 6]);

        $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
        EgresoRequisicionBitacora::create([
            'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
            'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
            'id_cat_egreso_requisicion_estado_anterior' => $estadoAnterior,
            'id_cat_egreso_requisicion_estado_nuevo' => 6,
            'id_tb_usuarios' => 1,
            'observaciones' => 'Recibido en Recursos Materiales. Inicia proceso de cotización.',
        ]);

        return redirect()->route('requisiciones.show', $requisicion->id_tb_egreso_requisicion)->with('success', 'Requisición recibida. En cotización.');
    }

    public function recursosMateriales(Request $request)
    {
        // Recursos Materiales recibe de todas las secretarías: aquí no se filtra por unidad administrativa.
        $query = EgresoRequisicion::with(['catEgresoRequisicionEstado', 'catEgresoUnidadAdministrativa', 'ordenCompra:id,id_tb_egreso_requisicion,folio_completo,xml_uuid'])
            ->whereIn('id_cat_egreso_requisicion_estado', [5, 6]);

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
            'estados' => EgresoRequisicionEstado::all(['id_cat_egreso_requisicion_estado', 'descripcion']),
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

            $requisicion->update([
                'suficiencia' => $resultado['suficiente'],
                'suficiencia_fecha' => now(),
                'suficiencia_user_id' => auth()->id(),
            ]);

            $bitacoraId = (EgresoRequisicionBitacora::max('id_tb_egreso_requisicion_bitacora') ?? 0) + 1;
            EgresoRequisicionBitacora::create([
                'id_tb_egreso_requisicion_bitacora' => $bitacoraId,
                'id_tb_egreso_requisicion' => $requisicion->id_tb_egreso_requisicion,
                'id_cat_egreso_requisicion_estado_anterior' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_cat_egreso_requisicion_estado_nuevo' => $requisicion->id_cat_egreso_requisicion_estado,
                'id_tb_usuarios' => 1,
                'observaciones' => $resultado['suficiente']
                    ? 'Validada contra presupuesto: CON SUFICIENCIA por $'.number_format($resultado['total'], 2).'.'
                    : 'Validada contra presupuesto: SIN SUFICIENCIA. Se requiere modificación presupuestal.',
            ]);

            return $resultado;
        });

        return back()->with($resultado['suficiente'] ? 'success' : 'error', $resultado['suficiente'] ? 'Con suficiencia presupuestal.' : 'Sin suficiencia presupuestal.');
    }

    /**
     * "Cómo va el gasto": por línea de presupuesto, lo asignado, las modificaciones autorizadas,
     * lo comprometido (requisiciones con suficiencia), lo que está en trámite y lo disponible.
     */
    public function gasto(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['Super Admin', 'Admin']);

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

        if ($soloConMovimiento = $request->boolean('con_movimiento')) {
            $filas = $filas->filter(fn ($l) => $l['modificado'] != 0 || $l['comprometido'] != 0 || $l['en_tramite'] != 0)->values();
        }

        return Inertia::render('requisiciones/Gasto', [
            'filas' => $filas,
            'unidadesDisponibles' => $unidadesDisponibles,
            'unidadSeleccionada' => $unidadSeleccionada,
            'conMovimiento' => $soloConMovimiento,
        ]);
    }

    public function pdf(EgresoRequisicion $requisicion)
    {
        if (!in_array($requisicion->id_cat_egreso_requisicion_estado, [3, 5, 6], true)) {
            abort(403, 'El PDF solo está disponible para requisiciones validadas.');
        }
        $requisicion->load('tbEgresoRequisicionDetalles', 'catEgresoUnidadAdministrativa');
        $pdf = Pdf::loadView('requisiciones.pdf', compact('requisicion'));
        return $pdf->stream("FUR-{$requisicion->folio_completo}.pdf");
    }
}
