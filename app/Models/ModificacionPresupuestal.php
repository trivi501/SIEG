<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\PresupuestoService;
use App\Services\SaldosMensuales;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class ModificacionPresupuestal extends Model
{
    use Auditable;

    protected $table = 'modificaciones_presupuestales';

    public const TIPOS = [
        'traspaso' => 'Traspaso',
        'reduccion' => 'Reducción',
        'ampliacion' => 'Ampliación',
    ];

    protected $fillable = [
        'folio', 'año', 'folio_completo', 'tipo', 'estado',
        'origen_clave', 'origen_fuente', 'origen_proyecto', 'origen_partida', 'origen_descripcion', 'origen_mes',
        'destino_clave', 'destino_fuente', 'destino_proyecto', 'destino_partida', 'destino_descripcion', 'destino_mes',
        'importe', 'justificacion', 'id_tb_egreso_requisicion',
        'solicitante_id', 'autorizador_id', 'resuelto_at', 'observaciones_resolucion',
        'origen_disponible_antes', 'destino_vigente_antes',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'float',
            'resuelto_at' => 'datetime',
            'origen_mes' => 'integer',
            'destino_mes' => 'integer',
            'origen_disponible_antes' => 'float',
            'destino_vigente_antes' => 'float',
        ];
    }

    /** Días antes del fin de mes en que se cierra la captura para las áreas. */
    public const DIAS_CIERRE = 3;

    /**
     * Último día del mes en que las áreas pueden capturar: el fin de mes menos DIAS_CIERRE (en octubre,
     * hasta el 28; del 29 al 31 está cerrada).
     */
    public static function ultimoDiaCaptura(?CarbonImmutable $hoy = null): CarbonImmutable
    {
        $hoy ??= CarbonImmutable::now(SaldosMensuales::ZONA);

        return $hoy->endOfMonth()->subDays(self::DIAS_CIERRE)->startOfDay();
    }

    /** Control Presupuestal (quien autoriza) y Admin capturan siempre; las áreas, hasta el día de cierre. */
    public static function puedeCapturar(User $user, ?CarbonImmutable $hoy = null): bool
    {
        $hoy ??= CarbonImmutable::now(SaldosMensuales::ZONA);

        return self::siempreAbierta($user) || $hoy->startOfDay()->lte(self::ultimoDiaCaptura($hoy));
    }

    public static function siempreAbierta(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin']) || $user->can('modificaciones-autorizar');
    }

    /** Clave unidad|fuente|proyecto|partida del lado indicado, o null si ese lado no aplica al tipo. */
    public function claveLinea(string $lado): ?string
    {
        if ($this->{"{$lado}_clave"} === null) {
            return null;
        }

        return PresupuestoService::clave(
            $this->{"{$lado}_clave"}, $this->{"{$lado}_fuente"}, $this->{"{$lado}_proyecto"}, $this->{"{$lado}_partida"}
        );
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function autorizador()
    {
        return $this->belongsTo(User::class, 'autorizador_id');
    }

    public function requisicion()
    {
        return $this->belongsTo(EgresoRequisicion::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }
}
