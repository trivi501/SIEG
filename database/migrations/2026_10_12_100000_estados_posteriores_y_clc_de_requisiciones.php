<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estados de la requisición después de la cotización (antes se quedaba "En Cotización" aunque ya
     * estuviera comprometida) y la CLC (cuenta por liquidar certificada) que genera el área cuando la
     * orden de compra ya tiene factura.
     */
    private const ESTADOS = [
        7 => 'Comprometida',
        8 => 'Orden de Compra',
        9 => 'Con Factura',
        10 => 'CLC Generada',
        11 => 'Pagada',
    ];

    public function up(): void
    {
        if (Schema::hasTable('ordenes_compra') && ! Schema::hasColumn('ordenes_compra', 'clc_folio_completo')) {
            Schema::table('ordenes_compra', function (Blueprint $table) {
                $table->unsignedInteger('clc_folio')->nullable();
                $table->unsignedSmallInteger('clc_año')->nullable();
                $table->string('clc_folio_completo', 30)->nullable()->unique();
                $table->date('clc_fecha')->nullable();
                $table->foreignId('clc_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('clc_generada_at')->nullable();
            });
        }

        // Tipo de documento para configurar los firmantes de la CLC.
        if (Schema::hasTable('tipos_documento') && ! DB::table('tipos_documento')->where('clave', 'clc')->exists()) {
            DB::table('tipos_documento')->insert([
                'clave' => 'clc', 'nombre' => 'Cuenta por liquidar certificada (CLC)', 'prefijo' => 'CLC',
                'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('cat_egreso_requisicion_estado')) {
            return;
        }

        foreach (self::ESTADOS as $id => $descripcion) {
            if (! DB::table('cat_egreso_requisicion_estado')->where('id_cat_egreso_requisicion_estado', $id)->exists()) {
                DB::table('cat_egreso_requisicion_estado')->insert([
                    'id_cat_egreso_requisicion_estado' => $id,
                    'descripcion' => $descripcion,
                    'activo' => 1,
                ]);
            }
        }

        if (! Schema::hasTable('tb_egreso_requisicion') || ! Schema::hasTable('ordenes_compra')) {
            return;
        }

        // Las requisiciones que ya avanzaron se quedaron "En Cotización" (6): se pasan al estado
        // que corresponde a lo que ya tienen.
        $requisiciones = DB::table('tb_egreso_requisicion as r')
            ->leftJoin('ordenes_compra as oc', 'oc.id_tb_egreso_requisicion', '=', 'r.id_tb_egreso_requisicion')
            ->where('r.id_cat_egreso_requisicion_estado', 6)
            ->where('r.suficiencia', 1)
            ->get(['r.id_tb_egreso_requisicion', 'oc.id as orden', 'oc.xml_uuid', 'oc.pago_fecha']);

        foreach ($requisiciones as $r) {
            $estado = match (true) {
                $r->pago_fecha !== null => 11,
                $r->xml_uuid !== null => 9,
                $r->orden !== null => 8,
                default => 7,
            };

            DB::table('tb_egreso_requisicion')
                ->where('id_tb_egreso_requisicion', $r->id_tb_egreso_requisicion)
                ->update(['id_cat_egreso_requisicion_estado' => $estado]);
        }
    }

    public function down(): void
    {
        // Los estados y folios de CLC ya pueden estar en uso por requisiciones: no se borran.
    }
};
