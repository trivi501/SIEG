<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrón vehicular: el vehículo es resguardo de una secretaría (id_cat_egreso_unidad_administrativa) y está
 * asignado a un área de ella (departamento). Mantenimiento: se recibe el vehículo en el taller y desde ahí
 * se hace la requisición a nombre de la secretaría de resguardo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehiculos') && ! Schema::hasColumn('vehiculos', 'departamento_id')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->unsignedBigInteger('departamento_id')->nullable()->index()->after('id_cat_egreso_unidad_administrativa');
            });
        }

        if (! Schema::hasTable('vehiculo_mantenimientos') && Schema::hasTable('vehiculos')) {
            Schema::create('vehiculo_mantenimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->unsignedInteger('folio');
                $table->unsignedSmallInteger('año');
                $table->string('folio_completo', 20)->unique();
                // Secretaría de resguardo y área asignada al recibirlo (la requisición va a nombre de la secretaría).
                $table->smallInteger('id_cat_egreso_unidad_administrativa')->nullable()->index('vehiculo_mant_unidad_index');
                $table->unsignedBigInteger('departamento_id')->nullable();
                $table->date('fecha_recepcion');
                $table->unsignedInteger('kilometraje')->nullable();
                $table->string('tipo', 20); // preventivo / correctivo
                $table->string('descripcion', 2000); // falla reportada o servicio solicitado
                $table->string('entrega', 200)->nullable(); // quien lleva el vehículo al taller
                $table->foreignId('user_id')->nullable()->constrained('users'); // quien lo recibe
                $table->string('estado', 20)->default('en_taller'); // en_taller / entregado
                $table->date('fecha_entrega')->nullable();
                $table->string('recibe_entrega', 200)->nullable(); // quien se lo lleva al terminar
                $table->string('observaciones_entrega', 2000)->nullable();
                $table->integer('id_tb_egreso_requisicion')->nullable()->index();
                $table->timestamps();
                $table->unique(['año', 'folio']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_mantenimientos');
        if (Schema::hasColumn('vehiculos', 'departamento_id')) {
            Schema::table('vehiculos', fn (Blueprint $table) => $table->dropColumn('departamento_id'));
        }
    }
};
