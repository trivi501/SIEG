<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('tb_egreso_requisicion')) {
            return;
        }

        Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user_laravel')->nullable()->after('id_tb_usuarios');
        });
    }

    public function down(): void
    {
        Schema::table('tb_egreso_requisicion', function (Blueprint $table) {
            $table->dropColumn('id_user_laravel');
        });
    }
};
