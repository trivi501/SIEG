<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calendario de requisiciones: periodo ordinario de captura por ejercicio (por defecto del 1 al 7
     * de cada mes). Lo capturado fuera del periodo queda extemporáneo y no avanza hasta que la
     * Subdirección Administrativa (Adquisiciones) lo autoriza; se guarda quién y cuándo.
     */
    public function up(): void
    {
        if (Schema::hasTable('ejercicios_fiscales') && ! Schema::hasColumn('ejercicios_fiscales', 'captura_dia_inicio')) {
            Schema::table('ejercicios_fiscales', function (Blueprint $table) {
                $table->unsignedTinyInteger('captura_dia_inicio')->default(1)->after('estado');
                $table->unsignedTinyInteger('captura_dia_fin')->default(7)->after('captura_dia_inicio');
            });
        }

        if (Schema::hasTable('tb_egreso_requisicion') && ! Schema::hasColumn('tb_egreso_requisicion', 'extemporanea')) {
            Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
                $table->boolean('extemporanea')->default(false);
                $table->unsignedBigInteger('extemporanea_user_id')->nullable();
                $table->dateTime('extemporanea_autorizada_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ejercicios_fiscales', 'captura_dia_inicio')) {
            Schema::table('ejercicios_fiscales', fn (Blueprint $table) => $table->dropColumn(['captura_dia_inicio', 'captura_dia_fin']));
        }

        // Las marcas de extemporánea de tb_egreso_requisicion son historial: no se borran.
    }
};
