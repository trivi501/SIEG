<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EjercicioFiscal extends Model
{
    use Auditable;

    protected $table = 'ejercicios_fiscales';

    protected $fillable = ['año', 'descripcion', 'estado', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'año' => 'integer'];
    }

    /** Un ejercicio registrado y cerrado no admite documentos nuevos; uno sin registrar no se bloquea. */
    public static function estaCerrado(int $año): bool
    {
        return static::where('año', $año)->where('estado', 'cerrado')->exists();
    }
}
