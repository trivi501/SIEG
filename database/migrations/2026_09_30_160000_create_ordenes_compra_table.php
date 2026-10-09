<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orden de compra que Recursos Materiales genera para una requisición con suficiencia,
     * y los datos de la factura (XML CFDI) que se le cargan después.
     */
    public function up(): void
    {
        if (Schema::hasTable('ordenes_compra')) {
            return;
        }

        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->integer('id_tb_egreso_requisicion')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->unsignedInteger('folio');
            $table->unsignedSmallInteger('año');
            $table->string('folio_completo', 30)->unique();
            $table->date('fecha');
            $table->decimal('importe', 18, 2);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('xml_path', 500)->nullable();
            $table->string('xml_uuid', 60)->nullable()->unique();
            $table->string('xml_rfc_emisor', 15)->nullable();
            $table->string('xml_nombre_emisor', 300)->nullable();
            $table->string('xml_serie', 25)->nullable();
            $table->string('xml_folio', 40)->nullable();
            $table->dateTime('xml_fecha')->nullable();
            $table->decimal('xml_subtotal', 18, 2)->nullable();
            $table->decimal('xml_total', 18, 2)->nullable();
            $table->dateTime('xml_cargado_at')->nullable();
            $table->foreignId('xml_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->foreign('id_tb_egreso_requisicion')
                ->references('id_tb_egreso_requisicion')->on('tb_egreso_requisicion')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
