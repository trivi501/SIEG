<?php

namespace App\Services;

use App\Models\EgresoRequisicion;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\ModificacionPresupuestal;
use App\Models\PresupuestoEgreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saldos del presupuesto de egresos por línea (unidad|fuente|proyecto|partida):
 *
 *   vigente      = asignado (IMPORTE_TOTAL importado) ± modificaciones presupuestales autorizadas
 *   comprometido = requisiciones validadas con suficiencia
 *   en trámite   = requisiciones no rechazadas que aún no tienen suficiencia
 *   disponible   = vigente - comprometido
 */
class PresupuestoService
{
    public static function clave(?string $unidad, ?string $fuente, ?string $proyecto, ?string $partida): string
    {
        return implode('|', array_map(fn ($v) => trim((string) $v), [$unidad, $fuente, $proyecto, $partida]));
    }

    /**
     * Claves de unidad administrativa (CLAVE del presupuesto) que el usuario puede ver;
     * null = todas (Admin o quien autoriza modificaciones presupuestales).
     */
    public static function clavesUnidadVisibles($user): ?array
    {
        if ($user->hasRole(['Super Admin', 'Admin']) || $user->can('modificaciones-autorizar')) {
            return null;
        }

        if (! $user->secretaria_id) {
            return [];
        }

        return EgresoUnidadAdministrativa::where('secretaria_id', $user->secretaria_id)->pluck('clave')->all();
    }

    /**
     * @param  array<string>|null  $clavesUnidad  null = todas las unidades
     * @param  int|null  $excluirRequisicion  requisición que no se cuenta (la que se está validando)
     */
    public function lineas(?array $clavesUnidad = null, ?int $excluirRequisicion = null): Collection
    {
        $presupuesto = PresupuestoEgreso::query()
            ->when($clavesUnidad !== null, fn ($q) => $q->whereIn('CLAVE', $clavesUnidad === [] ? ['__ninguna__'] : $clavesUnidad))
            ->orderBy('CLAVE')->orderBy('CLAVE3')->orderBy('CLAVE6')->orderBy('PARTIDA')
            ->get(['id_presupuesto', 'CLAVE', 'UNIDAD_ADMINISTRATIVA', 'CLAVE3', 'FUENTE_DE_FINANCIAMIENTO', 'CLAVE6', 'PROYECTO', 'PARTIDA', 'NOMBRE_PARTIDA', 'IMPORTE_TOTAL']);

        $modificado = $this->modificadoPorClave();
        [$comprometido, $enTramite] = $this->ejercidoPorClave($excluirRequisicion);

        return $presupuesto->map(function ($p) use ($modificado, $comprometido, $enTramite) {
            $clave = self::clave($p->CLAVE, $p->CLAVE3, $p->CLAVE6, $p->PARTIDA);
            $asignado = (float) $p->IMPORTE_TOTAL;
            $mod = $modificado[$clave] ?? 0.0;
            $vigente = $asignado + $mod;
            $comp = $comprometido[$clave] ?? 0.0;

            return [
                'clave' => $clave,
                'unidad_clave' => trim((string) $p->CLAVE),
                'unidad' => trim((string) $p->UNIDAD_ADMINISTRATIVA),
                'fuente' => trim((string) $p->CLAVE3),
                'nombre_fuente' => trim((string) $p->FUENTE_DE_FINANCIAMIENTO),
                'proyecto' => trim((string) $p->CLAVE6),
                'nombre_proyecto' => trim((string) $p->PROYECTO),
                'partida' => trim((string) $p->PARTIDA),
                'nombre_partida' => trim((string) $p->NOMBRE_PARTIDA),
                'asignado' => round($asignado, 2),
                'modificado' => round($mod, 2),
                'vigente' => round($vigente, 2),
                'comprometido' => round($comp, 2),
                'en_tramite' => round($enTramite[$clave] ?? 0.0, 2),
                'disponible' => round($vigente - $comp, 2),
            ];
        })->values();
    }

    public function linea(string $clave, ?int $excluirRequisicion = null): ?array
    {
        $unidad = explode('|', $clave)[0];

        return $this->lineas([$unidad], $excluirRequisicion)->firstWhere('clave', $clave);
    }

