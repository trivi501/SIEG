<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de transferencia consecutivo por año (TRF-2026-0001): se asigna solo al registrar un pago por
 * transferencia y sirve para armar la posición bancaria. Los pagos anteriores quedan sin número.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'transferencia_numero')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->unsignedInteger('transferencia_folio')->nullable()->after('pago_referencia');
            $table->unsignedSmallInteger('transferencia_año')->nullable()->after('transferencia_folio');
            $table->string('transferencia_numero', 20)->nullable()->unique()->after('transferencia_año');
            $table->unique(['transferencia_año', 'transferencia_folio']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'transferencia_numero')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropUnique(['transferencia_año', 'transferencia_folio']);
            $table->dropUnique(['transferencia_numero']);
            $table->dropColumn(['transferencia_folio', 'transferencia_año', 'transferencia_numero']);
        });
    }
};
