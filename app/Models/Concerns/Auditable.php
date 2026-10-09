<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Support\Arr;

/**
 * Registra en la bitácora de auditoría cada alta, modificación, baja/reactivación (columna `activo`)
 * y eliminación del modelo, con los valores anteriores y nuevos de los campos que cambiaron.
 * Los cambios masivos (`Model::whereIn(...)->update()`) no pasan por aquí.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($modelo) {
            Auditoria::registrar($modelo, 'alta', null, $modelo->valoresAuditables($modelo->getAttributes()));
        });

        static::updated(function ($modelo) {
            $despues = $modelo->valoresAuditables($modelo->getChanges());

            if ($despues === []) {
                return;
            }

            $antes = Arr::only($modelo->getOriginal(), array_keys($despues));
            $accion = 'modificación';

            if (array_keys($despues) === ['activo']) {
                $accion = $despues['activo'] ? 'reactivación' : 'baja';
            }

            Auditoria::registrar($modelo, $accion, $modelo->valoresAuditables($antes), $despues);
        });

        static::deleted(function ($modelo) {
            Auditoria::registrar($modelo, 'eliminación', $modelo->valoresAuditables($modelo->getAttributes()), null);
        });
    }

    protected function valoresAuditables(array $valores): array
    {
        // La llave ya queda en auditable_id.
        $omitir = [...$this->getHidden(), $this->getKeyName(), 'created_at', 'updated_at', 'password', 'remember_token'];

        return collect(Arr::except($valores, $omitir))
            ->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v)
            ->all();
    }
}
