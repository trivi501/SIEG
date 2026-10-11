<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periodo (mes del ejercicio) del que sale el recurso (origen / emisor) y al que entra
     * (destino / receptor). Las modificaciones anteriores quedan sin periodo y cuentan en el mes
     * en que se autorizaron.
     */
    public function up(): void
    {
        if (! Schema::hasTable('modificaciones_presupuestales') || Schema::hasColumn('modificaciones_presupuestales', 'origen_mes')) {
            return;
        }

        Schema::table('modificaciones_presupuestales', function (Blueprint $table) {
            $table->unsignedTinyInteger('origen_mes')->nullable()->after('origen_descripcion');
            $table->unsignedTinyInteger('destino_mes')->nullable()->after('destino_descripcion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('modificaciones_presupuestales', 'origen_mes')) {
            return;
        }

        Schema::table('modificaciones_presupuestales', function (Blueprint $table) {
            $table->dropColumn(['origen_mes', 'destino_mes']);
        });
    }
};
