<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('presupuesto 2024')) {
            return;
        }

        Schema::table('presupuesto 2024', function (Blueprint $table) {
            $table->id('id_presupuesto')->first();
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto 2024', function (Blueprint $table) {
            $table->dropColumn('id_presupuesto');
        });
    }
};
