# Instalación

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.3 o superior, con extensiones `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `dom`, `intl` |
| Composer | 2.x |
| Node.js | 20 o superior (con npm) |
| MySQL | 8 recomendado |

En Windows el entorno de desarrollo es **Laragon** (`C:\laragon\www\SIEG`).

## 1. Dependencias

```bash
composer install
npm install
```

## 2. Archivo `.env`

```bash
cp .env.example .env
php artisan key:generate
```

```dotenv
APP_NAME=SIEG
APP_URL=http://localhost:8001

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sieg
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
```

SIEG debe tener su **propio `APP_KEY`** (no copiar el de SIEMG).

## 3. Base de datos

La base `sieg` se creó copiando de la base de SIEMG las tablas de egresos (`tb_egreso_*`, `cat_egreso_*`, `presupuesto 2024`, `proveedores`, `ordenes_compra`, `modificaciones_presupuestales`), los usuarios, roles, permisos y secretarías, y los catálogos que las llaves foráneas de egresos exigen (`cat_pais`, `cat_estado`, `cat_municipio`, `cat_areas`, `cat_tipo_area`, `cat_tipo_usuario`, `tb_usuarios`, `cat_banco`, `cat_area_x_nombre_y_puesto`). **Varias de esas tablas no las crea ninguna migración**, así que un entorno nuevo parte de un respaldo:

```bash
mysql -u root -e "CREATE DATABASE sieg CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci"
mysql -u root sieg < respaldo_sieg.sql
```

Después, migraciones pendientes **una por una, por ruta** (ver [Base de datos](base-de-datos.md#política-de-migraciones)):

```bash
php artisan migrate:status
php artisan migrate --path=database/migrations/NOMBRE.php --pretend
php artisan migrate --path=database/migrations/NOMBRE.php
php artisan permissions:sync
```

## 4. Arrancar en desarrollo

```bash
composer dev
```

| Proceso | Puerto / función |
|---|---|
| `php artisan serve` | `http://localhost:8001` |
| `php artisan queue:listen` | Worker de la cola |
| `php artisan pail` | Logs en vivo |
| `npm run dev` | Vite en el puerto 5174, recarga en caliente y regeneración de rutas tipadas |

> **SIEG y SIEMG abiertos en el mismo navegador:** las cookies se separan por nombre de host, no por puerto. Abre SIEG en `http://localhost:8001` y SIEMG en `http://127.0.0.1:8000` (o al revés); si los dos usan el mismo host, la cookie `XSRF-TOKEN` de uno pisa la del otro y aparecen errores 419 al guardar.

## 5. Primer usuario

Los usuarios se administran en **Administración → Usuarios**. En una base vacía:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Administrador', 'email' => 'admin@ejemplo.mx', 'password' => bcrypt('cambiar-esta-contraseña')]);
>>> $u->forceFill(['email_verified_at' => now()])->save();
>>> $u->assignRole('Super Admin');
```

Para que un usuario de un área vea requisiciones y presupuesto, debe tener **secretaría** asignada y la secretaría sus **unidades administrativas** (Secretarías → Editar).

## Verificación

```bash
npm run types:check
php artisan test
```

La suite de PHPUnit corre sobre SQLite en memoria; las migraciones que modifican tablas del sistema anterior se omiten ahí porque esas tablas no existen. Los módulos de egresos no tienen pruebas automatizadas.
