<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoAreaNombrePuesto extends Model
{
    protected $table = 'cat_area_x_nombre_y_puesto';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id', 'nombre', 'puesto', 'area', 'fecha', 'activo', 'clave',
    ];

    public function requisicionesSolicitadas()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_cat_area_x_nombre_y_puesto_Solicita', 'id');
    }

    public function requisicionesAutorizadas()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_cat_area_x_nombre_y_puesto_Autoriza', 'id');
    }

    public function ordenesCompraAvaladas()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_cat_area_x_nombre_y_puesto_avala', 'id');
    }

    public function ordenesCompraAutorizadas()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_cat_area_x_nombre_y_puesto_autoriza', 'id');
    }

    public function ordenesPagoTesorero()
    {
        return $this->hasMany(EgresoOrdenPago::class, 'id_cat_area_x_nombre_y_puesto_tesorero', 'id');
    }

    public function ordenesPagoSindico()
    {
        return $this->hasMany(EgresoOrdenPago::class, 'id_cat_area_x_nombre_y_puesto_sindico', 'id');
    }

    public function ordenesPagoTitularUnidad()
    {
        return $this->hasMany(EgresoOrdenPago::class, 'id_cat_area_x_nombre_y_puesto_titular_unidad', 'id');
    }

    public function ordenesPagoPresidente()
    {
        return $this->hasMany(EgresoOrdenPago::class, 'id_cat_area_x_nombre_y_puesto_presidente', 'id');
    }

    public function presupuestoModificacionesTesorero()
    {
        return $this->hasMany(EgresoPresupuestoModificacion::class, 'id_cat_area_x_nombre_y_puesto_Tesorero', 'id');
    }

    public function enlacesAdministrativos()
    {
        return $this->hasMany(EgresoEnlaceAdministrativoRegidor::class, 'id_cat_area_x_nombre_y_puesto', 'id');
    }

    public function fondosRevolventes()
    {
        return $this->hasMany(EgresoUnidadAdministrativaFondoRevolvente::class, 'id_cat_area_x_nombre_y_puesto', 'id');
    }

    public function unidadAdministrativa()
    {
        return $this->hasMany(EgresoUnidadAdministrativa::class, 'id_cat_area_x_nombre_y_puesto', 'id');
    }
}
