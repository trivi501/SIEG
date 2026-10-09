<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatPais extends Model
{
    protected $table = 'cat_pais';
    protected $primaryKey = 'id_pais';
    public $timestamps = false;

    protected $fillable = ['nombre_pais', 'activo'];
}
