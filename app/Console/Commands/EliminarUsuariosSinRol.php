<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Elimina los usuarios que no tienen ningún rol (los de ingresos que llegaron con la copia de SIEMG).
 * Antes guarda un respaldo JSON en storage/app/private/respaldos/ y conserva a cualquiera que tenga
 * historial (requisiciones, órdenes, modificaciones, tickets o bitácora): borrarlo perdería esa
 * referencia o, en tickets, los borraría en cascada.
 */
class EliminarUsuariosSinRol extends Command
{
    protected $signature = 'usuarios:eliminar-sin-rol
        {--pretend : Solo muestra a quién eliminaría}
        {--force : No pide confirmación}';

    protected $description = 'Elimina los usuarios sin ningún rol (con respaldo previo)';

    public function handle(): int
    {
        $candidatos = User::doesntHave('roles')->orderBy('id')->get();

        if ($candidatos->isEmpty()) {
            $this->info('No hay usuarios sin rol.');

            return Command::SUCCESS;
        }

        [$eliminar, $conservar] = $candidatos->partition(fn (User $u) => $this->referencias($u->id) === []);

        if ($conservar->isNotEmpty()) {
            $this->warn("Se conservan {$conservar->count()} usuarios sin rol porque tienen historial:");
            $this->table(['Id', 'Nombre', 'Correo', 'Referencias'], $conservar->map(fn (User $u) => [
                $u->id, $u->name, $u->email, implode(', ', $this->referencias($u->id)),
            ]));
        }

        if ($eliminar->isEmpty()) {
            $this->info('No hay usuarios que se puedan eliminar.');

            return Command::SUCCESS;
        }

        $this->table(['Id', 'Nombre', 'Correo'], $eliminar->map(fn (User $u) => [$u->id, $u->name, $u->email]));
        $this->line('Usuarios totales: '.User::count()." · se eliminarían: {$eliminar->count()}");

        if ($this->option('pretend')) {
            return Command::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("¿Eliminar estos {$eliminar->count()} usuarios? No se puede deshacer (queda un respaldo).")) {
            $this->info('Cancelado.');

            return Command::SUCCESS;
        }

        $ids = $eliminar->pluck('id')->all();

        // Respaldo con todas las columnas (incluida la contraseña cifrada) para poder restaurar.
        $ruta = 'respaldos/usuarios_sin_rol_'.now('America/Mexico_City')->format('Ymd_His').'.json';
        Storage::disk('local')->put($ruta, json_encode([
            'fecha' => now()->toIso8601String(),
            'base' => DB::connection()->getDatabaseName(),
            'users' => DB::table('users')->whereIn('id', $ids)->get(),
            'model_has_permissions' => DB::table('model_has_permissions')->whereIn('model_id', $ids)->where('model_type', User::class)->get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        DB::transaction(function () use ($ids) {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('model_id', $ids)->where('model_type', User::class)->delete();
            DB::table('password_reset_tokens')->whereIn('email', User::whereIn('id', $ids)->pluck('email'))->delete();
            User::whereIn('id', $ids)->delete();
        });

        $this->info(count($ids).' usuarios eliminados. Respaldo: '.Storage::disk('local')->path($ruta));
        $this->line('Quedan '.User::count().' usuarios.');

        return Command::SUCCESS;
    }

    /** @return string[] dónde aparece el usuario */
    private function referencias(int $id): array
    {
        $tablas = [
            'tb_egreso_requisicion' => ['id_user_laravel', 'suficiencia_user_id'],
            'ordenes_compra' => ['user_id', 'xml_user_id'],
            'modificaciones_presupuestales' => ['solicitante_id', 'autorizador_id'],
            'support_tickets' => ['user_id', 'assigned_to'],
            'ticket_comments' => ['user_id'],
            'auditoria' => ['user_id'],
        ];
        $encontradas = [];

        foreach ($tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna) && DB::table($tabla)->where($columna, $id)->exists()) {
                    $encontradas[] = "{$tabla}.{$columna}";
                }
            }
        }

        return $encontradas;
    }
}
