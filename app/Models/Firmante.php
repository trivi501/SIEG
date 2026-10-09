<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Firmante extends Model
{
    use Auditable;

    public const ROLES = [
        'elabora' => 'Elabora',
        'revisa' => 'Revisa',
        'autoriza' => 'Autoriza',
        'vobo' => 'Visto bueno',
    ];

    protected $fillable = ['tipo_documento_id', 'rol', 'id_cat_egreso_unidad_administrativa', 'nombre', 'cargo', 'vigente_desde', 'vigente_hasta', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }

    /**
     * Firmante vigente en la fecha del documento. El configurado para la unidad tiene prioridad
     * sobre el general (sin unidad); null si no hay ninguno y el PDF usa su texto por defecto.
     */
    public static function vigente(string $tipoDocumento, string $rol, ?int $unidadId = null, ?CarbonInterface $fecha = null): ?self
    {
        $fecha = ($fecha ?? now())->toDateString();

        return static::query()
            ->whereHas('tipoDocumento', fn ($q) => $q->where('clave', $tipoDocumento))
            ->where('rol', $rol)
            ->where('activo', true)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha))
            ->where(fn ($q) => $q->whereNull('id_cat_egreso_unidad_administrativa')->orWhere('id_cat_egreso_unidad_administrativa', $unidadId))
            ->orderByRaw('id_cat_egreso_unidad_administrativa is null')
            ->orderByDesc('vigente_desde')
            ->first();
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function unidadAdministrativa()
    {
        return $this->belongsTo(EgresoUnidadAdministrativa::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }
}
