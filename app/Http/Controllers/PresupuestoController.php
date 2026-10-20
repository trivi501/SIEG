<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\EgresoFuenteFinanciamiento;
use App\Models\EgresoObjetoGasto;
use App\Models\EgresoProyecto;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\PresupuestoEgreso;
use App\Services\DistribucionMensual;
use App\Services\PresupuestoService;
use App\Services\SaldosMensuales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PresupuestoController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->hasRole(['Super Admin', 'Admin']), 403);
    }

    public function index(Request $request)
    {
        // Consulta: permiso presupuesto-index. Cada área ve solo el presupuesto de su unidad (solo lectura);
        // Admin y Control Presupuestal, todo. Alta, edición, importación y borrado llevan su propio permiso.
        $visibles = PresupuestoService::clavesUnidadVisibles(auth()->user());
        $query = PresupuestoEgreso::query()->when($visibles !== null, fn ($q) => $q->whereIn(DB::raw('TRIM(CLAVE)'), $visibles ?: ['__ninguna__']));

        $filters = $request->only(['CLAVE', 'UNIDAD_ADMINISTRATIVA', 'CLAVE3', 'FUENTE_DE_FINANCIAMIENTO', 'CLAVE6', 'PROYECTO', 'PARTIDA', 'capitulo', 'TIPO_DE_GASTO', 'NOMBRE_PARTIDA']);

        foreach ($filters as $column => $value) {
            if ($value !== null && $value !== '') {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        // Líneas cuya distribución mensual no suma el importe total.
        $sumaMeses = collect(array_keys(DistribucionMensual::MESES))->map(fn ($m) => "COALESCE(`{$m}`, 0)")->implode(' + ');

        if ($descuadradas = $request->boolean('descuadradas')) {
            $query->whereRaw("ABS(COALESCE(`IMPORTE_TOTAL`, 0) - ({$sumaMeses})) >= 0.005");
        }

        // Modificado, vigente y momentos del gasto de cada línea (comprometido, devengado, pagado), por clave de línea.
        $momentos = app(PresupuestoService::class)->lineas()->keyBy('clave');

        // Vigente al mes de la consulta: aprobado de enero al mes actual ± modificaciones autorizadas hasta ese mes.
        $hoy = now(SaldosMensuales::ZONA);
        $movimientos = app(SaldosMensuales::class)->movimientos($hoy->year);
        $vigenteAlMes = fn (string $clave) => round(collect($movimientos[$clave] ?? [])
            ->filter(fn ($m, $mes) => $mes <= $hoy->month)->sum(fn ($m) => $m['asignado'] + $m['modificado']), 2);

        return Inertia::render('presupuesto/index', [
            'presupuestos' => $query->paginate(50)->withQueryString()->through(function (PresupuestoEgreso $linea) use ($momentos, $vigenteAlMes) {
                $clave = PresupuestoService::clave($linea->CLAVE, $linea->CLAVE3, $linea->CLAVE6, $linea->PARTIDA);
                $saldos = $momentos->get($clave, []);

                return [
                    ...$linea->toArray(),
                    'diferencia' => DistribucionMensual::diferencia($linea->IMPORTE_TOTAL, $linea->getAttributes()) / 100,
                    'modificado' => $saldos['modificado'] ?? 0,
                    'vigente' => $saldos['vigente'] ?? (float) $linea->IMPORTE_TOTAL,
                    'vigente_mes' => $vigenteAlMes($clave),
                    'comprometido' => $saldos['comprometido'] ?? 0,
                    'devengado' => $saldos['devengado'] ?? 0,
                    'pagado' => $saldos['pagado'] ?? 0,
                ];
            }),
            'filters' => [...$filters, 'descuadradas' => $descuadradas ? '1' : ''],
            'mesActual' => $hoy->locale('es')->monthName,
            'totalDescuadradas' => PresupuestoEgreso::whereRaw("ABS(COALESCE(`IMPORTE_TOTAL`, 0) - ({$sumaMeses})) >= 0.005")->count(),
        ]);
    }

    /** Corrida manual de saldos del mes actual (la automática la hace el scheduler el día 1). */
    public function correrSaldos(SaldosMensuales $saldos)
    {
        $hoy = now(SaldosMensuales::ZONA);

        try {
            $corrida = $saldos->correr($hoy->year, $hoy->month, 'manual', auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf('Saldos corridos a %s: se sumaron $%s disponibles del mes anterior en %d líneas.',
            SaldosMensuales::MESES[$corrida->mes], number_format($corrida->total_corrido, 2), $corrida->lineas));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return Inertia::render('presupuesto/create', ['catalogos' => $this->catalogos()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $linea = PresupuestoEgreso::create($this->validarLinea($request));

        return redirect()->route('presupuesto', ['PARTIDA' => $linea->PARTIDA, 'CLAVE' => $linea->CLAVE])
            ->with('success', "Partida {$linea->PARTIDA} dada de alta por $".number_format($linea->IMPORTE_TOTAL, 2).'.');
    }

    public function edit(PresupuestoEgreso $presupuesto)
    {
        $this->authorizeAdmin();

        return Inertia::render('presupuesto/edit', [
            'presupuesto' => $presupuesto,
            'catalogos' => $this->catalogos(),
        ]);
    }

    public function update(Request $request, PresupuestoEgreso $presupuesto)
    {
        $this->authorizeAdmin();

        $presupuesto->update($this->validarLinea($request, $presupuesto));

        return redirect()->route('presupuesto')->with('success', 'Registro actualizado correctamente.');
    }

    /**
     * Opciones de los catálogos para capturar una línea: secretarías y direcciones (una por clave,
     * la del año más reciente), fuentes, proyectos y partidas activas.
     */
    private function catalogos(): array
    {
        $opciones = fn (string $modelo, array $columnas) => Schema::hasTable((new $modelo)->getTable())
            ? $modelo::where('activo', true)->orderBy('clave')->get($columnas)->map->only($columnas)->values()->all()
            : [];

        return [
            'unidades' => Schema::hasTable('cat_egreso_unidad_administrativa')
                ? EgresoUnidadAdministrativa::where('activo', true)->orderByDesc('año')->get(['clave', 'nombre'])
                    ->unique('clave')->sortBy('clave')->map->only(['clave', 'nombre'])->values()->all()
                : [],
            'fuentes' => $opciones(EgresoFuenteFinanciamiento::class, ['clave', 'nombre']),
            'proyectos' => $opciones(EgresoProyecto::class, ['clave', 'nombre']),
            'partidas' => $opciones(EgresoObjetoGasto::class, ['clave', 'nombre', 'tipo_gasto']),
        ];
    }

    /**
     * Valida una línea del presupuesto. Las claves deben existir en los catálogos (de ahí salen los
     * nombres, el capítulo y el tipo de gasto), no puede repetirse la combinación unidad + fuente +
     * proyecto + partida, y la distribución mensual debe sumar exactamente el importe total.
     */
    private function validarLinea(Request $request, ?PresupuestoEgreso $linea = null): array
    {
        $meses = array_keys(DistribucionMensual::MESES);

        $datos = $request->validate([
            'CLAVE' => 'required|string|max:50',
            'CLAVE3' => 'required|string|max:50',
            'CLAVE6' => 'required|string|max:50',
            'PARTIDA' => 'required|string|max:200',
            // Se puede dar de alta en 0 (la línea existe y recibe recursos después por modificaciones).
            'IMPORTE_TOTAL' => 'required|numeric|min:0|max:9999999999999',
            ...array_fill_keys($meses, 'nullable|numeric|min:0|max:9999999999999'),
        ], [
            'IMPORTE_TOTAL.min' => 'El importe total no puede ser negativo.',
        ], [
            'CLAVE' => 'secretaría o dirección', 'CLAVE3' => 'fuente de financiamiento',
            'CLAVE6' => 'proyecto', 'PARTIDA' => 'partida', 'IMPORTE_TOTAL' => 'importe total',
        ]);

        $datos = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $datos);
        $errores = [];

        $unidad = EgresoUnidadAdministrativa::where('clave', $datos['CLAVE'])->orderByDesc('año')->first();
        $fuente = EgresoFuenteFinanciamiento::where('clave', $datos['CLAVE3'])->first();
        $proyecto = EgresoProyecto::where('clave', $datos['CLAVE6'])->first();
        $partida = EgresoObjetoGasto::where('clave', $datos['PARTIDA'])->first();

        foreach (['CLAVE' => [$unidad, 'Secretarías y direcciones'], 'CLAVE3' => [$fuente, 'Fuentes de financiamiento'],
            'CLAVE6' => [$proyecto, 'Proyectos'], 'PARTIDA' => [$partida, 'Clasificador por Objeto del Gasto']] as $campo => [$registro, $catalogo]) {
            if (! $registro) {
                $errores[$campo] = "La clave {$datos[$campo]} no está en el catálogo de {$catalogo}.";
            }
        }

        $repetida = PresupuestoEgreso::where('CLAVE', $datos['CLAVE'])->where('CLAVE3', $datos['CLAVE3'])
            ->where('CLAVE6', $datos['CLAVE6'])->where('PARTIDA', $datos['PARTIDA'])
            ->when($linea, fn ($q) => $q->whereKeyNot($linea->getKey()))
            ->exists();

        if ($repetida) {
            $errores['PARTIDA'] = 'Ya existe una línea con la misma secretaría o dirección, fuente, proyecto y partida.';
        }

        if ($mensaje = DistribucionMensual::mensaje($datos['IMPORTE_TOTAL'], $datos)) {
            $errores['distribucion'] = $mensaje;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return [
            'CLAVE' => $datos['CLAVE'],
            'UNIDAD_ADMINISTRATIVA' => $unidad->nombre,
            'CLAVE3' => $datos['CLAVE3'],
            'FUENTE_DE_FINANCIAMIENTO' => $fuente->nombre,
            'CLAVE6' => $datos['CLAVE6'],
            'PROYECTO' => $proyecto->nombre,
            'PARTIDA' => $datos['PARTIDA'],
            'NOMBRE_PARTIDA' => $partida->nombre,
            'capitulo' => EgresoObjetoGasto::niveles($partida->clave)['capitulo'],
            'TIPO_DE_GASTO' => (string) $partida->tipo_gasto,
            'IMPORTE_TOTAL' => round((float) $datos['IMPORTE_TOTAL'], 2),
            ...collect($meses)->mapWithKeys(fn ($m) => [$m => round((float) ($datos[$m] ?? 0), 2)])->all(),
        ];
    }

    public function destroy()
    {
        $this->authorizeAdmin();

        // Uno por uno dentro de un lote: la bitácora conserva cada línea borrada (con sus importes).
        Auditoria::enLote('Eliminación de todo el presupuesto', fn () => DB::transaction(
            fn () => PresupuestoEgreso::query()->lazyById(500, 'id_presupuesto')->each(fn ($linea) => $linea->delete()),
        ));

        return redirect()->route('presupuesto')->with('success', 'Todos los registros fueron eliminados.');
    }

    public function import(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        $headers = array_shift($rows);

        $map = [
            'CLAVE' => 'CLAVE',
            'CLAVE UNIDAD ADMINISTRATIVA' => 'CLAVE',
            'UNIDAD ADMINISTRATIVA' => 'UNIDAD_ADMINISTRATIVA',
            'CLAVE FF' => 'CLAVE3',
            'CLAVE3' => 'CLAVE3',
            'FUENTE DE FINANCIAMIENTO' => 'FUENTE_DE_FINANCIAMIENTO',
            'FUENTES DE FINANCIAMIENTO' => 'FUENTE_DE_FINANCIAMIENTO',
            'CLAVE PROYECTO' => 'CLAVE6',
            'CLAVE6' => 'CLAVE6',
            'PROYECTO' => 'PROYECTO',
            'PARTIDA' => 'PARTIDA',
            'CAPITULO' => 'capitulo',
            'TIPO DE GASTO' => 'TIPO_DE_GASTO',
            'TIPO GASTO' => 'TIPO_DE_GASTO',
            'NOMBRE PARTIDA' => 'NOMBRE_PARTIDA',
            'NOMBRE DE PARTIDA' => 'NOMBRE_PARTIDA',
            'IMPORTE TOTAL' => 'IMPORTE_TOTAL',
            'IMPORT_TOTAL' => 'IMPORTE_TOTAL',
            'ENERO' => 'ENERO',
            'FEBRERO' => 'FEBRERO',
            'MARZO' => 'MARZO',
            'ABRIL' => 'ABRIL',
            'MAYO' => 'MAYO',
            'JUNIO' => 'JUNIO',
            'JULIO' => 'JULIO',
            'AGOSTO' => 'AGOSTO',
            'SEP' => 'SEP',
            'SEPTIEMBRE' => 'SEP',
            'SEPT' => 'SEP',
            'OCTUBRE' => 'OCTUBRE',
            'NOV' => 'NOV',
            'NOVIEMBRE' => 'NOV',
            'DIC' => 'DIC',
            'DICIEMBRE' => 'DIC',
        ];

        $mappedHeaders = [];
        foreach ($headers as $h) {
            $normalized = preg_replace('/^[\xEF\xBB\xBF]+/', '', $h);
            $normalized = trim(mb_strtoupper($normalized));
            $normalized = preg_replace('/[[:space:]]+/', ' ', $normalized);
            $normalized = str_replace(["\xC2\xA0", "\xA0"], ' ', $normalized);
            $col = $map[$normalized] ?? null;

            if ($col === null) {
                $noSpace = str_replace(' ', '', $normalized);
                foreach ($map as $key => $val) {
                    $keyNoSpace = str_replace(' ', '', preg_replace('/\s+/', ' ', $key));
                    if ($keyNoSpace === $noSpace) {
                        $col = $val;
                        break;
                    }
                }
            }

            if ($col === null) {
                $ordered = array_keys($map);
                usort($ordered, fn ($a, $b) => strlen($b) <=> strlen($a));
                foreach ($ordered as $key) {
                    if (mb_strpos($normalized, $key) !== false) {
                        $col = $map[$key];
                        break;
                    }
                }
            }

            $mappedHeaders[] = $col;
        }

        $numericFields = ['IMPORTE_TOTAL', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'];
        $claves = ['CLAVE3' => [], 'CLAVE6' => [], 'PARTIDA' => []];

        // Primera pasada: leer y revisar todos los renglones; no se guarda nada si alguno no cuadra.
        $lineas = [];
        $descuadres = [];

        foreach ($rows as $i => $row) {
            $data = [];
            foreach ($mappedHeaders as $col_i => $col) {
                if ($col === null || ! isset($row[$col_i])) {
                    continue;
                }
                $value = $row[$col_i];
                if (in_array($col, $numericFields)) {
                    if (is_string($value)) {
                        $value = preg_replace('/[^0-9.,\-]/', '', $value);
                        $value = str_replace(['$', ','], ['', ''], $value);
                    }
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    $value = round((float) $value, 2);
                }
                $data[$col] = $value;
            }
            if (empty(array_filter($data))) {
                continue;
            }

            // Si el archivo trae el importe total, la distribución mensual debe sumarlo exacto;
            // si no lo trae, el total es la suma de los meses.
            $sumaMeses = DistribucionMensual::sumaMeses($data) / 100;

            if (isset($data['IMPORTE_TOTAL']) && DistribucionMensual::diferencia($data['IMPORTE_TOTAL'], $data) !== 0) {
                $descuadres[] = sprintf('renglón %d (%s / %s): total $%s, meses $%s',
                    $i + 2, $data['CLAVE'] ?? '—', $data['PARTIDA'] ?? '—',
                    number_format($data['IMPORTE_TOTAL'], 2), number_format($sumaMeses, 2));
            }

            $data['IMPORTE_TOTAL'] = $data['IMPORTE_TOTAL'] ?? $sumaMeses;
            $lineas[] = $data;
        }

        if ($descuadres !== []) {
            return back()->with('error', 'No se importó nada: en '.count($descuadres).' renglones la distribución mensual no suma el importe total (ni de más ni de menos). '
                .implode('; ', array_slice($descuadres, 0, 10)).(count($descuadres) > 10 ? '; y '.(count($descuadres) - 10).' más.' : '.'));
        }

        // Segunda pasada: guardar. Todas las líneas importadas comparten un lote en la bitácora de auditoría.
        $archivo = $request->file('file')->getClientOriginalName();
        Auditoria::enLote("Importación del presupuesto ({$archivo})", fn () => DB::transaction(function () use ($lineas, &$claves) {
            foreach ($lineas as $data) {
                PresupuestoEgreso::create($data);

                foreach (array_keys($claves) as $col) {
                    if (isset($data[$col]) && trim((string) $data[$col]) !== '') {
                        $claves[$col][trim((string) $data[$col])] = true;
                    }
                }
            }
        }));

        $imported = count($lineas);
        $mensaje = "Se importaron $imported registros correctamente.";
        $faltantes = $this->clavesSinCatalogo($claves);

        if ($faltantes !== []) {
            $mensaje .= ' Aviso: hay claves que no están en los catálogos — '.implode('; ', $faltantes)
                .'. Agrégalas en Catálogos o corre php artisan catalogos:inicializar.';
        }

        return back()->with('success', $mensaje);
    }

    /** Fuentes, proyectos y partidas del archivo importado que no existen en su catálogo. */
    private function clavesSinCatalogo(array $claves): array
    {
        $catalogos = [
            'CLAVE3' => [EgresoFuenteFinanciamiento::class, 'fuentes'],
            'CLAVE6' => [EgresoProyecto::class, 'proyectos'],
            'PARTIDA' => [EgresoObjetoGasto::class, 'partidas'],
        ];
        $avisos = [];

        foreach ($catalogos as $col => [$modelo, $etiqueta]) {
            if ($claves[$col] === [] || ! Schema::hasTable((new $modelo)->getTable())) {
                continue;
            }

            $existentes = $modelo::whereIn('clave', array_keys($claves[$col]))->pluck('clave')->map(fn ($c) => (string) $c)->all();
            $faltan = array_values(array_diff(array_map('strval', array_keys($claves[$col])), $existentes));

            if ($faltan !== []) {
                $avisos[] = "{$etiqueta}: ".implode(', ', array_slice($faltan, 0, 5)).(count($faltan) > 5 ? ' y '.(count($faltan) - 5).' más' : '');
            }
        }

        return $avisos;
    }
}
