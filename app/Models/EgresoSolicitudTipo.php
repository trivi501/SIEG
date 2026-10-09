<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoSolicitudTipo extends Model
{
    protected $table = 'cat_egreso_solicitud_tipo';

    protected $primaryKey = 'id_cat_egreso_solicitud_tipo';

    public $timestamps = false;

    protected $fillable = [
        'id_cat_egreso_solicitud_tipo',
        'descripcion',
        'activo'
    ];

    public function tbEgresoFacturas()
    {
        return $this->hasMany(EgresoFactura::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

    public function tbEgresoOrdenCompras()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

    public function tbEgresoRequisicions()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_cat_egreso_solicitud_tipo', 'id_cat_egreso_solicitud_tipo');
    }

}
