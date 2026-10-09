# SIEG — Sistema de Egresos

Sistema de gestión del **gasto** del **Ayuntamiento de Guadalupe, Zacatecas**: presupuesto de egresos, requisiciones, suficiencia presupuestal, órdenes de compra con factura XML y modificaciones presupuestales.

Se separó de **SIEMG** (sistema de ingresos: predial, cajas, cobro) el 8 de octubre de 2026. Tiene su propia base de datos (`sieg`); los usuarios, roles y secretarías se copiaron de SIEMG en esa fecha y desde entonces cada sistema administra los suyos.

Monolito **Laravel 13 + Inertia.js + React 19 (TypeScript)** sobre MySQL.

## Documentación

| Documento | Contenido |
|---|---|
| [Instalación](docs/instalacion.md) | Requisitos, entorno local, `.env`, primer arranque |
| [Arquitectura](docs/arquitectura.md) | Estructura del código, convenciones de backend y frontend |
| [Módulos](docs/modulos.md) | Presupuesto, requisiciones, Recursos Materiales, órdenes de compra, modificaciones |
| [Permisos y roles](docs/permisos.md) | Permisos, perfiles sugeridos y cómo agregar uno nuevo |
| [Base de datos](docs/base-de-datos.md) | Tablas, reglas y política de migraciones |
| [Operación y despliegue](docs/operacion-y-despliegue.md) | Pasos para producción y diagnóstico |

## Arranque rápido (desarrollo)

```bash
composer install
npm install
cp .env.example .env      # DB_DATABASE=sieg (ver docs/instalacion.md)
php artisan key:generate
php artisan permissions:sync
composer dev              # http://localhost:8001
```

SIEG usa el puerto **8001** (Vite 5174) para poder correr al mismo tiempo que SIEMG (8000 / 5173).

## Comandos más usados

| Comando | Para qué |
|---|---|
| `composer dev` | Servidor, cola, visor de logs y Vite |
| `npm run build` | Compila el frontend para producción |
| `php artisan permissions:sync` | Sincroniza permisos y los asigna a Super Admin y Admin |
| `php artisan wayfinder:generate` | Regenera las rutas tipadas del frontend |
| `npm run types:check` | Revisión de tipos TypeScript |
| `php artisan test` | Pruebas de PHPUnit |
