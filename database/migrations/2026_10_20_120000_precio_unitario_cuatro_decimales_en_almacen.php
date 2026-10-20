<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La cotización guarda el precio unitario con 4 decimales; la entrada de almacén lo copia igual.
 * Ampliar decimales no pierde información.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('almacen_entrada_detalles')) {
            return;
        }

        Schema::table('almacen_entrada_detalles', function (Blueprint $table) {
            $table->decimal('precio_unitario', 18, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Sin reversa: regresar a 2 decimales recortaría precios.
    }
};
