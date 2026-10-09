<?php

namespace App\Models;

use App\Services\PresupuestoService;
use Illuminate\Database\Eloquent\Model;

class ModificacionPresupuestal extends Model
{
    protected $table = 'modificaciones_presupuestales';

    public const TIPOS = [
        'traspaso' => 'Traspaso',
        'reduccion' => 'Reducción',
        'ampliacion' => 'Ampliación',
    ];

    protected $fillable = [
        'folio', 'año', 'folio_completo', 'tipo', 'estado',
        'origen_clave', 'origen_fuente', 'origen_proyecto', 'origen_partida', 'origen_descripcion',
        'destino_clave', 'destino_fuente', 'destino_proyecto', 'destino_partida', 'destino_descripcion',
        'importe', 'justificacion', 'id_tb_egreso_requisicion',
        'solicitante_id', 'autorizador_id', 'resuelto_at', 'observaciones_resolucion',
        'origen_disponible_antes', 'destino_vigente_antes',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'float',
            'resuelto_at' => 'datetime',
            'origen_disponible_antes' => 'float',
            'destino_vigente_antes' => 'float',
        ];
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
