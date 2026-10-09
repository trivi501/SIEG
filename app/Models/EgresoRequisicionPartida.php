<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoRequisicionPartida extends Model
{
    protected $table = 'tb_egreso_requisicion_partida';

    protected $primaryKey = 'id_tb_egreso_requisicion_partida';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_requisicion_partida',
        'id_tb_egreso_requisicion',
        'id_tb_egreso_presupuesto',
        'id_tb_egreso_presupuesto_detalle',
        'cotizacion_sub_total',
        'cotizacion_descuento',
        'cotizacion_iva',
        'cotizacion_total',
        'cotizacion_isr',
        'factura_sub_total',
        'factura_descuento',
        'factura_iva',
        'factura_total',
        'factura_isr',
        'observaciones',
        'total_diferencia',
        'id_tb_egreso_orden_compra',
        'id_tb_egreso_factura'
    ];

    public function tbEgresoFactura()
    {
        return $this->belongsTo(EgresoFactura::class, 'id_tb_egreso_factura', 'id_tb_egreso_factura');
    }

    public function tbEgresoOrdenCompra()
    {
        return $this->belongsTo(EgresoOrdenCompra::class, 'id_tb_egreso_orden_compra', 'id_tb_egreso_orden_compra');
    }

    public function tbEgresoPresupuesto()
    {
        return $this->belongsTo(EgresoPresupuesto::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoPresupuestoDetalle()
    {
        return $this->belongsTo(EgresoPresupuestoDetalle::class, 'id_tb_egreso_presupuesto_detalle', 'id_tb_egreso_presupuesto_detalle');
    }

    public function tbEgresoRequisicion()
    {
        return $this->belongsTo(EgresoRequisicion::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

}
