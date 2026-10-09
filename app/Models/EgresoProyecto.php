<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoProyecto extends Model
{
    protected $table = 'cat_egreso_proyecto';

    protected $primaryKey = 'id_cat_egreso_proyecto';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_proyecto',
        'clave',
        'nombre',
        'activo'
    ];

    public function tbEgresoPresupuestos()
    {
        return $this->hasMany(EgresoPresupuesto::class, 'id_cat_egreso_proyecto', 'id_cat_egreso_proyecto');
    }

}
