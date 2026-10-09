<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoOrdenCompra extends Model
{
    protected $table = 'tb_egreso_orden_compra';

    protected $primaryKey = 'id_tb_egreso_orden_compra';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_orden_compra',
        'id_cat_egreso_solicitud_tipo',
        'registro',
        'alta',
        'proveedor_nombre',
        'id_tb_egreso_proveedor',
        'folio',
        'año',
        'id_tb_egreso_requisicion',
        'id_cat_area_x_nombre_y_puesto_avala',
        'id_cat_area_x_nombre_y_puesto_autoriza',
        'observaciones',
        'folio_completo',
        'id_tb_usuarios',
        'id_cat_egreso_requisicion_estado',
        'id_tb_egreso_orden_pago',
        'importe',
        'id_tb_egreso_factura_aprobada',
        'id_tb_egreso_factura_validacion'
    ];

    public function avalante()
    {
        return $this->belongsTo(EgresoAreaNombrePuesto::class, 'id_cat_area_x_nombre_y_puesto_avala', 'id');
    }

    public function catAreaXNombreYPuestoAutoriza()
    {
        return $this->belongsTo(EgresoAreaNombrePuesto::class, 'id_cat_area_x_nombre_y_puesto_autoriza', 'id');
    }

    public function catEgresoRequisicionEstado()
    {
        return $this->belongsTo(EgresoRequisicionEstado::class, 'id_cat_egreso_requisicion_estado', 'id_cat_egreso_requisicion_estado');
    }

    public function catEgresoSolicitudTipo()
    {
        return $this->belongsTo(EgresoSolicitudTipo::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

    public function tbEgresoFacturaAprobada()
    {
        return $this->belongsTo(EgresoFactura::class, 'id_tb_egreso_factura_aprobada', 'id_tb_egreso_factura');
    }

    public function tbEgresoProveedor()
    {
        return $this->belongsTo(EgresoProveedor::class, 'id_tb_egreso_proveedor', 'id_tb_egreso_proveedor');
    }

    public function tbEgresoRequisicion()
    {
        return $this->belongsTo(EgresoRequisicion::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function tbEgresoFacturas()
    {
        return $this->hasMany(EgresoFactura::class, 'id_tb_egreso_orden_compra', 'id_tb_egreso_orden_compra');
    }

    public function tbEgresoRequisicionPartidas()
    {
        return $this->hasMany(EgresoRequisicionPartida::class, 'id_tb_egreso_orden_compra', 'id_tb_egreso_orden_compra');
    }

}
