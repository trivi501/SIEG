<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'rfc', 'correo', 'telefono', 'domicilio', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function ordenesCompra()
    {
        return $this->hasMany(OrdenCompra::class);
    }
}
