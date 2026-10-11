<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use Auditable;

    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'rfc', 'correo', 'telefono', 'domicilio', 'id_cat_banco', 'clabe', 'cuenta_bancaria', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function ordenesCompra()
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'id_cat_banco', 'id_cat_banco');
    }

    public function cuentas()
    {
        return $this->hasMany(ProveedorCuenta::class);
    }
}
