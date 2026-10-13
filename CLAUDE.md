# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción general

SIEG es el sistema de **egresos** del Ayuntamiento de Guadalupe, Zacatecas: presupuesto de egresos, requisiciones, Recursos Materiales (cotización y suficiencia presupuestal), órdenes de compra con factura XML y modificaciones presupuestales. Es un monolito **Laravel 13 + Inertia.js + React 19 (TypeScript)**.

Se separó de **SIEMG** (`C:\laragon\www\siemg`, sistema de ingresos: predial, cajas, cobro) el 2026-10-08: es una copia de ese proyecto a la que se le quitaron los módulos de ingresos. Usa su propia base de datos **`sieg`** (copia de las tablas de egresos, usuarios, roles, permisos, secretarías y catálogos requeridos). Las convenciones de código son las mismas que en SIEMG. La documentación funcional está en `docs/` (`arquitectura.md`, `modulos.md`, `permisos.md`, `base-de-datos.md`, `instalacion.md`, `operacion-y-despliegue.md`); conviene leer el documento del módulo antes de cambiar reglas de negocio.

## Comandos

```bash
composer dev               # servidor (http://localhost:8001), cola, logs y Vite (5174)
npm run build              # build de producción
npm run lint               # eslint --fix
npm run format             # prettier --write resources/
npm run types:check        # tsc --noEmit
composer lint              # pint (fix)
php artisan test           # PHPUnit (SQLite en memoria)
php artisan test --filter=DashboardTest   # una sola prueba / clase
composer test              # config:clear + pint --test + php artisan test
composer ci:check          # eslint, prettier y pint en modo check, tsc y pruebas
php artisan permissions:sync
php artisan catalogos:inicializar --pretend   # sin --pretend agrega fuentes/proyectos/partidas del presupuesto, bancos y ejercicio
php artisan clc:usuarios-prueba          # roles del trámite (Área, RM, Control Presupuestal, Sindicatura, Egresos) + usuarios {rol}.prueba@sieg.test
php artisan presupuesto:correr-saldos   # corre los saldos del mes (manual: no se programa; también con el botón "Correr saldos")
php artisan wayfinder:generate
```

Los scripts `lint:check` / `format:check` (npm) y `lint:check` (composer) validan sin modificar archivos. En servidores, los permisos se sincronizan con `scripts/sync-permissions.sh` (además limpia caché de config, rutas y vistas).

SIEG usa los puertos 8001/5174 para correr junto a SIEMG (8000/5173). En el mismo navegador hay que abrirlos con hosts distintos (`localhost` vs `127.0.0.1`) o la cookie `XSRF-TOKEN` de uno pisa la del otro (errores 419).

**Permisos** — la fuente de verdad es el arreglo de `app/Console/Commands/SyncPermissions.php`. `permissions:sync` crea los que falten y asigna todos a **Super Admin** y **Admin**; los demás roles (Área, Recursos Materiales, Jefe de Control Presupuestal…) se administran desde la pantalla de Roles. Todo permiso nuevo en una ruta debe agregarse al comando y sincronizarse. Excepción: los permisos de catálogos (`{prefijo}-index|create|edit|delete|import`) los genera el comando desde `app/Catalogos/Catalogos.php`.

**Wayfinder** — `resources/js/routes/` y `resources/js/actions/` son generados (en `.gitignore`); con `npm run dev` se regeneran solos.

## Arquitectura

### Backend

