<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Secretaria extends Model
{
    protected $fillable = [
        'nombre',
        'prefijo',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'secretaria_id', 'id');
    }

    public function unidadesAdministrativas()
    {
        return $this->hasMany(EgresoUnidadAdministrativa::class, 'secretaria_id', 'id');
    }
}
