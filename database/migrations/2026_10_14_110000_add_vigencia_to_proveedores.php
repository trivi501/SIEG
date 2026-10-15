<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vigencia del registro del proveedor en el padrón: con la vigencia vencida no se le asigna orden de
 * compra. Vacía = sin fecha de vencimiento (los proveedores actuales quedan así).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proveedores') || Schema::hasColumn('proveedores', 'vigencia')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->date('vigencia')->nullable()->after('cuenta_bancaria');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proveedores', 'vigencia')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('vigencia');
        });
    }
};
