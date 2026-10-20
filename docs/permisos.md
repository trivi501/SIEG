# Permisos y roles

El control de acceso usa `spatie/laravel-permission` en dos niveles: la **ruta** exige un permiso (sin él responde 403) y la **pantalla** muestra menús y botones según `usePage().props.userPermissions`.

Además, los datos se acotan:

| Módulo | Regla |
|---|---|
| Requisiciones, Ejercicio y control del gasto, Panel | Solo la secretaría o dirección del usuario. Admin y Super Admin ven todo |
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
| Bandeja de Recursos Materiales | `recursos-materiales-index` (solo Admin y el rol Recursos Materiales). Los proveedores (`proveedores-*`, catálogo) también son solo de Recursos Materiales |
| Ejercicio y control del gasto | `gasto-index` (solo Admin y Control Presupuestal; también muestra los montos del presupuesto en el Panel) |
| Presupuesto | `presupuesto-index`, `-create` (nueva partida), `-edit`, `-import`, `-delete`, `presupuesto-correr-saldos` (correr saldos a mano) |
| Requisiciones | `requisiciones-index`, `-create`, `-edit`, `-delete`, `requisiciones-suficiencia`, `requisiciones-clc` (el área genera la CLC de una orden con factura), `requisiciones-extemporaneas` (Subdirección Administrativa: autoriza las capturadas fuera del periodo), `requisiciones-fecha` (cambia la fecha de trámite de cualquier requisición; el enlace de cada secretaría puede cambiar la de las suyas con `requisiciones-create`), `requisiciones-cotizar` (Recursos Materiales: *Recibido – Iniciar cotización* y editar la cotización), `requisiciones-devolver` (Recursos Materiales regresa al área una requisición en RM o en cotización, p. ej. sin stock, para que la modifique), `facturas-index` (pestaña Facturas), `vehiculos-mantenimiento` (taller: recibir y entregar vehículos y hacer su requisición a nombre de la secretaría de resguardo), `almacen-index` (bandeja e inventario del almacén y sus formatos), `almacen-entradas`, `almacen-salidas`, `ordenes-compra-almacen` (Recursos Materiales asigna la orden al almacén), `requisiciones-cancelar` (cancela cualquier requisición sin orden de compra vigente; con orden, primero se cancelan la CLC y la orden; el enlace cancela las suyas mientras estén pendientes o rechazadas), `ordenes-compra-refacturar` (Admin: refactura de la orden; cancela la CLC y genera otra al cargar el nuevo XML), `ordenes-compra-cancelar` (cancela la orden de compra con su factura, ya sin CLC vigente), `clc-cancelar` (cancela la CLC según el paso: el área hasta que la recibe RM, RM hasta que la recibe CP, CP hasta que la recibe Sindicatura; Admin siempre) |
| Trámite de CLC | `clc-index` (ver la bandeja), `clc-recursos-materiales`, `clc-control-presupuestal`, `clc-sindicatura`, `clc-egresos` (actuar como cada área); el pago es `ordenes-compra-pagar` |
| Órdenes de compra y proveedores | `ordenes-compra-create`, `ordenes-compra-pagar` (registrar pago), `ordenes-compra-fecha` (corregir la fecha de la orden), `ordenes-compra-verificar-sat` (volver a consultar la factura en el SAT), `proveedores-index`, `-create`, `-edit`, `-delete`, `-import` |
| Catálogos | `catalogos-{slug}-index`, `-create`, `-edit`, `-delete` (dar de baja / reactivar), `-import`; p. ej. `catalogos-vehiculos-edit`. Slugs en [Módulos](modulos.md#catálogos-generales) |
| Bitácora de auditoría | `auditoria-index` |
| Modificaciones presupuestales | `modificaciones-index`, `modificaciones-create`, `modificaciones-autorizar` |
| Secretarías y direcciones | `catalogos-unidades-administrativas-*` (los antiguos `secretarias-*` ya no se usan) |
| Tickets de soporte | `tickets-index`, `tickets-create`, `tickets-update` |
| Logs | `logs-view` |
| Usuarios / Roles / Permisos | `users-*`, `roles-*`, `permisos-*` (`index`, `create`, `edit`, `delete`) |

## Perfiles sugeridos

Crear estos roles en **Administración → Roles**:

| Rol | Permisos |
|---|---|
| Área solicitante | `requisiciones-index`, `requisiciones-create`, `requisiciones-edit`, `requisiciones-clc`, `modificaciones-index`, `modificaciones-create` |
| Recursos Materiales | `requisiciones-index`, `requisiciones-edit`, `requisiciones-suficiencia`, `requisiciones-fecha`, `ordenes-compra-create`, `ordenes-compra-fecha`, `proveedores-*` |
| Jefe de Control Presupuestal | `modificaciones-index`, `modificaciones-autorizar`, `requisiciones-index`, `requisiciones-fecha`, `ordenes-compra-fecha` |
| Subdirección Administrativa (Adquisiciones) | `requisiciones-index`, `requisiciones-extemporaneas` |
| Sindicatura | `clc-index`, `clc-sindicatura` |
| Egresos | `clc-index`, `clc-egresos`, `ordenes-compra-pagar` |

Estos roles del trámite (y Área solicitante, Recursos Materiales y Control Presupuestal con sus permisos) los crea `php artisan clc:usuarios-prueba --solo-roles`; sin `--solo-roles` crea además un usuario de prueba por rol. **Control Presupuestal** (además de Admin) administra usuarios, roles y permisos (`users-*`, `roles-*`, `permisos-index`).
| Control Vehicular | `catalogos-vehiculos-*` |
| Consulta de catálogos | `catalogos-*-index` de los catálogos que deba ver |

La validación de requisiciones en revisión (botones *Válida / No válida*) solo se muestra a Admin y Super Admin; la ruta exige `requisiciones-edit`, así que la restricción por rol es solo en pantalla.

## Roles heredados de SIEMG

La base `sieg` se copió con los roles de SIEMG (Catastro, Cajero, Ventanilla, Contabilidad, Catastro Solo Impresión, Auxiliar Jefe Cajas). Los permisos de ingresos se eliminaron de la base `sieg`, así que esos roles quedaron vacíos o casi vacíos. Conviene revisar a qué usuarios asignarles los roles de egresos y borrar los que no se usen.
