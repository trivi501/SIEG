# Permisos y roles

El control de acceso usa `spatie/laravel-permission` en dos niveles: la **ruta** exige un permiso (sin él responde 403) y la **pantalla** muestra menús y botones según `usePage().props.userPermissions`.

Además, los datos se acotan:

| Módulo | Regla |
|---|---|
| Requisiciones, Cómo va el gasto, Panel | Solo las unidades administrativas de la secretaría del usuario. Admin y Super Admin ven todo |
| Modificaciones presupuestales | Solo líneas de las unidades del usuario; quien tiene `modificaciones-autorizar` ve todas |
| Recursos Materiales | Ve requisiciones de todas las secretarías |
| Presupuesto (`/presupuesto`) | Solo roles Admin y Super Admin, además del permiso |

## Fuente de verdad: `permissions:sync`

La lista de permisos está en `app/Console/Commands/SyncPermissions.php`:

```bash
php artisan permissions:sync
```

Crea los permisos que falten y asigna **todos** a **Super Admin** y **Admin**. Los demás roles se administran en **Administración → Roles**; el comando no los toca.

Para agregar un permiso: protege la ruta (`->middleware('permission:modulo-accion')`), agrega el nombre al arreglo del comando, corre `permissions:sync` en cada entorno y usa el mismo nombre en el menú o en `userPermissions.includes(...)`.

Los permisos de los **catálogos** no se escriben a mano: el comando los toma del registro `app/Catalogos/Catalogos.php` (cinco por catálogo) y les pone nombre legible y la categoría *Catálogos* para la pantalla de Roles. Sus rutas son compartidas (`/catalogos/{catalogo}`) y las protege el middleware `catalogo:<acción>`, que exige `{prefijo}-<acción>` del catálogo de la URL.

## Catálogo de permisos

| Módulo | Permisos |
|---|---|
| Presupuesto | `presupuesto-index`, `-edit`, `-import`, `-delete` |
| Requisiciones | `requisiciones-index`, `-create`, `-edit`, `-delete`, `requisiciones-suficiencia` |
| Órdenes de compra y proveedores | `ordenes-compra-create`, `proveedores-index`, `-create`, `-edit`, `-delete`, `-import` |
| Catálogos | `catalogos-{slug}-index`, `-create`, `-edit`, `-delete` (dar de baja / reactivar), `-import`; p. ej. `catalogos-vehiculos-edit`. Slugs en [Módulos](modulos.md#catálogos-generales) |
| Bitácora de auditoría | `auditoria-index` |
| Modificaciones presupuestales | `modificaciones-index`, `modificaciones-create`, `modificaciones-autorizar` |
| Secretarías | `secretarias-index`, `-create`, `-edit`, `-delete` |
| Tickets de soporte | `tickets-index`, `tickets-create`, `tickets-update` |
| Logs | `logs-view` |
| Usuarios / Roles / Permisos | `users-*`, `roles-*`, `permisos-*` (`index`, `create`, `edit`, `delete`) |

## Perfiles sugeridos

Crear estos roles en **Administración → Roles**:

| Rol | Permisos |
|---|---|
| Área solicitante | `requisiciones-index`, `requisiciones-create`, `requisiciones-edit`, `modificaciones-index`, `modificaciones-create` |
| Recursos Materiales | `requisiciones-index`, `requisiciones-edit`, `requisiciones-suficiencia`, `ordenes-compra-create`, `proveedores-*` |
| Jefe de Control Presupuestal | `modificaciones-index`, `modificaciones-autorizar` |
| Control Vehicular | `catalogos-vehiculos-*` |
| Consulta de catálogos | `catalogos-*-index` de los catálogos que deba ver |

La validación de requisiciones en revisión (botones *Válida / No válida*) solo se muestra a Admin y Super Admin; la ruta exige `requisiciones-edit`, así que la restricción por rol es solo en pantalla.

## Roles heredados de SIEMG

La base `sieg` se copió con los roles de SIEMG (Catastro, Cajero, Ventanilla, Contabilidad, Catastro Solo Impresión, Auxiliar Jefe Cajas). Los permisos de ingresos se eliminaron de la base `sieg`, así que esos roles quedaron vacíos o casi vacíos. Conviene revisar a qué usuarios asignarles los roles de egresos y borrar los que no se usen.
