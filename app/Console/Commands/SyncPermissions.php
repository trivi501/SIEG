<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

            // Órdenes de compra y proveedores (Recursos Materiales)
            'ordenes-compra-create',
            'proveedores-index', 'proveedores-create', 'proveedores-edit', 'proveedores-delete',

            // Modificaciones presupuestales
            'modificaciones-index', 'modificaciones-create', 'modificaciones-autorizar',

            // Usuarios, roles y permisos (settings)
            'users-index', 'users-create', 'users-edit', 'users-delete',
            'roles-index', 'roles-create', 'roles-edit', 'roles-delete',
            'permisos-index', 'permisos-create', 'permisos-edit', 'permisos-delete',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
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
