<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estructura orgánica: Secretaría → Dirección (cat_egreso_unidad_administrativa) → Departamento,
     * y los firmantes que aparecen en los PDF por tipo de documento.
     */
    public function up(): void
    {
        $hayUnidades = Schema::hasTable('cat_egreso_unidad_administrativa');

        if (! Schema::hasTable('departamentos')) {
            Schema::create('departamentos', function (Blueprint $table) use ($hayUnidades) {
                $table->id();
                $table->smallInteger('id_cat_egreso_unidad_administrativa')->index();
                $table->string('clave', 20)->nullable();
                $table->string('nombre', 300);
                $table->string('responsable', 200)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                // La tabla de unidades es del sistema anterior: no existe en SQLite de pruebas.
                if ($hayUnidades) {
                    $table->foreign('id_cat_egreso_unidad_administrativa')
                        ->references('id_cat_egreso_unidad_administrativa')->on('cat_egreso_unidad_administrativa');
                }
            });
        }

        if (! Schema::hasTable('firmantes')) {
            Schema::create('firmantes', function (Blueprint $table) use ($hayUnidades) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipos_documento');
                // elabora, revisa, autoriza, vobo
                $table->string('rol', 20);
                // Nulo = aplica a todas las unidades; con valor tiene prioridad para esa unidad.
                $table->smallInteger('id_cat_egreso_unidad_administrativa')->nullable()->index();
                $table->string('nombre', 200);
                $table->string('cargo', 200);
                $table->date('vigente_desde');
                $table->date('vigente_hasta')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['tipo_documento_id', 'rol']);

                if ($hayUnidades) {
                    $table->foreign('id_cat_egreso_unidad_administrativa')
                        ->references('id_cat_egreso_unidad_administrativa')->on('cat_egreso_unidad_administrativa');
                }
            });
        }

        // Responsable del centro gestor (la unidad administrativa).
        if ($hayUnidades && ! Schema::hasColumn('cat_egreso_unidad_administrativa', 'responsable')) {
            Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
                $table->string('responsable', 200)->nullable();
                $table->string('cargo_responsable', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firmantes');
        Schema::dropIfExists('departamentos');

        if (Schema::hasColumn('cat_egreso_unidad_administrativa', 'responsable')) {
            Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
                $table->dropColumn(['responsable', 'cargo_responsable']);
            });
        }
    }
};
