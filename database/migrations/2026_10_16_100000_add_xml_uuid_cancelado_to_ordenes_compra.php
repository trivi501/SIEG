<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Al cancelar la orden de compra se cancela su factura: el UUID pasa a `xml_uuid_cancelado` (historial)
 * y deja libre `xml_uuid` (único), para que esa misma factura se pueda cargar una vez más en otra orden.
 * Las órdenes ya canceladas mueven su UUID igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra') || Schema::hasColumn('ordenes_compra', 'xml_uuid_cancelado')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('xml_uuid_cancelado', 40)->nullable()->index()->after('xml_uuid');
        });

        DB::table('ordenes_compra')->whereNotNull('cancelada_at')->whereNotNull('xml_uuid')
            ->update(['xml_uuid_cancelado' => DB::raw('xml_uuid'), 'xml_uuid' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordenes_compra', 'xml_uuid_cancelado')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropIndex(['xml_uuid_cancelado']);
            $table->dropColumn('xml_uuid_cancelado');
        });
    }
};
