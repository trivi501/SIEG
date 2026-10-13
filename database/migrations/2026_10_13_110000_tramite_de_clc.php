<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trámite de la CLC entre áreas: área → Recursos Materiales → Control Presupuestal → Sindicatura →
     * Control Presupuestal → Egresos (pago). Cada envío es una relación (documento con las CLC que se
     * mandan) y cada paso queda en clc_movimientos.
     */
    public function up(): void
    {
        if (Schema::hasTable('ordenes_compra') && ! Schema::hasColumn('ordenes_compra', 'clc_estado')) {
            Schema::table('ordenes_compra', function (Blueprint $table) {
                $table->string('clc_estado', 30)->nullable()->index();
                $table->foreignId('clc_relacion_id')->nullable();
            });

            // Las CLC que ya existían: pagadas o, si no, en el área para enviarlas a Recursos Materiales.
            DB::table('ordenes_compra')->whereNotNull('clc_folio_completo')->whereNotNull('pago_fecha')->update(['clc_estado' => 'pagada']);
            DB::table('ordenes_compra')->whereNotNull('clc_folio_completo')->whereNull('pago_fecha')->update(['clc_estado' => 'generada']);
        }

        if (! Schema::hasTable('clc_relaciones')) {
            Schema::create('clc_relaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('folio');
                $table->unsignedSmallInteger('año');
                $table->string('folio_completo', 30)->unique();
                $table->string('origen', 30);
                $table->string('destino', 30);
                $table->text('observaciones')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clc_relacion_orden')) {
            Schema::create('clc_relacion_orden', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clc_relacion_id')->constrained('clc_relaciones');
                $table->foreignId('orden_compra_id')->constrained('ordenes_compra');
                $table->timestamp('recibida_at')->nullable();
                $table->foreignId('recibida_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unique(['clc_relacion_id', 'orden_compra_id']);
            });
        }

        if (! Schema::hasTable('clc_movimientos')) {
            Schema::create('clc_movimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_compra_id')->constrained('ordenes_compra');
                $table->string('accion', 30);
                $table->string('estado_anterior', 30)->nullable();
                $table->string('estado_nuevo', 30);
                $table->foreignId('clc_relacion_id')->nullable()->constrained('clc_relaciones')->nullOnDelete();
                $table->text('observaciones')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // El trámite de las CLC es historial: no se borra.
    }
};
