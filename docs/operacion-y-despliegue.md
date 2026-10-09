# Operación y despliegue

## Primera instalación en el servidor

SIEG se instala como una aplicación aparte de SIEMG, con su propio dominio (o subdominio), su propia carpeta y su propia base `sieg`.

1. Crear la base `sieg` en el servidor a partir de la base de producción de SIEMG. Mismo procedimiento que se usó en desarrollo: exportar de la base de SIEMG las tablas de egresos, usuarios, roles, permisos, secretarías y catálogos requeridos (la lista está en [Base de datos](base-de-datos.md)), importarlas en `sieg` y eliminar de `sieg` los permisos de ingresos.
2. Clonar el código, `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`.
3. `.env` con `APP_NAME=SIEG`, `APP_URL` del dominio de SIEG, `DB_DATABASE=sieg` y un `APP_KEY` nuevo (`php artisan key:generate`).
4. `php artisan migrate:status` y migraciones pendientes por ruta (ver abajo).
5. `php artisan permissions:sync`.
6. Crear los roles de egresos (ver [Permisos](permisos.md#perfiles-sugeridos)) y asignarlos.
7. Servidor web: instalar el sitio de nginx [`deploy/nginx/sieg.conf`](../deploy/nginx/sieg.conf) (las instrucciones están en el encabezado del archivo) y dar permisos de escritura a PHP-FPM: `sudo chown -R www-data:www-data storage bootstrap/cache`.
8. Quitar los módulos de egresos de SIEMG en producción: actualizar SIEMG a la versión sin egresos.

## Actualizar

```bash
mysqldump -u USUARIO -p --single-transaction sieg > respaldo_sieg_$(date +%Y%m%d_%H%M).sql
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:status
php artisan migrate --path=database/migrations/NOMBRE.php --pretend
php artisan migrate --path=database/migrations/NOMBRE.php
php artisan permissions:sync
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
```

## Procesos

- **Worker de la cola** (`php artisan queue:work`): la app no encola trabajos propios hoy, pero las notificaciones y futuros procesos lo usan; se recomienda dejarlo como servicio.
- SIEG **no tiene tareas programadas**; no requiere el cron del scheduler.

## Archivos generados

| Ruta | Contenido | Respaldar |
|---|---|---|
| `storage/app/private/ordenes-compra/` | Facturas XML de las órdenes de compra | Sí |
| `storage/logs/` | Log de Laravel (consultable en `/logs`) | No |

## Diagnóstico rápido

| Síntoma | Revisar |
|---|---|
| Un menú o pantalla no aparece para nadie | El permiso no está en `SyncPermissions.php` o falta correr `permissions:sync` |
| El área no ve unidades al crear una requisición | El usuario no tiene secretaría o la secretaría no tiene unidades administrativas |
| No aparecen partidas al capturar | La unidad no tiene líneas en el presupuesto importado, o ninguna tiene disponible |
| Errores 419 al guardar en desarrollo | SIEG y SIEMG abiertos con el mismo host; usar `localhost` para uno y `127.0.0.1` para el otro |
| La factura XML se rechaza | El mensaje indica la causa: total distinto de lo cotizado, RFC del emisor distinto al del proveedor, XML sin timbre o UUID ya usado |
