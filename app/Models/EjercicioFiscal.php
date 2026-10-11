<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class EjercicioFiscal extends Model
{
    use Auditable;

    /** Periodo ordinario de captura de requisiciones cuando el ejercicio no está registrado. */
    public const CAPTURA_DIA_INICIO = 1;

    public const CAPTURA_DIA_FIN = 7;

    protected $table = 'ejercicios_fiscales';

    protected $fillable = ['año', 'descripcion', 'estado', 'captura_dia_inicio', 'captura_dia_fin', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'año' => 'integer', 'captura_dia_inicio' => 'integer', 'captura_dia_fin' => 'integer'];
    }

    /** Un ejercicio registrado y cerrado no admite documentos nuevos; uno sin registrar no se bloquea. */
    public static function estaCerrado(int $año): bool
    {
        return static::where('año', $año)->where('estado', 'cerrado')->exists();
    }

    /**
     * Días del mes (inicio, fin) en que se capturan requisiciones en el ejercicio.
     *
     * @return array{0: int, 1: int}
     */
    public static function periodoCaptura(int $año): array
    {
        $ejercicio = static::where('año', $año)->first(['captura_dia_inicio', 'captura_dia_fin']);

        return [
            $ejercicio?->captura_dia_inicio ?? self::CAPTURA_DIA_INICIO,
            $ejercicio?->captura_dia_fin ?? self::CAPTURA_DIA_FIN,
        ];
    }

    /** ¿La fecha (hora de México) cae en el periodo ordinario de captura de su ejercicio? */
    public static function enPeriodoDeCaptura(CarbonInterface $fecha): bool
    {
        [$inicio, $fin] = static::periodoCaptura($fecha->year);

        return $fecha->day >= $inicio && $fecha->day <= $fin;
    }
}
