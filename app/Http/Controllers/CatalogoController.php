<?php

namespace App\Http\Controllers;

use App\Catalogos\Campo;
use App\Catalogos\Catalogo;
use App\Catalogos\Catalogos;
use App\Catalogos\Importador;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pantalla, alta, edición, baja lógica, historial e importación de todos los catálogos
 * definidos en App\Catalogos\Catalogos. Los permisos los revisa el middleware `catalogo:<accion>`.
 */
class CatalogoController extends Controller
{
    public function inicio(Request $request)
    {
        $user = $request->user();

        $catalogos = collect(Catalogos::todos())
            ->filter(fn (Catalogo $c) => $c->disponible() && $user->checkPermissionTo("{$c->prefijoPermiso()}-index"))
            ->map(fn (Catalogo $c) => [
                'slug' => $c->slug,
                'titulo' => $c->titulo,
                'grupo' => $c->grupo,
                'descripcion' => $c->descripcion,
                'total' => $c->modelo::query()->count(),
                'activos' => $c->tieneActivo() ? $c->modelo::query()->where('activo', true)->count() : null,
            ])
            ->values();

        return Inertia::render('catalogos/Index', [
            'grupos' => Catalogos::GRUPOS,
            'catalogos' => $catalogos,
            'secretarias' => $user->checkPermissionTo('secretarias-index'),
        ]);
    }

    public function index(Request $request, Catalogo $catalogo)
    {
        $user = $request->user();
        $prefijo = $catalogo->prefijoPermiso();

        $opcionesRelacion = collect($catalogo->campos)
            ->filter(fn (Campo $c) => $c->tipo === 'relacion')
            ->mapWithKeys(fn (Campo $c) => [$c->nombre => $c->opcionesRelacion()])
            ->all();

        $query = $catalogo->query();

        if ($buscar = trim((string) $request->input('buscar'))) {
            $query->where(function ($q) use ($catalogo, $buscar) {
                foreach ($catalogo->buscar as $columna) {
                    if (str_contains($columna, '.')) {
                        [$relacion, $col] = explode('.', $columna, 2);
                        $q->orWhereHas($relacion, fn ($r) => $r->where($col, 'like', "%{$buscar}%"));
                    } else {
                        $q->orWhere($columna, 'like', "%{$buscar}%");
                    }
                }
            });
        }

        $estado = $request->input('estado');

        if ($catalogo->tieneActivo() && in_array($estado, ['activos', 'inactivos'], true)) {
            $query->where('activo', $estado === 'activos');
        }

        $registros = $query->paginate(25)->withQueryString()->through(fn ($registro) => [
            'id' => $registro->getKey(),
            'activo' => $catalogo->tieneActivo() ? (bool) $registro->activo : true,
            'valores' => collect($catalogo->camposEditables())
                ->mapWithKeys(fn (Campo $c) => [$c->nombre => $catalogo->valorDe($registro, $c)])
                ->all(),
            'textos' => collect($catalogo->campos)
                ->filter(fn (Campo $c) => $c->enLista)
                ->mapWithKeys(fn (Campo $c) => [$c->nombre => $catalogo->textoDe($registro, $c, $opcionesRelacion)])
                ->all(),
        ]);

        return Inertia::render('catalogos/Catalogo', [
            'catalogo' => [
                'slug' => $catalogo->slug,
                'titulo' => $catalogo->titulo,
                'singular' => $catalogo->singular,
                'grupo' => $catalogo->grupo,
                'descripcion' => $catalogo->descripcion,
                'tieneActivo' => $catalogo->tieneActivo(),
                'campos' => collect($catalogo->campos)
                    ->map(fn (Campo $c) => $c->paraFrontend($opcionesRelacion[$c->nombre] ?? null))
                    ->all(),
                'puede' => [
                    'crear' => $user->checkPermissionTo("{$prefijo}-create"),
                    'editar' => $user->checkPermissionTo("{$prefijo}-edit"),
                    'baja' => $user->checkPermissionTo("{$prefijo}-delete"),
                    'importar' => $user->checkPermissionTo("{$prefijo}-import"),
                ],
            ],
            'registros' => $registros,
            'filtros' => ['buscar' => $buscar ?: null, 'estado' => $estado],
            'importacion' => fn () => $this->resumenImportacion($request, $catalogo),
        ]);
    }

