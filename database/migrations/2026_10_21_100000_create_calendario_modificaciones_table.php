<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario de captura de modificaciones presupuestales: igual que el de requisiciones, el Admin define
 * para cada mes la fecha de inicio y la de fin en que las áreas capturan. Un mes sin registrar usa la
 * regla anterior (del día 1 hasta 3 días antes del fin de mes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendario_modificaciones')) {
            return;
        }

        Schema::create('calendario_modificaciones', function (Blueprint $table) {
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
        Schema::dropIfExists('calendario_modificaciones');
    }
};
