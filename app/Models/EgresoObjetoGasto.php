<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoObjetoGasto extends Model
{
    protected $table = 'cat_egreso_objeto_gasto';

    protected $primaryKey = 'id_cat_egreso_objeto_gasto';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_objeto_gasto',
        'clave',
        'tipo_gasto',
        'nombre',
        'activo'
    ];

    public function catEgresoProductos()
    {
        return $this->hasMany(EgresoProducto::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

    public function tbEgresoFacturas()
    {
        return $this->hasMany(EgresoFactura::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

    public function tbEgresoPresupuestos()
    {
        return $this->hasMany(EgresoPresupuesto::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

}