    public function store(Request $request, Catalogo $catalogo)
    {
        $datos = $catalogo->validar($catalogo->normalizar($request->all()));
        $catalogo->crear($datos);

        return back()->with('success', Str::ucfirst($catalogo->singular).' registrado.');
    }

    public function update(Request $request, Catalogo $catalogo, string $registro)
    {
        $modelo = $catalogo->buscarRegistro($registro);
        $datos = $catalogo->validar($catalogo->normalizar($request->all()), $modelo);
        $catalogo->actualizar($modelo, $datos);

        return back()->with('success', Str::ucfirst($catalogo->singular).' actualizado.');
    }

    /** Baja o reactivación lógica: los catálogos nunca se borran (hay documentos que los referencian). */
    public function estado(Request $request, Catalogo $catalogo, string $registro)
    {
        abort_unless($catalogo->tieneActivo(), 404);

        $activo = $request->boolean('activo');
        $catalogo->buscarRegistro($registro)->update(['activo' => $activo]);

        return back()->with('success', Str::ucfirst($catalogo->singular).($activo ? ' reactivado.' : ' dado de baja.'));
    }

    public function historial(Catalogo $catalogo, string $registro)
    {
        $modelo = $catalogo->buscarRegistro($registro);

        return response()->json(
            Auditoria::with('user:id,name')
                ->where('auditable_type', $modelo::class)
                ->where('auditable_id', (string) $modelo->getKey())
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (Auditoria $a) => [
                    'id' => $a->id,
                    'fecha' => $a->created_at?->toIso8601String(),
                    'usuario' => $a->user?->name,
                    'accion' => $a->accion,
                    'descripcion' => $a->descripcion,
                    'antes' => $a->antes,
                    'despues' => $a->despues,
                ]),
        );
    }

    public function plantilla(Catalogo $catalogo)
    {
        $libro = (new Importador($catalogo))->plantilla();

        return response()->streamDownload(
            fn () => (new Xlsx($libro))->save('php://output'),
            "plantilla_{$catalogo->slug}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** Paso 1: analiza el archivo y guarda el resultado 30 minutos para confirmarlo. */
    public function previa(Request $request, Catalogo $catalogo)
    {
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240']);

        $resultado = (new Importador($catalogo))->analizar($request->file('archivo'));
        $token = (string) Str::uuid();

        Cache::put($this->llaveCache($request, $token), [
            'catalogo' => $catalogo->slug,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            ...$resultado,
        ], now()->addMinutes(30));

        return back()->with('importacion', $token);
    }

    /** Paso 2: guarda los renglones analizados; solo si el archivo no tuvo errores. */
    public function importar(Request $request, Catalogo $catalogo)
    {
        $request->validate(['token' => 'required|uuid']);

        $llave = $this->llaveCache($request, $request->input('token'));
        $analisis = Cache::get($llave);

        if (! $analisis || $analisis['catalogo'] !== $catalogo->slug) {
            return back()->with('error', 'La vista previa expiró; vuelve a subir el archivo.');
        }

        if ($analisis['errores'] !== [] || $analisis['faltantes'] !== []) {
            return back()->with('error', 'Corrige los errores del archivo antes de importarlo.');
        }

        $resultado = (new Importador($catalogo))->aplicar($analisis['filas'], $analisis['archivo']);
        Cache::forget($llave);

        return back()->with('success', "Importación terminada: {$resultado['altas']} altas y {$resultado['modificaciones']} modificaciones.");
    }

    private function llaveCache(Request $request, string $token): string
    {
        return "importacion:{$request->user()->id}:{$token}";
    }

    private function resumenImportacion(Request $request, Catalogo $catalogo): ?array
    {
        $token = $request->session()->get('importacion');
        $analisis = $token ? Cache::get($this->llaveCache($request, $token)) : null;

        if (! $analisis || $analisis['catalogo'] !== $catalogo->slug) {
            return null;
        }

        $conteo = collect($analisis['filas'])->countBy('accion');

        return [
            'token' => $token,
            'archivo' => $analisis['archivo'],
            'altas' => $conteo->get('alta', 0),
            'modificaciones' => $conteo->get('modificacion', 0),
            'sinCambios' => $conteo->get('sin_cambios', 0),
            'errores' => array_slice($analisis['errores'], 0, 200),
            'totalErrores' => count($analisis['errores']),
            'faltantes' => $analisis['faltantes'],
            'columnasIgnoradas' => $analisis['columnasIgnoradas'],
        ];
    }
}
