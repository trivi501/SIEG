<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('cat_egreso_unidad_administrativa')) {
            return;
        }

        Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
            $table->unsignedBigInteger('secretaria_id')->nullable()->after('id_cat_egreso_unidad_administrativa');
            $table->foreign('secretaria_id')->references('id')->on('secretarias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cat_egreso_unidad_administrativa', function (Blueprint $table) {
            $table->dropForeign(['secretaria_id']);
            $table->dropColumn('secretaria_id');
        });
    }
};
