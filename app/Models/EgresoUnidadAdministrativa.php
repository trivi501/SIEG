<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoUnidadAdministrativa extends Model
{
    protected $table = 'cat_egreso_unidad_administrativa';

    protected $primaryKey = 'id_cat_egreso_unidad_administrativa';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_unidad_administrativa',
        'secretaria_id',
        'clave',
        'nombre',
        'año',
        'activo',
        'id_cat_area_x_nombre_y_puesto'
    ];

    public function secretaria()
    {
        return $this->belongsTo(Secretaria::class, 'secretaria_id', 'id');
    }

    public function tbEgresoFacturas()
    {
        return $this->hasMany(EgresoFactura::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

    public function tbEgresoPresupuestos()
    {
        return $this->hasMany(EgresoPresupuesto::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

    public function tbEgresoRequisicions()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

}
