<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'accion', 'auditable_type', 'auditable_id', 'descripcion', 'antes', 'despues', 'lote', 'ip'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array'];
    }

    /** Lote y descripción activos (importación masiva): todos los cambios del bloque los comparten. */
    private static ?array $loteActual = null;

    public static function enLote(string $descripcion, callable $callback): mixed
    {
        $anterior = self::$loteActual;
        self::$loteActual = ['lote' => (string) Str::uuid(), 'descripcion' => mb_substr($descripcion, 0, 300)];

        try {
            return $callback();
        } finally {
            self::$loteActual = $anterior;
        }
    }

    public static function registrar(Model $modelo, string $accion, ?array $antes, ?array $despues): void
    {
        static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'auditable_type' => $modelo::class,
            'auditable_id' => (string) $modelo->getKey(),
            'descripcion' => self::$loteActual['descripcion'] ?? null,
            'antes' => $antes,
            'despues' => $despues,
            'lote' => self::$loteActual['lote'] ?? null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
