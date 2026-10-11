<?php

namespace App\Http\Controllers;

use App\Catalogos\Catalogos;
use App\Models\Auditoria;
use App\Models\EgresoRequisicion;
use App\Models\EgresoRequisicionDetalle;
use App\Models\ModificacionPresupuestal;
use App\Models\OrdenCompra;
use App\Models\PresupuestoEgreso;
use App\Models\Secretaria;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Bitácora de auditoría general: altas, modificaciones, bajas y accesos de todo el sistema. */
class AuditoriaController extends Controller
{
    /** Módulos que no son catálogos: etiqueta y nombres legibles de sus campos más usados. */
    private const MODULOS = [
        EgresoRequisicion::class => ['Requisiciones', [
            'id_cat_egreso_requisicion_estado' => 'Estado', 'id_cat_egreso_unidad_administrativa' => 'Unidad administrativa',
            'folio_completo' => 'Folio', 'observaciones' => 'Concepto', 'solicitado' => 'Fecha de trámite',
            'sub_total' => 'Subtotal', 'iva' => 'IVA', 'total' => 'Total', 'monto_requisicion' => 'Monto',
            'suficiencia' => 'Suficiencia', 'suficiencia_fecha' => 'Fecha de suficiencia', 'suficiencia_user_id' => 'Validó suficiencia (usuario)',
        ]],
        EgresoRequisicionDetalle::class => ['Conceptos de requisición', [
            'id_tb_egreso_requisicion' => 'Requisición', 'descripcion' => 'Descripción', 'cantidad' => 'Cantidad',
            'fuente' => 'Fuente', 'proyecto' => 'Proyecto', 'partida' => 'Partida', 'precio_unitario' => 'Precio unitario',
            'sub_total' => 'Subtotal', 'iva' => 'IVA', 'iva_factor' => 'Factor de IVA', 'total' => 'Total',
        ]],
        OrdenCompra::class => ['Órdenes de compra', [
            'id_tb_egreso_requisicion' => 'Requisición', 'proveedor_id' => 'Proveedor', 'folio_completo' => 'Folio',
            'fecha' => 'Fecha', 'importe' => 'Importe', 'observaciones' => 'Observaciones',
            'xml_uuid' => 'UUID de la factura', 'xml_rfc_emisor' => 'RFC emisor', 'xml_total' => 'Total de la factura',
        ]],
        ModificacionPresupuestal::class => ['Modificaciones presupuestales', [
            'folio_completo' => 'Folio', 'tipo' => 'Tipo', 'estado' => 'Estado', 'importe' => 'Importe',
            'justificacion' => 'Justificación', 'observaciones_resolucion' => 'Observaciones de la resolución',
        ]],
        PresupuestoEgreso::class => ['Presupuesto de egresos', [
            'CLAVE' => 'Unidad', 'CLAVE3' => 'Fuente', 'CLAVE6' => 'Proyecto', 'PARTIDA' => 'Partida',
            'NOMBRE_PARTIDA' => 'Nombre de la partida', 'IMPORTE_TOTAL' => 'Importe total',
        ]],
        Secretaria::class => ['Secretarías', ['nombre' => 'Nombre', 'prefijo' => 'Prefijo']],
        User::class => ['Usuarios y accesos', [
            'name' => 'Nombre', 'email' => 'Correo', 'secretaria_id' => 'Secretaría (anterior)', 'id_cat_egreso_unidad_administrativa' => 'Secretaría o dirección', 'password' => 'Contraseña',
            'roles' => 'Roles', 'email_verified_at' => 'Correo verificado',
        ]],
        Role::class => ['Roles', ['name' => 'Nombre', 'permisos' => 'Permisos']],
        Permission::class => ['Permisos', ['name' => 'Clave', 'nombre_mostrar' => 'Nombre', 'categoria' => 'Categoría']],
        SupportTicket::class => ['Tickets de soporte', []],
        TicketComment::class => ['Comentarios de tickets', []],
    ];

