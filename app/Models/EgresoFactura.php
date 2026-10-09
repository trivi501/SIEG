<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoFactura extends Model
{
    protected $table = 'tb_egreso_factura';

    protected $primaryKey = 'id_tb_egreso_factura';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_factura',
        'id_cat_egreso_requisicion_estado',
        'id_cat_egreso_solicitud_tipo',
        'id_tb_egreso_orden_compra',
        'Serie',
        'Folio',
        'Fecha',
        'id_formaPago',
        'CondicionesPago',
        'Moneda',
        'id_TipoComprobante',
        'id_MetodoPago',
        'LugarExpediecion',
        'RFC_Emisor',
        'Nombre_Emisor',
        'RFC_Receptor',
        'Nombre_receptor',
        'id_UsoCFDI',
        'timbre_CertificadoSAT',
        'timbre_fecha_factura',
        'timbre_fecha_certificacion',
        'letra',
        'total',
        'subtotal',
        'iva',
        'iva_tasa',
        'isr',
        'descuento',
        'uuid',
        'ruta_xml',
        'ruta_pdf',
        'cadena_original_complento',
        'sello_digital_cfdi',
        'sello_digital_sat',
        'certificado_emisor',
        'registro',
        'id_cat_egreso_objeto_gasto',
        'id_cat_egreso_unidad_administrativa',
        'carga_directa',
        'id_tb_egreso_presupuesto',
        'observaciones',
        'MetodoPago'
    ];

    public function catEgresoObjetoGasto()
    {
        return $this->belongsTo(EgresoObjetoGasto::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

    public function catEgresoRequisicionEstado()
    {
        return $this->belongsTo(EgresoRequisicionEstado::class, 'id_cat_egreso_requisicion_estado', 'id_cat_egreso_requisicion_estado');
    }

    public function catEgresoSolicitudTipo()
    {
        return $this->belongsTo(EgresoSolicitudTipo::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

    public function catEgresoUnidadAdministrativa()
    {
        return $this->belongsTo(EgresoUnidadAdministrativa::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

    public function tbEgresoOrdenCompra()
    {
        return $this->belongsTo(EgresoOrdenCompra::class, 'id_tb_egreso_orden_compra', 'id_tb_egreso_orden_compra');
    }

    public function tbEgresoPresupuesto()
    {
        return $this->belongsTo(EgresoPresupuesto::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoOrdenCompras()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_tb_egreso_factura_aprobada', 'id_tb_egreso_factura');
    }

    public function tbEgresoRequisicionPartidas()
    {
        return $this->hasMany(EgresoRequisicionPartida::class, 'id_tb_egreso_factura', 'id_tb_egreso_factura');
    }

}
