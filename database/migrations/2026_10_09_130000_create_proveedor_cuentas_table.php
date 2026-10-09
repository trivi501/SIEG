<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas bancarias de los proveedores (tabla nueva ligada a `proveedores`; la del sistema anterior,
     * cat_egreso_proveedor_banco, apunta a tb_egreso_proveedor, que ya no se usa) y la clave
     * de banco de 3 dígitos en cat_banco, que es con la que empieza la CLABE.
     */
    public function up(): void
    {
        $hayBancos = Schema::hasTable('cat_banco');

        if (! Schema::hasTable('proveedor_cuentas')) {
            Schema::create('proveedor_cuentas', function (Blueprint $table) use ($hayBancos) {
                $table->id();
                $table->foreignId('proveedor_id')->constrained('proveedores');
                $table->unsignedTinyInteger('id_cat_banco')->nullable()->index();
                $table->string('clabe', 18)->nullable()->unique();
                $table->string('cuenta', 20)->nullable();
                $table->string('sucursal', 100)->nullable();
                $table->boolean('principal')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                if ($hayBancos) {
                    $table->foreign('id_cat_banco')->references('id_cat_banco')->on('cat_banco');
                }
            });
        }

        if ($hayBancos && ! Schema::hasColumn('cat_banco', 'clave')) {
            Schema::table('cat_banco', function (Blueprint $table) {
                $table->string('clave', 3)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_cuentas');

        if (Schema::hasColumn('cat_banco', 'clave')) {
            Schema::table('cat_banco', function (Blueprint $table) {
                $table->dropUnique(['clave']);
                $table->dropColumn('clave');
            });
        }
    }
};
