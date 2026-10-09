<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoRequisicionCalendarioPermitido extends Model
{
    protected $table = 'tb_egreso_requisicion_calendario_permitido';

    protected $primaryKey = 'id_tb_egreso_requisicion_calendario_permitido';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_requisicion_calendario_permitido',
        'año',
        'mes',
        'limite'
    ];

}
