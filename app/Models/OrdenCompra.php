<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'id_tb_egreso_requisicion', 'proveedor_id', 'folio', 'año', 'folio_completo', 'fecha', 'importe',
        'observaciones', 'user_id', 'xml_path', 'xml_uuid', 'xml_rfc_emisor', 'xml_nombre_emisor',
        'xml_serie', 'xml_folio', 'xml_fecha', 'xml_subtotal', 'xml_total', 'xml_cargado_at', 'xml_user_id',
    ];

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
