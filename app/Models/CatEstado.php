<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatEstado extends Model
{
    protected $table = 'cat_estado';
    protected $primaryKey = 'id_estado';
    public $timestamps = false;

    protected $fillable = ['nombre_estado', 'activo'];

    public function municipios(): HasMany
    {
        return $this->hasMany(CatMunicipio::class, 'id_estado', 'id_estado');
    }
}
