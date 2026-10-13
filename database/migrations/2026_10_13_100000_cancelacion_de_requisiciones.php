<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cancelación de requisiciones: estado 12 "Cancelada" con el motivo, quién y cuándo. Una cancelada
     * deja de contar en el presupuesto (en trámite o comprometido).
     */
    public function up(): void
    {
        if (Schema::hasTable('cat_egreso_requisicion_estado')
            && ! DB::table('cat_egreso_requisicion_estado')->where('id_cat_egreso_requisicion_estado', 12)->exists()) {
            DB::table('cat_egreso_requisicion_estado')->insert([
                'id_cat_egreso_requisicion_estado' => 12,
                'descripcion' => 'Cancelada',
                'activo' => 1,
            ]);
        }

        if (Schema::hasTable('tb_egreso_requisicion') && ! Schema::hasColumn('tb_egreso_requisicion', 'cancelada_at')) {
            Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
                $table->dateTime('cancelada_at')->nullable();
                $table->unsignedBigInteger('cancelada_user_id')->nullable();
                $table->string('motivo_cancelacion', 1000)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Las cancelaciones son historial: no se borran.
    }
};
