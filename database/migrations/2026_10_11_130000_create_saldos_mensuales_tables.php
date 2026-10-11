<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrida de saldos: el día 1 de cada mes lo que quedó disponible el mes anterior en cada línea
     * del presupuesto se suma al mes actual como "saldo corrido". Se guarda aparte (no se toca el
     * calendario autorizado de `presupuesto 2024`) y queda congelado al momento de correrlo.
     */
    public function up(): void
    {
        if (! Schema::hasTable('corridas_saldo')) {
            Schema::create('corridas_saldo', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('ejercicio');
                $table->unsignedTinyInteger('mes');
                // automatica (programada el día 1) o manual (botón)
                $table->string('origen', 12);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('lineas')->default(0);
                $table->decimal('total_corrido', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['ejercicio', 'mes']);
            });
        }

        if (! Schema::hasTable('saldos_mensuales')) {
            Schema::create('saldos_mensuales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('corrida_saldo_id')->constrained('corridas_saldo')->cascadeOnDelete();
                $table->unsignedSmallInteger('ejercicio');
                $table->unsignedTinyInteger('mes');
                // Clave de línea: unidad|fuente|proyecto|partida (PresupuestoService::clave)
                $table->string('clave', 150);
                // Disponible que quedó en el mes anterior y se suma a este mes.
                $table->decimal('saldo_anterior', 15, 2)->default(0);
                // Movimientos del mes anterior con los que se calculó (para poder revisarlo).
                $table->decimal('asignado_mes_anterior', 15, 2)->default(0);
                $table->decimal('modificado_mes_anterior', 15, 2)->default(0);
                $table->decimal('comprometido_mes_anterior', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['ejercicio', 'mes', 'clave']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_mensuales');
        Schema::dropIfExists('corridas_saldo');
    }
};