- **Saldos mensuales** (`App\Services\SaldosMensuales`): disponible del mes = saldo corrido + asignado del mes ± modificado del mes − comprometido del mes (por `suficiencia_fecha` / `resuelto_at`, en hora de México). Una requisición cancelada libera su comprometido en el mes de `cancelada_at`. Los traspasos solo entre líneas de la misma fuente. La corrida de saldos es **manual** (botón *Correr saldos* o `presupuesto:correr-saldos`; no se programa) y congela en `saldos_mensuales` lo que quedó disponible el mes anterior; los meses sin corrida se calculan. No modifica el calendario de `presupuesto 2024`.
- **Distribución mensual del presupuesto**: en alta, edición e importación de líneas, los 12 meses deben sumar exactamente `IMPORTE_TOTAL` (al centavo, en `App\Services\DistribucionMensual`); las claves de una línea deben existir en los catálogos.
- **Estados de requisición** (`cat_egreso_requisicion_estado`, constantes en `EgresoRequisicion`): 1 Pendiente, 2 En revisión, 3 Aprobada, 4 Rechazada, 5 En Recursos Materiales, 6 En cotización, 7 Comprometida (con suficiencia), 8 Orden de compra, 9 Con factura (XML), 10 CLC generada, 11 Pagada, 12 Cancelada (con motivo; antes de la orden de compra, deja de contar en el presupuesto). Cada paso valida el estado de origen y deja bitácora. Los momentos del gasto no se acumulan: cada importe está solo en comprometido, devengado o pagado.
- **Trámite de CLC** (`App\Services\TramiteClc`, pantalla `/clc`): la CLC pasa área → Recursos Materiales → Control Presupuestal (puede devolver con observaciones) → Sindicatura → Control Presupuestal → Egresos (pago). Las acciones están en `TramiteClc::ACCIONES` (estados de origen, estado nuevo, permiso); el área envía a RM las de su secretaría; solo los envíos CP ↔ Sindicatura crean `ClcRelacion` (PDF) y cada paso un `ClcMovimiento`. El paso actual es `ordenes_compra.clc_estado`; el pago solo procede en `recibida_egresos`.
- Controladores en `app/Http/Controllers` concentran validación, reglas y escritura. Los saldos del presupuesto (asignado, modificado, vigente, comprometido, en trámite, devengado = OC con factura XML, pagado = OC con pago registrado, disponible), la validación de suficiencia y las unidades visibles por usuario están en `app/Services/PresupuestoService.php`.
- `routes/web.php` es el único archivo de rutas de negocio; cada ruta lleva `->middleware('permission:...')`. **No usar `$this->middleware()` en constructores** (el `Controller` base de Laravel 13 no lo tiene). Las rutas compartidas `/catalogos/{catalogo}` usan `->middleware('catalogo:<accion>')` (`PermisoCatalogo`), que exige el permiso del catálogo de la URL.
- **Catálogos genéricos** (`app/Catalogos/`): cada catálogo es una entrada en `Catalogos.php` (modelo + `Campo`s); de ahí salen pantalla (`pages/catalogos/Catalogo.tsx`), validación, importación Excel en dos pasos (`Importador`: vista previa → confirmar), historial y permisos. Un catálogo nuevo no necesita controlador ni página propios. Bajas siempre lógicas (`activo`).
- **Auditoría (todo el sistema)**: todo modelo que se escribe desde la app lleva el trait `Concerns\Auditable` (alta/modificación/baja/reactivación/eliminación → tabla `auditoria`, pantalla `/auditoria`); los roles y permisos de Spatie se observan con `AuditoriaObserver` desde `AppServiceProvider`, igual que los inicios/cierres de sesión y accesos fallidos. Los atributos ocultos (contraseña) se guardan como `[oculto]`. Los cambios de relaciones muchos a muchos (roles de usuario, permisos de rol) se registran con `Auditoria::relacion()`, y `Auditoria::enLote()` agrupa importaciones y borrados masivos. **Los `whereIn(...)->update()/delete()` masivos no se auditan**: en código nuevo recorrer los modelos (`->get()->each(...)`). Un modelo nuevo debe llevar el trait y su etiqueta en `AuditoriaController::MODULOS` (o ser catálogo).
- Tablas `cat_egreso_*` / `cat_banco` no tienen AUTO_INCREMENT: sus modelos llevan `$incrementing = false` y `Catalogo::crear()` calcula el id.
- Esquema híbrido: tablas nuevas Laravel (`proveedores`, `ordenes_compra`, `modificaciones_presupuestales`) y tablas legadas `tb_egreso_*` / `cat_egreso_*` / `presupuesto 2024` (nombre con espacio). `tb_egreso_requisicion` exige llaves a catálogos legados; el controlador usa el id `1` de cada uno.
- **Secretarías y direcciones son un solo catálogo**: `cat_egreso_unidad_administrativa` (`/catalogos/unidades-administrativas`). Cada usuario pertenece a **una** (`users.id_cat_egreso_unidad_administrativa`) y solo ve requisiciones, presupuesto y modificaciones de esa clave (en todos los años); Admin y Control Presupuestal (`modificaciones-autorizar`) ven todo. Las áreas capturan sus modificaciones hasta 3 días antes del fin de mes (`ModificacionPresupuestal::DIAS_CIERRE`); Control Presupuestal siempre. El prefijo de folio de requisiciones está en la unidad (`prefijo`, vacío = `REQ`). La tabla `secretarias` y las columnas `secretaria_id` quedan sin uso (solo historial).
- Las migraciones **nunca borran información**, se corren por `--path` en servidores, y las que alteran tablas legadas empiezan con `if (! Schema::hasTable(...)) return;` (no existen en SQLite de pruebas).
- PDF con `barryvdh/laravel-dompdf`; fechas con mes en texto usan `->locale('es')` porque `APP_LOCALE=en`.
- Laravel corre en UTC; MySQL guarda hora local. Columnas de Laravel se muestran con `fechaLocal()` en el frontend y `setTimezone('America/Mexico_City')` en los PDF.

### Frontend

- Layout automático en `resources/js/app.tsx` (`auth/*` → AuthLayout, `settings/*` → AppLayout + SettingsLayout, resto → AppLayout). **Las páginas no se envuelven en `<AppLayout>`**: las migas de pan se pasan con `Pagina.layout = { breadcrumbs }`.
- Páginas: carpeta en minúsculas + archivo PascalCase (`pages/requisiciones/Show.tsx`).
- Permisos en pantalla: `usePage().props.userPermissions`.
- **Formato de números**: coma para miles y punto para centavos (`1,234,567.89`). Usar `money()` / `numero()` de `lib/presupuesto.ts` (formato fijo `en-US`); nunca `toLocaleString()` sin formato, que depende del idioma del navegador. En PHP, `number_format($n, 2)`.
- Componentes propios: `SearchableSelect` (usar `inline` dentro de un `Dialog`), `DesplazableConMouse` (envuelve un `<Table>` ancho para arrastrarlo con el mouse), `LineaPresupuestoCeldas` (Partida / Proyecto / Fuente encadenadas), `SuficienciaBadge`, `EstadoModificacionBadge`; utilidades en `lib/presupuesto.ts`.
- El proyecto compila con React Compiler: dentro de handlers usar `?.` sobre valores que pueden ser `null`.

### Testing

PHPUnit sobre SQLite en memoria; hay pruebas de autenticación, ajustes, panel, catálogos (`tests/Feature/CatalogosTest.php`) y bitácora (`tests/Feature/AuditoriaSistemaTest.php`). Los catálogos sobre tablas del sistema anterior no existen en SQLite (dan 404), así que las pruebas usan los de tablas nuevas (vehículos, cuentas bancarias, firmantes, ejercicios). Requisiciones, órdenes de compra y modificaciones no tienen pruebas automatizadas.
