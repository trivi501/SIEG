<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    use Auditable;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'id_tb_egreso_requisicion', 'proveedor_id', 'folio', 'año', 'folio_completo', 'fecha', 'importe',
        'observaciones', 'user_id', 'xml_path', 'xml_uuid', 'xml_rfc_emisor', 'xml_nombre_emisor',
        'xml_serie', 'xml_folio', 'xml_fecha', 'xml_subtotal', 'xml_total', 'xml_cargado_at', 'xml_user_id',
        'pago_fecha', 'pago_forma', 'pago_referencia', 'pago_user_id', 'pago_registrado_at',
        'clc_folio', 'clc_año', 'clc_folio_completo', 'clc_fecha', 'clc_user_id', 'clc_generada_at',
    ];

    public const FORMAS_PAGO = ['transferencia' => 'Transferencia', 'cheque' => 'Cheque'];

    protected $hidden = ['xml_path'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'importe' => 'float',
            'xml_fecha' => 'datetime',
            'xml_subtotal' => 'float',
            'xml_total' => 'float',
            'xml_cargado_at' => 'datetime',
            'pago_fecha' => 'date:Y-m-d',
            'pago_registrado_at' => 'datetime',
            'clc_fecha' => 'date:Y-m-d',
            'clc_generada_at' => 'datetime',
        ];
    }

    public function requisicion()
    {
        return $this->belongsTo(EgresoRequisicion::class, 'id_tb_egreso_requisicion', 'id_tb_egreso_requisicion');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
