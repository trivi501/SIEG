<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultado de validar la cotización contra el presupuesto: null = aún no se valida,
     * 1 = con suficiencia (el monto queda comprometido), 0 = sin suficiencia.
     */
    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('tb_egreso_requisicion')) {
            return;
        }

        if (Schema::hasColumn('tb_egreso_requisicion', 'suficiencia')) {
            return;
        }

        Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
            $table->boolean('suficiencia')->nullable()->after('monto_requisicion');
            $table->dateTime('suficiencia_fecha')->nullable()->after('suficiencia');
            $table->unsignedBigInteger('suficiencia_user_id')->nullable()->after('suficiencia_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
            $table->dropColumn(['suficiencia', 'suficiencia_fecha', 'suficiencia_user_id']);
        });
    }
};
