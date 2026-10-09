# Arquitectura

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 (PHP 8.3), autenticación con Fortify |
| Frontend | React 19 + TypeScript, Inertia.js 3, Tailwind CSS, componentes shadcn/ui (Radix) |
| Base de datos | MySQL (base `sieg`: tablas nuevas + tablas de egresos del sistema anterior) |
| Permisos | `spatie/laravel-permission` |
| PDF | `barryvdh/laravel-dompdf` (vistas Blade en `resources/views/**/pdf.blade.php`) |
| Excel | `phpoffice/phpspreadsheet` (importación del presupuesto) |
| Rutas tipadas | Laravel Wayfinder (`resources/js/routes`, generado) |

La app es 100 % Inertia: el controlador responde con `Inertia::render('modulo/Pagina', [...props])` y React pinta la página.

SIEG nació como copia de **SIEMG** (`C:\laragon\www\siemg`), del que se quitaron los módulos de ingresos. Las convenciones son las mismas en los dos proyectos.

## Estructura de carpetas

```
app/
  Console/Commands/      SyncPermissions
  Http/Controllers/      Presupuesto, Requisiciones, Órdenes de compra, Proveedores,
                         Modificaciones presupuestales, Secretarías, Dashboard, Settings, Tickets
  Http/Middleware/       HandleInertiaRequests: props compartidos (usuario, permisos, flash)
  Models/                Modelos (muchos apuntan a tablas tb_egreso_* / cat_egreso_*)
  Services/              PresupuestoService
resources/
  js/pages/{modulo}/     Páginas React
  js/components/         Sidebar, SearchableSelect, LineaPresupuestoCeldas, badges…
  js/lib/presupuesto.ts  Tipos y formato de dinero/fechas del presupuesto
  views/{modulo}/        Plantillas de los PDF (requisición, orden de compra, modificación)
routes/web.php           Todas las rutas, con su permiso
```

## Backend

**Sin capa de repositorios.** Cada controlador valida, aplica reglas y escribe. La regla compartida vive en `app/Services/PresupuestoService.php`: saldos por línea de presupuesto (asignado, modificado, vigente, comprometido, en trámite, disponible), validación de suficiencia de una requisición y las unidades que puede ver cada usuario.

**Permisos en la ruta.** Cada ruta de `routes/web.php` lleva `->middleware('permission:…')`. No usar `$this->middleware()` en constructores (el `Controller` base de Laravel 13 no lo tiene). Todo permiso nuevo se agrega también a `SyncPermissions` (ver [Permisos](permisos.md)).

**Catálogos genéricos.** `app/Catalogos/` declara cada catálogo (`Catalogos.php`) con sus campos (`Campo`: texto, entero, fecha, booleano, selección, relación, calculado). De esa definición salen la pantalla `pages/catalogos/Catalogo.tsx`, la validación, la plantilla e importación (`Importador`), el historial y los permisos; `CatalogoController` atiende todos. Para un catálogo nuevo basta el modelo (con el trait `Auditable`), su migración y la entrada en `Catalogos.php`. Las tablas del sistema anterior no tienen AUTO_INCREMENT: el modelo lleva `$incrementing = false` y `Catalogo::crear()` calcula el siguiente id; las columnas obligatorias que no se capturan van en `valoresFijos`.

**Auditoría.** El trait `App\Models\Concerns\Auditable` escribe en `auditoria` cada alta, modificación, baja/reactivación (cambio de `activo`) y eliminación, con los valores anterior y nuevo. `Auditoria::enLote()` agrupa los cambios de una importación. Los `update()` masivos (`whereIn(...)->update()`) no generan eventos y no quedan auditados.

**Transacciones.** Requisición con sus conceptos, validación de suficiencia, orden de compra y autorización de modificaciones van dentro de `DB::transaction()`; la suficiencia y los folios usan `lockForUpdate()` para que dos usuarios no comprometan el mismo saldo ni repitan folio.

**Tablas legadas con llaves obligatorias.** `tb_egreso_requisicion` y su detalle exigen llaves a catálogos del sistema anterior (`tb_egreso_presupuesto`, `cat_area_x_nombre_y_puesto`, `tb_usuarios`, `cat_egreso_producto`…). El controlador llena esas columnas con el id `1` de cada catálogo; ese registro debe existir.

**PDF.** `Pdf::loadView(...)->stream()`. La app tiene `APP_LOCALE=en`: las fechas con nombre de mes se formatean con `->locale('es')`.

**Zona horaria.** Laravel corre en **UTC** y MySQL guarda hora local (UTC−6). Las columnas legadas con `DEFAULT CURRENT_TIMESTAMP` (como `registro`) quedan en hora local; las que llena Laravel (`created_at`, `now()`) en UTC. En pantalla se convierten con `fechaLocal()` y en los PDF con `->setTimezone('America/Mexico_City')`.

## Frontend

**Layout automático** (`resources/js/app.tsx`): `auth/*` → `AuthLayout`, `settings/*` → `AppLayout` + `SettingsLayout`, lo demás → `AppLayout`. Las páginas no se envuelven en `<AppLayout>`; las migas de pan se pasan con:

```tsx
Index.layout = { breadcrumbs };
```

**Convención de páginas:** carpeta en minúsculas, archivo en PascalCase (`pages/requisiciones/Show.tsx` ↔ `Inertia::render('requisiciones/Show')`).

**Rutas tipadas (Wayfinder):** `resources/js/routes` y `resources/js/actions` se generan (están en `.gitignore`). Con `npm run dev` se regeneran solas; si no, `php artisan wayfinder:generate`.

**Permisos en pantalla:** `usePage().props.userPermissions`. El menú (`components/app-sidebar.tsx` y `nav-admin.tsx`) filtra cada entrada por `permission`.

**Mensajes:** `redirect()->with('success' | 'error', '…')` se muestra como notificación (`hooks/use-flash-toast.ts`).

**Componentes propios:**

| Componente | Uso |
|---|---|
| `SearchableSelect` | Select con buscador. Con `inline` dentro de un `Dialog` |
| `LineaPresupuestoCeldas` | Columnas Partida / Proyecto / Fuente encadenadas; solo ofrece líneas con disponible |
| `SuficienciaBadge`, `EstadoModificacionBadge` | Etiquetas de estado |

**React Compiler:** dentro de un handler, acceder a una propiedad de algo que puede ser `null` truena al pintar aunque el handler no se ejecute; usar `?.`.
