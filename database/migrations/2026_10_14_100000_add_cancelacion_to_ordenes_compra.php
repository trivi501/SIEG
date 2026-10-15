<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelación escalonada: la CLC y la orden de compra se cancelan (con motivo) de la última a la primera
 * antes de cancelar la requisición. Se conservan todos sus datos (factura, pago, folios) como historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'cancelada_at')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->timestamp('clc_cancelada_at')->nullable()->after('clc_relacion_id');
            $table->unsignedBigInteger('clc_cancelada_user_id')->nullable()->after('clc_cancelada_at');
            $table->string('clc_motivo_cancelacion', 1000)->nullable()->after('clc_cancelada_user_id');
            $table->timestamp('cancelada_at')->nullable()->after('clc_motivo_cancelacion');
            $table->unsignedBigInteger('cancelada_user_id')->nullable()->after('cancelada_at');
            $table->string('motivo_cancelacion', 1000)->nullable()->after('cancelada_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'cancelada_at')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['clc_cancelada_at', 'clc_cancelada_user_id', 'clc_motivo_cancelacion', 'cancelada_at', 'cancelada_user_id', 'motivo_cancelacion']);
        });
    }
};
