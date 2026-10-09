<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionEstadoMomentoContable extends Model
{
    protected $table = 're_requisicion_estado_momento_contable';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_requisicion_estado',
        'id_cat_egreso_presupuesto_momento_contable'
    ];

    public function catEgresoPresupuestoMomentoContable()
    {
        return $this->belongsTo(EgresoPresupuestoMomentoContable::class, 'id_cat_egreso_presupuesto_momento_contable', 'id_cat_egreso_presupuesto_momento_contable');
    }

    public function catEgresoRequisicionEstado()
    {
        return $this->belongsTo(EgresoRequisicionEstado::class, 'id_cat_egreso_requisicion_estado', 'id_cat_egreso_requisicion_estado');
    }

}
