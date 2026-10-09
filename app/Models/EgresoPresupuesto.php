<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EgresoPresupuesto extends Model
{
    protected $table = 'tb_egreso_presupuesto';

    protected $primaryKey = 'id_tb_egreso_presupuesto';

    public $timestamps = false;

    protected $fillable = [
        'id_tb_egreso_presupuesto',
        'id_cat_egreso_unidad_administrativa',
        'id_cat_egreso_objeto_gasto',
        'id_cat_egreso_proyecto',
        'id_cat_egreso_fuente_financiamiento',
        'año',
        'importe',
        'modificado',
        'comprometido',
        'vigente',
        'devengado',
        'por_pagar',
        'ejercido',
        'pagado',
        'disponible',
        'modificacion_presupuestal',
        'requisicion_tramite',
        'requisicion_pendiente',
        'detalle_importe',
        'detalle_diferencia',
        'nombre_completo',
        'transferible'
    ];

    public function catEgresoUnidadAdministrativa()
    {
        return $this->belongsTo(EgresoUnidadAdministrativa::class, 'id_cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa');
    }

    public function catEgresoFuenteFinanciamiento()
    {
        return $this->belongsTo(EgresoFuenteFinanciamiento::class, 'id_cat_egreso_fuente_financiamiento', 'id_cat_egreso_fuente_financiamiento');
    }

    public function catEgresoObjetoGasto()
    {
        return $this->belongsTo(EgresoObjetoGasto::class, 'id_cat_egreso_objeto_gasto', 'id_cat_egreso_objeto_gasto');
    }

    public function catEgresoProyecto()
    {
        return $this->belongsTo(EgresoProyecto::class, 'id_cat_egreso_proyecto', 'id_cat_egreso_proyecto');
    }

    public function tbEgresoFacturas()
    {
        return $this->hasMany(EgresoFactura::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoPresupuestoDetalles()
    {
        return $this->hasMany(EgresoPresupuestoDetalle::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoRequisicions()
    {
        return $this->hasMany(EgresoRequisicion::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoRequisicionDetalles()
    {
        return $this->hasMany(EgresoRequisicionDetalle::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

    public function tbEgresoRequisicionPartidas()
    {
        return $this->hasMany(EgresoRequisicionPartida::class, 'id_tb_egreso_presupuesto', 'id_tb_egreso_presupuesto');
    }

}
