<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El controlador de requisiciones (store/update) ya guarda fuente/proyecto/partida por
     * concepto; estas columnas nunca llegaron a tb_egreso_requisicion_detalle en esta base.
     */
    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('tb_egreso_requisicion_detalle')) {
            return;
        }

        Schema::table('tb_egreso_requisicion_detalle', function (Blueprint $table) {
            $table->string('fuente', 200)->nullable()->after('descripcion');
            $table->string('proyecto', 200)->nullable()->after('fuente');
            $table->string('partida', 200)->nullable()->after('proyecto');
        });
    }

    public function down(): void
    {
        Schema::table('tb_egreso_requisicion_detalle', function (Blueprint $table) {
            $table->dropColumn(['fuente', 'proyecto', 'partida']);
        });
    }
};
