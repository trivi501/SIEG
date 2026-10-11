<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta bancaria del proveedor capturada en el mismo catálogo de proveedores (la que se usa para
     * pagarle y se imprime en la CLC). Las cuentas adicionales siguen en proveedor_cuentas.
     */
    public function up(): void
    {
        if (! Schema::hasTable('proveedores') || Schema::hasColumn('proveedores', 'clabe')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            // cat_banco es del sistema anterior: sin llave foránea.
            $table->unsignedInteger('id_cat_banco')->nullable()->after('domicilio');
            $table->string('clabe', 18)->nullable()->unique()->after('id_cat_banco');
            $table->string('cuenta_bancaria', 20)->nullable()->after('clabe');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proveedores', 'clabe')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropUnique(['clabe']);
            $table->dropColumn(['id_cat_banco', 'clabe', 'cuenta_bancaria']);
        });
    }
};
