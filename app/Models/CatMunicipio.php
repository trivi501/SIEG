<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatMunicipio extends Model
{
    protected $table = 'cat_municipio';
    protected $primaryKey = 'id_municipio';
    public $timestamps = false;

    protected $fillable = ['id_estado', 'nombre_municipio', 'activo'];

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'id_estado', 'id_estado');
    }
}
