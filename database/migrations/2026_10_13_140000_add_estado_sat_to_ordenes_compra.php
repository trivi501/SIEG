<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de la factura (CFDI) en el SAT: Vigente, Cancelado o No Encontrado, el estatus de cancelación,
 * si el emisor está en la lista de EFOS y cuándo se verificó por última vez. Las facturas ya cargadas
 * quedan sin verificar hasta que se consulten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'sat_estado')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('sat_estado', 20)->nullable()->after('xml_user_id');
            $table->string('sat_estatus_cancelacion', 100)->nullable()->after('sat_estado');
            $table->boolean('sat_efos')->nullable()->after('sat_estatus_cancelacion');
            $table->timestamp('sat_verificado_at')->nullable()->after('sat_efos');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'sat_estado')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['sat_estado', 'sat_estatus_cancelacion', 'sat_efos', 'sat_verificado_at']);
        });
    }
};
