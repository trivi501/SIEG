<?php

namespace App\Console\Commands;

use App\Catalogos\Catalogos;
use App\Models\Auditoria;
use App\Models\EjercicioFiscal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Llena los catálogos de clasificación presupuestal con las claves que ya usa el presupuesto
 * importado, la lista de bancos y el ejercicio fiscal actual. Solo agrega lo que falta: nunca
 * modifica ni borra registros existentes, así que se puede correr varias veces.
 */
class InicializarCatalogos extends Command
{
    protected $signature = 'catalogos:inicializar {--pretend : Solo muestra cuántos registros se agregarían}';

    protected $description = 'Agrega a los catálogos las fuentes, proyectos y partidas del presupuesto, los bancos y el ejercicio actual';

    /** Bancos por clave de 3 dígitos (inicio de la CLABE). Se pueden completar desde la pantalla de Bancos. */
    private const BANCOS = [
        '002' => 'BANAMEX', '006' => 'BANCOMEXT', '009' => 'BANOBRAS', '012' => 'BBVA MÉXICO',
        '014' => 'SANTANDER', '019' => 'BANJERCITO', '021' => 'HSBC', '030' => 'BAJÍO',
        '036' => 'INBURSA', '042' => 'MIFEL', '044' => 'SCOTIABANK', '058' => 'BANREGIO',
        '059' => 'INVEX', '060' => 'BANSI', '062' => 'AFIRME', '072' => 'BANORTE',
        '127' => 'AZTECA', '130' => 'COMPARTAMOS', '132' => 'MULTIVA', '133' => 'ACTINVER',
        '135' => 'NAFIN', '136' => 'INTERCAM', '137' => 'BANCOPPEL', '145' => 'BBASE',
        '166' => 'BANCO DEL BIENESTAR',
    ];

    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');
        $presupuesto = Schema::hasTable('presupuesto 2024') ? DB::table('presupuesto 2024') : null;

        $tareas = [
            'fuentes-financiamiento' => $presupuesto ? (clone $presupuesto)
                ->whereNotNull('CLAVE3')->distinct()->get(['CLAVE3', 'FUENTE_DE_FINANCIAMIENTO'])
                ->map(fn ($r) => ['clave' => trim($r->CLAVE3), 'nombre' => mb_strtoupper(trim((string) $r->FUENTE_DE_FINANCIAMIENTO))]) : collect(),
            'proyectos' => $presupuesto ? (clone $presupuesto)
                ->whereNotNull('CLAVE6')->distinct()->get(['CLAVE6', 'PROYECTO'])
                ->map(fn ($r) => ['clave' => trim($r->CLAVE6), 'nombre' => mb_strtoupper(trim((string) $r->PROYECTO))]) : collect(),
            'objeto-gasto' => $presupuesto ? (clone $presupuesto)
                ->whereNotNull('PARTIDA')->distinct()->get(['PARTIDA', 'NOMBRE_PARTIDA', 'TIPO_DE_GASTO'])
                ->map(fn ($r) => [
                    'clave' => trim($r->PARTIDA),
                    'nombre' => mb_strtoupper(rtrim(trim((string) $r->NOMBRE_PARTIDA), '.')),
                    'tipo_gasto' => in_array((int) $r->TIPO_DE_GASTO, [1, 2, 3, 4, 5], true) ? (string) (int) $r->TIPO_DE_GASTO : '1',
                ]) : collect(),
            'bancos' => collect(self::BANCOS)->map(fn ($nombre, $clave) => ['clave' => (string) $clave, 'descripcion' => $nombre])->values(),
        ];

        Auditoria::enLote('Inicialización de catálogos (catalogos:inicializar)', function () use ($tareas, $pretend) {
            foreach ($tareas as $slug => $filas) {
                $catalogo = Catalogos::buscar($slug);

                if (! $catalogo->disponible()) {
                    $this->warn("{$catalogo->titulo}: la tabla no existe, se omite.");

                    continue;
                }

                $agregados = 0;

                foreach ($filas->unique('clave') as $fila) {
                    if ($fila['clave'] === '' || $catalogo->modelo::where('clave', $fila['clave'])->exists()) {
                        continue;
                    }

                    if (! $pretend) {
                        $catalogo->crear([...$fila, 'activo' => true]);
                    }

                    $agregados++;
                }

                $this->info(($pretend ? 'Se agregarían' : 'Agregados')." {$agregados} a {$catalogo->titulo}.");
            }

            if (Schema::hasTable('ejercicios_fiscales') && EjercicioFiscal::count() === 0) {
                $pretend || EjercicioFiscal::create(['año' => (int) date('Y'), 'estado' => 'abierto', 'activo' => true]);
                $this->info(($pretend ? 'Se agregaría' : 'Agregado').' el ejercicio fiscal '.date('Y').'.');
            }
        });

        return Command::SUCCESS;
    }
}
