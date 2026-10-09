<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modificaciones presupuestales (traspaso, reducción, ampliación) sobre las líneas de
     * `presupuesto 2024`. Origen y destino se guardan por su clave (unidad|fuente|proyecto|partida)
     * y no por id_presupuesto, para que sobrevivan a una re-importación del presupuesto.
     */
    public function up(): void
    {
        if (Schema::hasTable('modificaciones_presupuestales')) {
            return;
        }

        Schema::create('modificaciones_presupuestales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('folio');
            $table->unsignedSmallInteger('año');
            $table->string('folio_completo', 30)->unique();
            $table->enum('tipo', ['traspaso', 'reduccion', 'ampliacion']);
            $table->enum('estado', ['pendiente', 'autorizada', 'rechazada'])->default('pendiente');

            foreach (['origen', 'destino'] as $lado) {
                $table->string("{$lado}_clave", 50)->nullable();
                $table->string("{$lado}_fuente", 50)->nullable();
                $table->string("{$lado}_proyecto", 50)->nullable();
                $table->string("{$lado}_partida", 200)->nullable();
                $table->string("{$lado}_descripcion", 500)->nullable();
            }

            $table->decimal('importe', 18, 2);
            $table->text('justificacion');
            $table->integer('id_tb_egreso_requisicion')->nullable();

            $table->foreignId('solicitante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('autorizador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelto_at')->nullable();
            $table->text('observaciones_resolucion')->nullable();
            // Saldos al momento de autorizar, para el historial.
            $table->decimal('origen_disponible_antes', 18, 2)->nullable();
            $table->decimal('destino_vigente_antes', 18, 2)->nullable();

            $table->timestamps();

            $table->index(['origen_clave', 'origen_fuente', 'origen_proyecto', 'origen_partida'], 'mod_pres_origen_idx');
            $table->index(['destino_clave', 'destino_fuente', 'destino_proyecto', 'destino_partida'], 'mod_pres_destino_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_presupuestales');
    }
};
