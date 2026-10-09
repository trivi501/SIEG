<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoRequisicionCompraConsolidada extends Model
{
    protected $table = 'cat_egreso_requisicion_compra_consolidada';

    protected $primaryKey = 'id_cat_egreso_requisicion_compra_consolidada';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_requisicion_compra_consolidada',
        'articulo',
        'precio_unitario',
        'clave_compra'
    ];

}
