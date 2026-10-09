<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Los programas presupuestarios del sistema anterior solo tienen nombre. */
    public function up(): void
    {
        if (! Schema::hasTable('cat_egreso_programa') || Schema::hasColumn('cat_egreso_programa', 'clave')) {
            return;
        }

        Schema::table('cat_egreso_programa', function (Blueprint $table) {
            $table->string('clave', 20)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cat_egreso_programa', 'clave')) {
            Schema::table('cat_egreso_programa', function (Blueprint $table) {
                $table->dropUnique(['clave']);
                $table->dropColumn('clave');
            });
        }
    }
};
