<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Padrón vehicular. El historial de resguardantes y de secretaría asignada queda en la
     * bitácora de auditoría (cada cambio guarda el valor anterior y el nuevo con su fecha).
     */
    public function up(): void
    {
        if (Schema::hasTable('vehiculos')) {
            return;
        }

        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_economico', 20)->unique();
            $table->string('placas', 15)->nullable()->unique();
            $table->string('serie', 30)->nullable()->unique();
            $table->string('marca', 60)->nullable();
            $table->string('linea', 80)->nullable();
            $table->unsignedSmallInteger('modelo')->nullable();
            $table->string('color', 40)->nullable();
            $table->string('tipo', 40)->nullable();
            $table->foreignId('secretaria_id')->nullable()->constrained('secretarias')->nullOnDelete();
            $table->string('resguardante', 200)->nullable();
            $table->string('cargo_resguardante', 200)->nullable();
            $table->string('numero_resguardo', 40)->nullable();
            $table->date('fecha_resguardo')->nullable();
            // en_servicio, taller, fuera_de_servicio
            $table->string('estado', 20)->default('en_servicio');
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
