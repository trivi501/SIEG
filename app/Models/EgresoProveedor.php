<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoProveedor extends Model
{
    protected $table = 'tb_egreso_proveedor';

    protected $primaryKey = 'id_tb_egreso_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_proveedor',
        'nombre',
        'RFC',
        'activo',
        'id_cat_pais',
        'id_cat_estado',
        'id_cat_municipio',
        'localidad',
        'colonia',
        'num_interior',
        'num_exterior',
        'codigo_postal',
        'domicilio_completo',
        'calle',
        'id_cat_egreso_proveedor_banco',
        'registro',
        'id_tb_usuarios',
        'correo_electronico',
        'id_cat_egreso_proveedor_tipo',
        'bloqueado'
    ];

    public function tbEgresoOrdenCompras()
    {
        return $this->hasMany(EgresoOrdenCompra::class, 'id_tb_egreso_proveedor', 'id_tb_egreso_proveedor');
    }

    public function tbEgresoRequisicions()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_tb_egreso_proveedor', 'id_tb_egreso_proveedor');
    }

}
