<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refacturas: el proveedor volvió a facturar. El Admin quita la factura vigente de la orden de compra (y
 * cancela su CLC si la tenía); al cargar el nuevo XML se genera una CLC nueva. Aquí queda el historial
 * de la factura y la CLC anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordenes_compra_refacturas') || ! Schema::hasTable('ordenes_compra')) {
            return;
        }

        Schema::create('ordenes_compra_refacturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra');
            $table->string('xml_uuid_anterior', 40)->nullable()->index();
            $table->string('xml_folio_anterior', 60)->nullable();
            $table->decimal('xml_total_anterior', 15, 2)->nullable();
            $table->string('xml_path_anterior', 300)->nullable();
            $table->string('clc_folio_anterior', 20)->nullable();
            $table->string('clc_estado_anterior', 30)->nullable();
            $table->string('clc_nueva', 20)->nullable();
            $table->string('motivo', 1000);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra_refacturas');
    }
};
