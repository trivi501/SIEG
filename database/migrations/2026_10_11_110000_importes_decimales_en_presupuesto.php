<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los importes del presupuesto eran FLOAT(24,0): sin centavos y con precisión exacta solo hasta
     * ~16.7 millones. Pasan a DECIMAL(15,2) para que la distribución mensual pueda cuadrar al
     * centavo con el importe total. Los valores actuales (enteros) se conservan tal cual.
     */
    private const COLUMNAS = ['IMPORTE_TOTAL', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'];

    public function up(): void
    {
        // Tabla del sistema anterior: no existe en la base SQLite de las pruebas.
        if (! Schema::hasTable('presupuesto 2024')) {
            return;
        }

        Schema::table('presupuesto 2024', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal($columna, 15, 2)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('presupuesto 2024')) {
            return;
        }

        Schema::table('presupuesto 2024', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->float($columna, 24)->nullable()->change();
            }
        });
    }
};
