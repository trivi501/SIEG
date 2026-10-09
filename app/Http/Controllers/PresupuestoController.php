<?php

namespace App\Http\Controllers;

use App\Models\EgresoFuenteFinanciamiento;
use App\Models\EgresoObjetoGasto;
use App\Models\EgresoProyecto;
use App\Models\PresupuestoEgreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $this->authorizeAdmin();

        $query = PresupuestoEgreso::query();

        $filters = $request->only(['CLAVE', 'UNIDAD_ADMINISTRATIVA', 'CLAVE3', 'FUENTE_DE_FINANCIAMIENTO', 'CLAVE6', 'PROYECTO', 'PARTIDA', 'capitulo', 'TIPO_DE_GASTO', 'NOMBRE_PARTIDA']);

        foreach ($filters as $column => $value) {
            if ($value !== null && $value !== '') {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        return Inertia::render('presupuesto/index', [
            'presupuestos' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function edit(PresupuestoEgreso $presupuesto)
    {
        $this->authorizeAdmin();

        return Inertia::render('presupuesto/edit', [
            'presupuesto' => $presupuesto,
        ]);
    }

    public function update(Request $request, PresupuestoEgreso $presupuesto)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'CLAVE' => 'nullable|string|max:50',
            'UNIDAD_ADMINISTRATIVA' => 'nullable|string|max:200',
            'CLAVE3' => 'nullable|string|max:50',
            'FUENTE_DE_FINANCIAMIENTO' => 'nullable|string|max:200',
            'CLAVE6' => 'nullable|string|max:50',
            'PROYECTO' => 'nullable|string|max:200',
            'PARTIDA' => 'nullable|string|max:200',
            'capitulo' => 'nullable|string|max:50',
            'TIPO_DE_GASTO' => 'nullable|string|max:50',
            'NOMBRE_PARTIDA' => 'nullable|string|max:200',
            'ENERO' => 'nullable|numeric',
            'FEBRERO' => 'nullable|numeric',
            'MARZO' => 'nullable|numeric',
            'ABRIL' => 'nullable|numeric',
            'MAYO' => 'nullable|numeric',
            'JUNIO' => 'nullable|numeric',
            'JULIO' => 'nullable|numeric',
            'AGOSTO' => 'nullable|numeric',
            'SEP' => 'nullable|numeric',
            'OCTUBRE' => 'nullable|numeric',
            'NOV' => 'nullable|numeric',
            'DIC' => 'nullable|numeric',
        ]);

        $monthlyFields = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'];
        $total = 0;
        foreach ($monthlyFields as $field) {
            $total += (float) ($validated[$field] ?? 0);
        }
        $validated['IMPORTE_TOTAL'] = $total;

        $presupuesto->update($validated);

        return redirect()->route('presupuesto')->with('success', 'Registro actualizado correctamente.');
    }

    public function destroy()
    {
        $this->authorizeAdmin();

        PresupuestoEgreso::query()->delete();

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
                usort($ordered, fn($a, $b) => strlen($b) <=> strlen($a));
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
        $imported = 0;
        $claves = ['CLAVE3' => [], 'CLAVE6' => [], 'PARTIDA' => []];

        foreach ($rows as $row) {
            $data = [];
            foreach ($mappedHeaders as $i => $col) {
                if ($col === null || !isset($row[$i])) {
                    continue;
                }
                $value = $row[$i];
                if (in_array($col, $numericFields)) {
                    if (is_string($value)) {
                        $value = preg_replace('/[^0-9.,\-]/', '', $value);
                        $value = str_replace(['$', ','], ['', ''], $value);
                    }
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    $value = (float) $value;
                }
                $data[$col] = $value;
            }
            if (empty(array_filter($data))) {
                continue;
            }
            $monthlyFields = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'];
            $total = 0;
            foreach ($monthlyFields as $field) {
                $total += (float) ($data[$field] ?? 0);
            }
            $data['IMPORTE_TOTAL'] = $total;
            PresupuestoEgreso::create($data);
            $imported++;

            foreach (array_keys($claves) as $col) {
                if (isset($data[$col]) && trim((string) $data[$col]) !== '') {
                    $claves[$col][trim((string) $data[$col])] = true;
                }
            }
        }

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
