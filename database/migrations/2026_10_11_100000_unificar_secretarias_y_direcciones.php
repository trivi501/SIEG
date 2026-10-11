<?php

use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secretarías y direcciones son un solo catálogo: cat_egreso_unidad_administrativa.
     * Cada usuario y cada vehículo pertenecen directamente a una dirección, y el prefijo de folio
     * de las requisiciones pasa a la dirección. La tabla `secretarias` y las columnas
     * `secretaria_id` se conservan (sin uso) porque las migraciones nunca borran información.
     *
     * Datos: un usuario o vehículo cuya secretaría tenía exactamente una dirección asignada pasa a
     * esa dirección (p. ej. los de CATASTRO a la Tesorería), y esa dirección hereda el prefijo.
     */
    public function up(): void
    {
        $hayUnidades = Schema::hasTable('cat_egreso_unidad_administrativa');

        foreach (['users', 'vehiculos'] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'id_cat_egreso_unidad_administrativa')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($hayUnidades) {
                $table->smallInteger('id_cat_egreso_unidad_administrativa')->nullable()->index();

                // La tabla de unidades es del sistema anterior: no existe en SQLite de pruebas.
                if ($hayUnidades) {
                    $table->foreign('id_cat_egreso_unidad_administrativa')
                        ->references('id_cat_egreso_unidad_administrativa')->on('cat_egreso_unidad_administrativa')
                        ->nullOnDelete();
                }
            });
        }

        if (! $hayUnidades) {
            return;
        }

        if (! Schema::hasColumn('cat_egreso_unidad_administrativa', 'prefijo')) {
            Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
                $table->string('prefijo', 10)->nullable();
            });
        }

        $unicaUnidad = DB::table('cat_egreso_unidad_administrativa')
            ->whereNotNull('secretaria_id')
            ->get(['id_cat_egreso_unidad_administrativa', 'secretaria_id'])
            ->groupBy('secretaria_id')
            ->filter(fn ($unidades) => $unidades->count() === 1)
            ->map(fn ($unidades) => $unidades->first()->id_cat_egreso_unidad_administrativa);

        foreach ($unicaUnidad as $secretariaId => $unidadId) {
            foreach (['users' => User::class, 'vehiculos' => Vehiculo::class] as $tabla => $modelo) {
                $ids = DB::table($tabla)
                    ->where('secretaria_id', $secretariaId)
                    ->whereNull('id_cat_egreso_unidad_administrativa')
                    ->pluck('id');

                DB::table($tabla)->whereIn('id', $ids)->update(['id_cat_egreso_unidad_administrativa' => $unidadId]);

                // Constancia en la bitácora de auditoría de cada registro que se movió.
                if (Schema::hasTable('auditoria')) {
                    DB::table('auditoria')->insert($ids->map(fn ($id) => [
                        'accion' => 'modificación',
                        'auditable_type' => $modelo,
                        'auditable_id' => (string) $id,
                        'descripcion' => 'Unificación de secretarías y direcciones (migración)',
                        'antes' => json_encode(['id_cat_egreso_unidad_administrativa' => null]),
                        'despues' => json_encode(['id_cat_egreso_unidad_administrativa' => $unidadId]),
                        'created_at' => now(),
                    ])->all());
                }
            }

            $prefijo = DB::table('secretarias')->where('id', $secretariaId)->value('prefijo');

            if ($prefijo) {
                DB::table('cat_egreso_unidad_administrativa')
                    ->where('id_cat_egreso_unidad_administrativa', $unidadId)
                    ->whereNull('prefijo')
                    ->update(['prefijo' => $prefijo]);
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'vehiculos'] as $tabla) {
            if (Schema::hasColumn($tabla, 'id_cat_egreso_unidad_administrativa')) {
                Schema::table($tabla, function (Blueprint $table) {
                    if (Schema::hasTable('cat_egreso_unidad_administrativa')) {
                        $table->dropForeign(['id_cat_egreso_unidad_administrativa']);
                    }
                    $table->dropIndex(['id_cat_egreso_unidad_administrativa']);
                    $table->dropColumn('id_cat_egreso_unidad_administrativa');
                });
            }
        }

        if (Schema::hasColumn('cat_egreso_unidad_administrativa', 'prefijo')) {
            Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
                $table->dropColumn('prefijo');
            });
        }
    }
};