    /**
     * Compara lo cotizado en la requisición contra el disponible de cada línea de presupuesto.
     *
     * @return array{suficiente: bool, total: float, partidas: array<int, array<string, mixed>>}
     */
    public function validarRequisicion(EgresoRequisicion $requisicion): array
    {
        $requisicion->loadMissing('tbEgresoRequisicionDetalles', 'catEgresoUnidadAdministrativa');
        $unidadClave = (string) $requisicion->catEgresoUnidadAdministrativa?->clave;

        $lineas = $this->lineas([$unidadClave], $requisicion->id_tb_egreso_requisicion)->keyBy('clave');

        $requerido = [];
        foreach ($requisicion->tbEgresoRequisicionDetalles as $d) {
            $clave = self::clave($unidadClave, $d->fuente, $d->proyecto, $d->partida);
            $requerido[$clave] = ($requerido[$clave] ?? 0) + (float) ($d->total ?? $d->sub_total ?? 0);
        }

        $partidas = [];
        $suficiente = $requerido !== [];
        foreach ($requerido as $clave => $monto) {
            $linea = $lineas[$clave] ?? null;
            [, $fuente, $proyecto, $partida] = explode('|', $clave);
            $ok = $linea !== null && round($monto, 2) <= $linea['disponible'];
            $suficiente = $suficiente && $ok;

            $partidas[] = [
                'clave' => $clave,
                'fuente' => $fuente,
                'proyecto' => $proyecto,
                'partida' => $partida,
                'nombre_partida' => $linea['nombre_partida'] ?? null,
                'existe' => $linea !== null,
                'requerido' => round($monto, 2),
                'vigente' => $linea['vigente'] ?? 0,
                'comprometido' => $linea['comprometido'] ?? 0,
                'disponible' => $linea['disponible'] ?? 0,
                'faltante' => $ok ? 0 : round($monto - ($linea['disponible'] ?? 0), 2),
                'suficiente' => $ok,
            ];
        }

        return [
            'suficiente' => $suficiente,
            'total' => round(array_sum($requerido), 2),
            'partidas' => $partidas,
        ];
    }

    /** Suma neta de modificaciones autorizadas por línea (destino suma, origen resta). */
    private function modificadoPorClave(): array
    {
        $neto = [];
        foreach (ModificacionPresupuestal::where('estado', 'autorizada')->get() as $m) {
            if ($origen = $m->claveLinea('origen')) {
                $neto[$origen] = ($neto[$origen] ?? 0) - $m->importe;
            }
            if ($destino = $m->claveLinea('destino')) {
                $neto[$destino] = ($neto[$destino] ?? 0) + $m->importe;
            }
        }

        return $neto;
    }

    /** @return array{0: array<string, float>, 1: array<string, float>} [comprometido, en trámite] */
    private function ejercidoPorClave(?int $excluirRequisicion): array
    {
        $filas = DB::table('tb_egreso_requisicion_detalle as d')
            ->join('tb_egreso_requisicion as r', 'r.id_tb_egreso_requisicion', '=', 'd.id_tb_egreso_requisicion')
            ->join('cat_egreso_unidad_administrativa as u', 'u.id_cat_egreso_unidad_administrativa', '=', 'r.id_cat_egreso_unidad_administrativa')
            ->where('r.id_cat_egreso_requisicion_estado', '!=', 4) // rechazada no compromete presupuesto
            ->when($excluirRequisicion, fn ($q) => $q->where('r.id_tb_egreso_requisicion', '!=', $excluirRequisicion))
            ->selectRaw('u.clave, d.fuente, d.proyecto, d.partida, r.suficiencia, SUM(COALESCE(d.total, d.sub_total, 0)) as monto')
            ->groupBy('u.clave', 'd.fuente', 'd.proyecto', 'd.partida', 'r.suficiencia')
            ->get();

        $comprometido = [];
        $enTramite = [];
        foreach ($filas as $f) {
            $clave = self::clave($f->clave, $f->fuente, $f->proyecto, $f->partida);
            if ((int) $f->suficiencia === 1) {
                $comprometido[$clave] = ($comprometido[$clave] ?? 0) + (float) $f->monto;
            } else {
                $enTramite[$clave] = ($enTramite[$clave] ?? 0) + (float) $f->monto;
            }
        }

        return [$comprometido, $enTramite];
    }
}
