<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoPresupuestoMomentoContable extends Model
{
    protected $table = 'cat_egreso_presupuesto_momento_contable';

    protected $primaryKey = 'id_cat_egreso_presupuesto_momento_contable';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_presupuesto_momento_contable',
        'descripcion',
        'activo'
    ];

    public function reRequisicionEstadoMomentoContables()
    {
        return $this->hasMany(RequisicionEstadoMomentoContable::class, 'id_cat_egreso_presupuesto_momento_contable', 'id_cat_egreso_presupuesto_momento_contable');
    }

}
