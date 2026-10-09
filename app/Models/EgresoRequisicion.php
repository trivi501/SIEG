<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoRequisicion extends Model
{
    protected $table = 'tb_egreso_requisicion';

    protected $primaryKey = 'id_tb_egreso_requisicion';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_requisicion',
        'id_cat_egreso_requisicion_estado',
        'id_cat_egreso_solicitud_tipo',
        'id_tb_egreso_presupuesto',
        'id_cat_egreso_unidad_administrativa',
        'id_cat_area_x_nombre_y_puesto_Solicita',
        'id_cat_area_x_nombre_y_puesto_Autoriza',
        'id_tb_usuarios',
        'id_user_laravel',
        'id_tb_egreso_presupuesto_detalle',
        'id_tb_egreso_proveedor',
        'folio',
        'año',
        'registro',
        'solicitado',
        'observaciones',
        'sub_total',
        'isr',
        'iva',
        'descuento',
        'total',
        'folio_completo',
        'sub_direccion',
        'departamento',
        'monto_requisicion',
        'beneficiario',
        'beneficiario_referencia',
        'suficiencia',
        'suficiencia_fecha',
        'suficiencia_user_id',
    ];

    protected function casts(): array
    {
        return [
            'suficiencia' => 'boolean',
            'suficiencia_fecha' => 'datetime',
        ];
    }

    public function ordenCompra()
    {
        return $this->hasOne(OrdenCompra::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function catEgresoRequisicionEstado()
    {
        return $this->belongsTo(EgresoRequisicionEstado::class, 'id_cat_egreso_requisicion_estado', 'id_cat_egreso_requisicion_estado');
    }

    public function catEgresoUnidadAdministrativa()
    {
        return $this->belongsTo(EgresoUnidadAdministrativa::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

    public function catEgresoSolicitudTipo()
    {
        return $this->belongsTo(EgresoSolicitudTipo::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

    public function tbEgresoPresupuesto()
    {
        return $this->belongsTo(EgresoPresupuesto::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoPresupuestoDetalle()
    {
        return $this->belongsTo(EgresoPresupuestoDetalle::class, 'id_tb_egreso_presupuesto_detalle', 'id_tb_egreso_presupuesto_detalle');
    }

    public function tbEgresoProveedor()
    {
        return $this->belongsTo(EgresoProveedor::class, 'id_tb_egreso_proveedor', 'id_tb_egreso_proveedor');
    }

    public function solicitante()
    {
        return $this->belongsTo(EgresoAreaNombrePuesto::class, 'id_cat_area_x_nombre_y_puesto_Solicita', 'id');
    }

    public function autorizante()
    {
        return $this->belongsTo(EgresoAreaNombrePuesto::class, 'id_cat_area_x_nombre_y_puesto_Autoriza', 'id');
    }

    public function tbEgresoOrdenCompras()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function tbEgresoRequisicionBitacoras()
    {
        return $this->hasMany(EgresoRequisicionBitacora::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function tbEgresoRequisicionDetalles()
    {
        return $this->hasMany(EgresoRequisicionDetalle::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function tbEgresoRequisicionPartidas()
    {
        return $this->hasMany(EgresoRequisicionPartida::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

}
