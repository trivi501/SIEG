<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro del pago de una orden de compra (momento contable "pagado"). Solo se registra cuando
     * la orden ya tiene su factura XML (momento "devengado").
     */
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'pago_fecha')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->date('pago_fecha')->nullable();
            // transferencia, cheque
            $table->string('pago_forma', 20)->nullable();
            $table->string('pago_referencia', 100)->nullable();
            $table->foreignId('pago_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pago_registrado_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'pago_fecha')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropForeign(['pago_user_id']);
            $table->dropColumn(['pago_fecha', 'pago_forma', 'pago_referencia', 'pago_user_id', 'pago_registrado_at']);
        });
    }
};
