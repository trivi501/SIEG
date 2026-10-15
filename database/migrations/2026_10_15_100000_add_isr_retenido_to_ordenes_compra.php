<?php

use App\Services\EstadoCfdiSat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * ISR retenido en la factura (CFDI, Retenciones del comprobante con Impuesto 001). No cambia el importe
 * del gasto: al proveedor se le paga el importe menos el ISR (el Total del XML). Las facturas ya
 * cargadas toman el ISR de su XML guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'xml_isr')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->decimal('xml_isr', 15, 2)->nullable()->after('xml_total');
        });

        foreach (DB::table('ordenes_compra')->whereNotNull('xml_path')->get(['id', 'xml_path']) as $orden) {
            if (Storage::disk('local')->exists($orden->xml_path)) {
                $isr = EstadoCfdiSat::datosDelXml(Storage::disk('local')->get($orden->xml_path))['isr'];
                DB::table('ordenes_compra')->where('id', $orden->id)->update(['xml_isr' => $isr]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'xml_isr')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('xml_isr');
        });
    }
};
