<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoProducto extends Model
{
    protected $table = 'cat_egreso_producto';

    protected $primaryKey = 'id_cat_egreso_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_producto',
        'clave_producto',
        'descripcion',
        'clasificador_objeto_gasto',
        'id_cat_egreso_objeto_gasto',
        'activo'
    ];

    public function catEgresoObjetoGasto()
    {
        return $this->belongsTo(EgresoObjetoGasto::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

    public function tbEgresoRequisicionDetalles()
    {
        return $this->hasMany(EgresoRequisicionDetalle::class, 'id_cat_egreso_producto', 'id_cat_egreso_producto');
    }

}
