<?php

namespace App\Console\Commands;

use App\Catalogos\Catalogos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync all module permissions and assign them to Super Admin and Admin';

    public function handle(): int
    {
        $permissions = [
            // Secretarías
            'secretarias-index', 'secretarias-create', 'secretarias-edit', 'secretarias-delete',

            // Logs
            'logs-view',

            // Support Tickets
            'tickets-index', 'tickets-create', 'tickets-update',

            // Presupuesto
            'presupuesto-index', 'presupuesto-edit', 'presupuesto-import', 'presupuesto-delete',

            // Requisiciones
            'requisiciones-index', 'requisiciones-create', 'requisiciones-edit', 'requisiciones-delete', 'requisiciones-suficiencia',

            // Órdenes de compra (Recursos Materiales)
            'ordenes-compra-create',

            // Modificaciones presupuestales
            'modificaciones-index', 'modificaciones-create', 'modificaciones-autorizar',

            // Usuarios, roles y permisos (settings)
            'users-index', 'users-create', 'users-edit', 'users-delete',
            'roles-index', 'roles-create', 'roles-edit', 'roles-delete',
            'permisos-index', 'permisos-create', 'permisos-edit', 'permisos-delete',

            // Bitácora de auditoría
            'auditoria-index',

            // Catálogos generales (incluye proveedores): {prefijo}-index|create|edit|delete|import
            // por cada catálogo de App\Catalogos\Catalogos.
            ...Catalogos::permisos(),
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Nombre legible y categoría de los permisos de catálogos para la pantalla de Roles.
        // (`categoria` no la crea ninguna migración: existe en MySQL pero no en SQLite de pruebas.)
        $acciones = ['index' => 'Ver', 'create' => 'Crear', 'edit' => 'Editar', 'delete' => 'Dar de baja', 'import' => 'Importar'];
        $conCategoria = Schema::hasColumn('permissions', 'categoria');

        foreach (Catalogos::todos() as $catalogo) {
            foreach ($acciones as $accion => $verbo) {
                Permission::where('name', "{$catalogo->prefijoPermiso()}-{$accion}")
                    ->whereNull('nombre_mostrar')
                    ->update(['nombre_mostrar' => "{$verbo}: {$catalogo->titulo}", ...($conCategoria ? ['categoria' => 'Catálogos'] : [])]);
            }
        }

        $this->info(count($permissions).' permissions synced.');

        // Solo los roles administradores reciben todo; los demás (Área, Recursos Materiales,
        // Jefe de Control Presupuestal…) se administran desde Administración > Roles.
        foreach (['Super Admin', 'Admin'] as $rol) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permissions);
            $this->info("{$rol}: all permissions assigned.");
        }

        return Command::SUCCESS;
    }
}
