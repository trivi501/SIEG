<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoFuenteFinanciamiento extends Model
{
    protected $table = 'cat_egreso_fuente_financiamiento';

    protected $primaryKey = 'id_cat_egreso_fuente_financiamiento';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_fuente_financiamiento',
        'clave',
        'nombre',
        'activo'
    ];

    public function tbEgresoPresupuestos()
    {
        return $this->hasMany(EgresoPresupuesto::class, 'id_cat_egreso_fuente_financiamiento', 'id_cat_egreso_fuente_financiamiento');
    }

}
