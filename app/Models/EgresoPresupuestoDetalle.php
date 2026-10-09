<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoPresupuestoDetalle extends Model
{
    protected $table = 'tb_egreso_presupuesto_detalle';

    protected $primaryKey = 'id_tb_egreso_presupuesto_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_presupuesto_detalle',
        'año',
        'id_tb_egreso_presupuesto',
        'importe',
        'modificado',
        'vigente',
        'comprometido',
        'devengado',
        'por_pagar',
        'ejercido',
        'pagado',
        'disponible',
        'modificacion_presupuestal',
        'requisicion_tramite',
        'requisicion_pendiente',
        'periodo_inicio',
        'periodo_fin'
    ];

    public function tbEgresoPresupuesto()
    {
        return $this->belongsTo(EgresoPresupuesto::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoRequisicions()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_tb_egreso_presupuesto_detalle', 'id_tb_egreso_presupuesto_detalle');
    }

    public function tbEgresoRequisicionDetalles()
    {
        return $this->hasMany(EgresoRequisicionDetalle::class, 'id_tb_egreso_presupuesto_detalle', 'id_tb_egreso_presupuesto_detalle');
    }

    public function tbEgresoRequisicionPartidas()
    {
        return $this->hasMany(EgresoRequisicionPartida::class, 'id_tb_egreso_presupuesto_detalle', 'id_tb_egreso_presupuesto_detalle');
    }

}
