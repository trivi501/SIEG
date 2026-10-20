<?php

namespace App\Console\Commands;

use App\Models\EgresoUnidadAdministrativa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles del trámite de requisiciones y CLC con sus permisos y un usuario de prueba por rol
 * ({rol}.prueba@sieg.test). La contraseña se genera y se guarda en storage/app/private.
 * Solo Control Presupuestal (y Admin) administra usuarios, roles y permisos.
 */
class UsuariosPruebaClc extends Command
{
    protected $signature = 'clc:usuarios-prueba
        {--unidad=310 : Clave de la secretaría del usuario del área solicitante}
        {--solo-roles : Solo crea o completa los roles, sin usuarios de prueba}
        {--force : Permite correrlo en producción}';

    protected $description = 'Crea los roles del trámite (Área, Recursos Materiales, Control Presupuestal, Sindicatura, Egresos) y sus usuarios de prueba';

    /**
     * Permisos exclusivos: se quitan a los roles del trámite que no los tengan en su lista. Presupuesto,
     * gasto y autorizar modificaciones son de Control Presupuestal; la bandeja de Recursos Materiales y
     * los proveedores, solo del rol Recursos Materiales (Admin siempre tiene todo).
     */
    private const SOLO_ADMIN_Y_CONTROL = [
        'modificaciones-autorizar',
        'recursos-materiales-index', 'proveedores-index', 'proveedores-create', 'proveedores-edit', 'proveedores-delete', 'proveedores-import',
    ];

    /** Rol => [correo de prueba, permisos]. */
    public const ROLES = [
        'Área solicitante' => ['area', [
            'requisiciones-index', 'requisiciones-create', 'requisiciones-edit', 'requisiciones-clc',
            'modificaciones-index', 'modificaciones-create', 'clc-index', 'clc-cancelar', 'facturas-index',
            'presupuesto-index', 'gasto-index', // solo consulta, de las partidas de su unidad
        ]],
        'Recursos Materiales' => ['recursos.materiales', [
            'requisiciones-index', 'requisiciones-edit', 'requisiciones-cotizar', 'requisiciones-devolver', 'requisiciones-suficiencia', 'requisiciones-fecha', 'requisiciones-cancelar',
            'recursos-materiales-index', 'ordenes-compra-create', 'ordenes-compra-fecha', 'ordenes-compra-verificar-sat', 'ordenes-compra-cancelar', 'proveedores-index', 'proveedores-create', 'proveedores-edit',
            'clc-index', 'clc-recursos-materiales', 'clc-cancelar', 'facturas-index', 'ordenes-compra-almacen', 'almacen-index',
        ]],
        'Control Presupuestal' => ['control.presupuestal', [
            // Requisiciones de todas las áreas (la cotización y la bandeja son de Recursos Materiales).
            'requisiciones-index', 'requisiciones-create', 'requisiciones-edit', 'requisiciones-delete', 'requisiciones-suficiencia',
            'requisiciones-clc', 'requisiciones-extemporaneas', 'requisiciones-fecha', 'requisiciones-cancelar', 'ordenes-compra-fecha', 'ordenes-compra-verificar-sat', 'ordenes-compra-cancelar', 'clc-cancelar',
            'presupuesto-index', 'gasto-index', 'modificaciones-index', 'modificaciones-create', 'modificaciones-autorizar', 'auditoria-index',
            'clc-index', 'clc-control-presupuestal', 'facturas-index', 'almacen-index',
            // Administra usuarios, roles y permisos.
            'users-index', 'users-create', 'users-edit', 'roles-index', 'roles-create', 'roles-edit', 'permisos-index',
            'catalogos-regidores-index', 'catalogos-regidores-create', 'catalogos-regidores-edit', 'catalogos-regidores-delete',
        ]],
        'Sindicatura' => ['sindicatura', ['clc-index', 'clc-sindicatura', 'facturas-index']],
        'Egresos' => ['egresos', ['clc-index', 'clc-egresos', 'ordenes-compra-pagar', 'ordenes-compra-verificar-sat', 'facturas-index']],
        'Almacén' => ['almacen', ['almacen-index', 'almacen-entradas', 'almacen-salidas']],
        'Taller vehicular' => ['taller', ['vehiculos-mantenimiento', 'requisiciones-index', 'requisiciones-create', 'catalogos-vehiculos-index']],
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('En producción usa --force (y de preferencia --solo-roles).');

            return self::FAILURE;
        }

        foreach (self::ROLES as $rol => [, $permisos]) {
            $faltan = array_diff($permisos, Permission::whereIn('name', $permisos)->pluck('name')->all());
            if ($faltan) {
                $this->error('Faltan permisos (corre permissions:sync): '.implode(', ', $faltan));

                return self::FAILURE;
            }

            // Agrega los que falten sin quitar los que el rol ya tenga, salvo presupuesto y ejercicio
            // del gasto, que solo son de Admin y Control Presupuestal.
            $role = Role::findOrCreate($rol, 'web');
            $role->givePermissionTo($permisos);
            foreach (array_diff(self::SOLO_ADMIN_Y_CONTROL, $permisos) as $permiso) {
                if ($role->hasPermissionTo($permiso)) {
                    $role->revokePermissionTo($permiso);
                }
            }
            $this->info("Rol {$rol}: ".count($permisos).' permisos.');
        }

        if ($this->option('solo-roles')) {
            return self::SUCCESS;
        }

        $unidad = EgresoUnidadAdministrativa::where('clave', $this->option('unidad'))->orderByDesc('año')->first();
        if (! $unidad) {
            $this->error("No existe la secretaría con clave {$this->option('unidad')}.");

            return self::FAILURE;
        }

        $contraseña = Str::password(14, symbols: false);
        $lineas = ['Usuarios de prueba del trámite de requisiciones y CLC ('.now('America/Mexico_City')->format('d/m/Y H:i').')', "Contraseña de todos: {$contraseña}", ''];

        foreach (self::ROLES as $rol => [$correo]) {
            $email = "{$correo}.prueba@sieg.test";
            $user = User::firstOrNew(['email' => $email]);
            $user->fill([
                'name' => "Prueba {$rol}",
                'password' => $contraseña,
                'id_cat_egreso_unidad_administrativa' => $rol === 'Área solicitante' ? $unidad->id_cat_egreso_unidad_administrativa : null,
            ]);
            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$rol]);

            $lineas[] = str_pad($rol, 22).$email.($rol === 'Área solicitante' ? "  ({$unidad->clave} {$unidad->nombre})" : '');
            $this->line("  {$email} → {$rol}");
        }

        $archivo = 'usuarios-prueba-clc.txt';
        Storage::disk('local')->put($archivo, implode(PHP_EOL, $lineas).PHP_EOL);
        $this->info('Contraseña guardada en '.Storage::disk('local')->path($archivo));

        return self::SUCCESS;
    }
}
