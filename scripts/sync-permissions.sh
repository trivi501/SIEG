#!/usr/bin/env bash
#
# Sincroniza roles y permisos en el servidor de producción/staging.
# Uso: ./scripts/sync-permissions.sh
#
# Qué hace:
#   1. Verifica que se ejecuta desde la raíz del proyecto (busca artisan).
#   2. Corre `php artisan permissions:sync`, que:
#      - crea/actualiza las filas de la tabla `permissions` a partir del
#        arreglo hardcodeado en app/Console/Commands/SyncPermissions.php
#      - asigna el set completo de permisos a Super Admin y Admin
#        (los roles personalizados como "Recursos Materiales"
#        NO se tocan, se administran manualmente desde Ajustes > Roles).
#   3. Limpia caché de config/rutas/vistas para que los cambios de
#      permisos se reflejen de inmediato sin esperar al próximo deploy.
#
# Seguro de correr varias veces (es idempotente) y no borra ni modifica
# roles personalizados ni usuarios.

set -euo pipefail

if [ ! -f "artisan" ]; then
    echo "Error: este script debe ejecutarse desde la raíz del proyecto (donde está 'artisan')." >&2
    exit 1
fi

echo "==> Sincronizando permisos y roles..."
php artisan permissions:sync

echo "==> Limpiando caché de configuración, rutas y vistas..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "==> Listo. Permisos sincronizados y caché limpia."
