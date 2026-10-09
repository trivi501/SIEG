<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de proveedores para las órdenes de compra. Se usa una tabla nueva porque
     * tb_egreso_proveedor del sistema anterior exige país/estado/municipio/banco/tipo con FKs
     * a catálogos que no se capturan aquí.
     */
    public function up(): void
    {
        if (Schema::hasTable('proveedores')) {
            return;
        }

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 300);
            $table->string('rfc', 13)->nullable()->unique();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('domicilio', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
