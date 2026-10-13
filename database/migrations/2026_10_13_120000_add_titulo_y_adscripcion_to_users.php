<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regidores (catálogo) y datos del usuario para los formatos: título (L.C., LIC., MTRA.), cargo
     * (ENLACE ADMINISTRATIVO) y a quién pertenece: a una secretaría o dirección, o a un regidor (sus
     * enlaces; para el presupuesto quedan en Cabildo). Ese titular firma "Autorizó" en el FUR y
     * "Solicita" en la CLC.
     */
    public function up(): void
    {
        if (! Schema::hasTable('regidores')) {
            Schema::create('regidores', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 30)->nullable();
                $table->string('nombre', 200);
                // REGIDOR / REGIDORA
                $table->string('cargo', 60)->default('REGIDOR');
                $table->string('comision', 300)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'titulo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('titulo', 30)->nullable()->after('name');
                $table->string('cargo', 150)->nullable()->after('titulo');
                $table->foreignId('regidor_id')->nullable()->after('id_cat_egreso_unidad_administrativa')
                    ->constrained('regidores')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'titulo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['regidor_id']);
                $table->dropColumn(['titulo', 'cargo', 'regidor_id']);
            });
        }

        Schema::dropIfExists('regidores');
    }
};
