<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario de captura de requisiciones: el Admin define para cada mes la fecha de inicio y la de fin.
 * Fuera de ese periodo la requisición queda extemporánea. Un mes sin registrar usa los días del
 * ejercicio fiscal (por omisión, del 1 al 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendario_captura')) {
            return;
        }

        Schema::create('calendario_captura', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('año');
            $table->unsignedTinyInteger('mes');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['año', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_captura');
    }
};
