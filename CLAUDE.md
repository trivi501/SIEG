# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción general

SIEG es el sistema de **egresos** del Ayuntamiento de Guadalupe, Zacatecas: presupuesto de egresos, requisiciones, Recursos Materiales (cotización y suficiencia presupuestal), órdenes de compra con factura XML y modificaciones presupuestales. Es un monolito **Laravel 13 + Inertia.js + React 19 (TypeScript)**.

Se separó de **SIEMG** (`C:\laragon\www\siemg`, sistema de ingresos: predial, cajas, cobro) el 2026-10-08: es una copia de ese proyecto a la que se le quitaron los módulos de ingresos. Usa su propia base de datos **`sieg`** (copia de las tablas de egresos, usuarios, roles, permisos, secretarías y catálogos requeridos). Las convenciones de código son las mismas que en SIEMG. La documentación funcional está en `docs/`.

## Comandos

```bash
composer dev               # servidor (http://localhost:8001), cola, logs y Vite (5174)
npm run build              # build de producción
npm run lint               # eslint --fix
npm run format             # prettier --write resources/
npm run types:check        # tsc --noEmit
composer lint              # pint (fix)
php artisan test           # PHPUnit (SQLite en memoria)
php artisan permissions:sync
php artisan wayfinder:generate
```

SIEG usa los puertos 8001/5174 para correr junto a SIEMG (8000/5173). En el mismo navegador hay que abrirlos con hosts distintos (`localhost` vs `127.0.0.1`) o la cookie `XSRF-TOKEN` de uno pisa la del otro (errores 419).

**Permisos** — la fuente de verdad es el arreglo de `app/Console/Commands/SyncPermissions.php`. `permissions:sync` crea los que falten y asigna todos a **Super Admin** y **Admin**; los demás roles (Área, Recursos Materiales, Jefe de Control Presupuestal…) se administran desde la pantalla de Roles. Todo permiso nuevo en una ruta debe agregarse al comando y sincronizarse.

**Wayfinder** — `resources/js/routes/` y `resources/js/actions/` son generados (en `.gitignore`); con `npm run dev` se regeneran solos.

## Arquitectura

### Backend

- Controladores en `app/Http/Controllers` concentran validación, reglas y escritura. Los saldos del presupuesto (asignado, modificado, vigente, comprometido, en trámite, disponible), la validación de suficiencia y las unidades visibles por usuario están en `app/Services/PresupuestoService.php`.
- `routes/web.php` es el único archivo de rutas de negocio; cada ruta lleva `->middleware('permission:...')`. **No usar `$this->middleware()` en constructores** (el `Controller` base de Laravel 13 no lo tiene).
- Esquema híbrido: tablas nuevas Laravel (`proveedores`, `ordenes_compra`, `modificaciones_presupuestales`, `secretarias`) y tablas legadas `tb_egreso_*` / `cat_egreso_*` / `presupuesto 2024` (nombre con espacio). `tb_egreso_requisicion` exige llaves a catálogos legados; el controlador usa el id `1` de cada uno.
- Una requisición solo es visible para usuarios cuya secretaría tenga asignada la unidad administrativa (`cat_egreso_unidad_administrativa.secretaria_id`); Admin ve todo.
- Las migraciones **nunca borran información**, se corren por `--path` en servidores, y las que alteran tablas legadas empiezan con `if (! Schema::hasTable(...)) return;` (no existen en SQLite de pruebas).
- PDF con `barryvdh/laravel-dompdf`; fechas con mes en texto usan `->locale('es')` porque `APP_LOCALE=en`.
- Laravel corre en UTC; MySQL guarda hora local. Columnas de Laravel se muestran con `fechaLocal()` en el frontend y `setTimezone('America/Mexico_City')` en los PDF.

### Frontend

- Layout automático en `resources/js/app.tsx` (`auth/*` → AuthLayout, `settings/*` → AppLayout + SettingsLayout, resto → AppLayout). **Las páginas no se envuelven en `<AppLayout>`**: las migas de pan se pasan con `Pagina.layout = { breadcrumbs }`.
- Páginas: carpeta en minúsculas + archivo PascalCase (`pages/requisiciones/Show.tsx`).
- Permisos en pantalla: `usePage().props.userPermissions`.
- Componentes propios: `SearchableSelect` (usar `inline` dentro de un `Dialog`), `LineaPresupuestoCeldas` (Partida / Proyecto / Fuente encadenadas), `SuficienciaBadge`, `EstadoModificacionBadge`; utilidades en `lib/presupuesto.ts`.
- El proyecto compila con React Compiler: dentro de handlers usar `?.` sobre valores que pueden ser `null`.

### Testing

PHPUnit sobre SQLite en memoria; solo hay pruebas de autenticación, ajustes y del panel. Los módulos de egresos no tienen pruebas automatizadas.
