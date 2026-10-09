<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Secretaria extends Model
{
    use Auditable;

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