    public function index(Request $request)
    {
        $tipos = $this->tipos();

        $query = Auditoria::with('user:id,name')->latest('id');

        if ($tipo = $request->input('tipo')) {
            $query->where('auditable_type', $tipo);
        }

        if ($userId = $request->input('usuario')) {
            $query->where('user_id', $userId);
        }

        if ($accion = $request->input('accion')) {
            $query->where('accion', $accion);
        }

        // Las fechas del filtro son de hora local; created_at se guarda en UTC.
        if ($desde = $request->date('desde', null, 'America/Mexico_City')) {
            $query->where('created_at', '>=', $desde->startOfDay()->utc());
        }

        if ($hasta = $request->date('hasta', null, 'America/Mexico_City')) {
            $query->where('created_at', '<=', $hasta->endOfDay()->utc());
        }

        if ($lote = $request->input('lote')) {
            $query->where('lote', $lote);
        }

        if ($id = $request->input('registro')) {
            $query->where('auditable_id', $id);
        }

        $registros = $query->paginate(30)->withQueryString()->through(function (Auditoria $a) use ($tipos) {
            $catalogo = Catalogos::deModelo($a->auditable_type);

            return [
                'id' => $a->id,
                'fecha' => $a->created_at?->toIso8601String(),
                'usuario' => $a->user?->name,
                'accion' => $a->accion,
                'tipo' => $tipos[$a->auditable_type] ?? class_basename($a->auditable_type),
                'auditable_type' => $a->auditable_type,
                'registro' => $a->auditable_id,
                'catalogo' => $catalogo?->slug,
                'enlace' => $this->enlace($a, $catalogo?->slug),
                'etiquetas' => $catalogo
                    ? collect($catalogo->campos)->mapWithKeys(fn ($c) => [$c->nombre => $c->etiqueta])->all()
                    : (self::MODULOS[$a->auditable_type][1] ?? []),
                'descripcion' => $a->descripcion,
                'lote' => $a->lote,
                'antes' => $a->antes,
                'despues' => $a->despues,
                'ip' => $a->ip,
            ];
        });

        return Inertia::render('auditoria/Index', [
            'registros' => $registros,
            'filtros' => $request->only(['tipo', 'usuario', 'accion', 'desde', 'hasta', 'lote', 'registro']),
            'tipos' => collect($tipos)->sort()->map(fn ($etiqueta, $clase) => ['valor' => $clase, 'etiqueta' => $etiqueta])->values(),
            'usuarios' => User::whereIn('id', Auditoria::query()->select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'acciones' => Auditoria::ACCIONES,
        ]);
    }

    /** @return array<class-string, string> */
    private function tipos(): array
    {
        return [
            ...collect(Catalogos::todos())->mapWithKeys(fn ($c) => [$c->modelo => $c->titulo])->all(),
            ...collect(self::MODULOS)->map(fn ($m) => $m[0])->all(),
        ];
    }

    /** Pantalla donde se ve el registro afectado, si existe una. */
    private function enlace(Auditoria $a, ?string $catalogo): ?string
    {
        if ($a->accion === 'eliminación' || $a->auditable_id === '') {
            return null;
        }

        $id = $a->auditable_id;
        $requisicion = $a->despues['id_tb_egreso_requisicion'] ?? $a->antes['id_tb_egreso_requisicion'] ?? null;

        return match ($a->auditable_type) {
            EgresoRequisicion::class => "/requisiciones/{$id}",
            EgresoRequisicionDetalle::class, OrdenCompra::class => $requisicion ? "/requisiciones/{$requisicion}" : null,
            ModificacionPresupuestal::class => "/modificaciones-presupuestales/{$id}",
            User::class => "/settings/users/{$id}/edit",
            Role::class => "/settings/roles/{$id}/edit",
            Permission::class => "/settings/permissions/{$id}/edit",
            SupportTicket::class => "/support-tickets/{$id}",
            default => $catalogo ? "/catalogos/{$catalogo}" : null,
        };
    }
}
