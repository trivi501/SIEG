<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Almacén: Recursos Materiales asigna la orden de compra al almacén; el almacén registra a mano sus
 * entradas (lo que recibe del proveedor, por partes) y sus salidas (lo que entrega al área, por partes).
 * Cada renglón apunta al concepto de la requisición de la orden. Formatos: acta de entrega, entrada y
 * salida de almacén (tipos de documento y firmantes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordenes_compra') && ! Schema::hasColumn('ordenes_compra', 'almacen_asignada_at')) {
            Schema::table('ordenes_compra', function (Blueprint $table) {
                $table->timestamp('almacen_asignada_at')->nullable()->after('motivo_cancelacion');
                $table->unsignedBigInteger('almacen_asignada_user_id')->nullable()->after('almacen_asignada_at');
            });
        }

        if (! Schema::hasTable('almacen_entradas')) {
            Schema::create('almacen_entradas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_compra_id')->constrained('ordenes_compra');
                $table->unsignedInteger('folio');
                $table->unsignedSmallInteger('año');
                $table->string('folio_completo', 20)->unique();
                $table->date('fecha');
                $table->string('observaciones', 1000)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->timestamps();
                $table->unique(['año', 'folio']);
            });
        }
        if (! Schema::hasTable('almacen_entrada_detalles')) {
            Schema::create('almacen_entrada_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('almacen_entrada_id')->constrained('almacen_entradas')->cascadeOnDelete();
                $table->unsignedBigInteger('id_tb_egreso_requisicion_detalle')->index();
                $table->string('descripcion', 1000);
                $table->decimal('cantidad', 15, 2);
                $table->decimal('precio_unitario', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('almacen_salidas')) {
            Schema::create('almacen_salidas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_compra_id')->constrained('ordenes_compra');
                $table->unsignedInteger('folio');
                $table->unsignedSmallInteger('año');
                $table->string('folio_completo', 20)->unique();
                $table->date('fecha');
                $table->smallInteger('id_cat_egreso_unidad_administrativa')->nullable()->index();
                $table->string('recibe', 200)->nullable();
                $table->string('observaciones', 1000)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->timestamps();
                $table->unique(['año', 'folio']);
            });
        }
        if (! Schema::hasTable('almacen_salida_detalles')) {
            Schema::create('almacen_salida_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('almacen_salida_id')->constrained('almacen_salidas')->cascadeOnDelete();
                $table->unsignedBigInteger('id_tb_egreso_requisicion_detalle')->index();
                $table->string('descripcion', 1000);
                $table->decimal('cantidad', 15, 2);
                $table->timestamps();
            });
        }

        $this->formatos();
    }

    /** Tipos de documento y firmantes iniciales (se cambian en Catálogos → Firmantes). */
    private function formatos(): void
    {
        if (! Schema::hasTable('tipos_documento')) {
            return;
        }

        foreach ([
            ['acta_entrega', 'Acta de entrega', 'AE'],
            ['entrada_almacen', 'Entrada de almacén', 'EA'],
            ['salida_almacen', 'Salida de almacén', 'SA'],
        ] as [$clave, $nombre, $prefijo]) {
            if (! DB::table('tipos_documento')->where('clave', $clave)->exists()) {
                DB::table('tipos_documento')->insert(['clave' => $clave, 'nombre' => $nombre, 'prefijo' => $prefijo, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (! Schema::hasTable('firmantes')) {
            return;
        }

        $jefe = ['CARLOS URIEL ESCOBEDO VENEGAS', 'JEFE DEL DEPARTAMENTO DE RECURSOS MATERIALES'];
        foreach ([
            // Entrada: autoriza el jefe de Recursos Materiales; elabora (recibe) la receptora de insumos.
            ['entrada_almacen', 'autoriza', ...$jefe],
            ['entrada_almacen', 'elabora', 'DELIA ROMÁN GARCÍA', 'RECEPTORA DE INSUMOS'],
            // Salida: autoriza el titular de Recursos Materiales (entrega el usuario; recibe/autoriza el titular del área).
            ['salida_almacen', 'autoriza', ...$jefe],
        ] as [$clave, $rol, $nombre, $cargo]) {
            $tipo = DB::table('tipos_documento')->where('clave', $clave)->value('id');
            if ($tipo && ! DB::table('firmantes')->where('tipo_documento_id', $tipo)->where('rol', $rol)->exists()) {
                DB::table('firmantes')->insert([
                    'tipo_documento_id' => $tipo, 'rol' => $rol, 'nombre' => $nombre, 'cargo' => $cargo,
                    'vigente_desde' => '2026-01-01', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen_salida_detalles');
        Schema::dropIfExists('almacen_salidas');
        Schema::dropIfExists('almacen_entrada_detalles');
        Schema::dropIfExists('almacen_entradas');
        if (Schema::hasColumn('ordenes_compra', 'almacen_asignada_at')) {
            Schema::table('ordenes_compra', function (Blueprint $table) {
                $table->dropColumn(['almacen_asignada_at', 'almacen_asignada_user_id']);
            });
        }
    }
};
