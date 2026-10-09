<?php

namespace App\Http\Controllers;

use App\Catalogos\Catalogos;
use App\Models\Auditoria;
use App\Models\Secretaria;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Bitácora de auditoría general: altas, modificaciones y bajas de todos los catálogos. */
class AuditoriaController extends Controller
{
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
                'etiquetas' => $catalogo
                    ? collect($catalogo->campos)->mapWithKeys(fn ($c) => [$c->nombre => $c->etiqueta])->all()
                    : [],
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
            'tipos' => collect($tipos)->map(fn ($etiqueta, $clase) => ['valor' => $clase, 'etiqueta' => $etiqueta])->values(),
            'usuarios' => User::whereIn('id', Auditoria::query()->select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'acciones' => ['alta', 'modificación', 'baja', 'reactivación', 'eliminación'],
        ]);
    }

    /** @return array<class-string, string> */
    private function tipos(): array
    {
        $tipos = collect(Catalogos::todos())->mapWithKeys(fn ($c) => [$c->modelo => $c->titulo])->all();
        $tipos[Secretaria::class] = 'Secretarías';

        return $tipos;
    }
}
