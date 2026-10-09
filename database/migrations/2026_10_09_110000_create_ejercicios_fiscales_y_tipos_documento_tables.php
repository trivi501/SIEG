<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ejercicios_fiscales')) {
            Schema::create('ejercicios_fiscales', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('año')->unique();
                $table->string('descripcion', 200)->nullable();
                // abierto: admite requisiciones nuevas; cerrado: solo consulta.
                $table->string('estado', 10)->default('abierto');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tipos_documento')) {
            Schema::create('tipos_documento', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 40)->unique();
                $table->string('nombre', 150);
                $table->string('prefijo', 10)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            // Los documentos que hoy imprime el sistema; los firmantes se configuran por tipo.
            DB::table('tipos_documento')->insert([
                ['clave' => 'requisicion', 'nombre' => 'Requisición', 'prefijo' => null, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
                ['clave' => 'orden_compra', 'nombre' => 'Orden de compra', 'prefijo' => 'OC', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
                ['clave' => 'modificacion_presupuestal', 'nombre' => 'Modificación presupuestal', 'prefijo' => null, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
        Schema::dropIfExists('ejercicios_fiscales');
    }
};
