# Base de datos

MySQL, base **`sieg`**. Esquema híbrido: tablas nuevas al estilo Laravel (plural, `id` autoincremental, `created_at`/`updated_at`) y tablas de egresos del sistema anterior (`tb_egreso_*`, `cat_egreso_*`, llaves sin autoincremento, sin timestamps).

La base se creó el 8 de octubre de 2026 copiando de la base de SIEMG (`laravel`) 85 tablas: todo lo de egresos, usuarios, roles, permisos, secretarías y los catálogos que exigen las llaves foráneas. Sesiones, caché, colas, notificaciones y tickets se copiaron vacíos.

## Tablas principales

| Tabla | Contenido |
|---|---|
| `presupuesto 2024` | Presupuesto de egresos importado. **El nombre lleva un espacio** (en SQL crudo va entre comillas invertidas). Línea = `CLAVE` (unidad) + `CLAVE3` (fuente) + `CLAVE6` (proyecto) + `PARTIDA` |
| `cat_egreso_unidad_administrativa` | Unidades administrativas (`clave` = `CLAVE` del presupuesto, `secretaria_id`) |
| `tb_egreso_requisicion` | Requisiciones (estado, unidad, folio, fecha de trámite, totales, `suficiencia`) |
| `tb_egreso_requisicion_detalle` | Conceptos (`fuente`, `proyecto`, `partida`, cantidad, precio, IVA, total) |
| `tb_egreso_requisicion_bitacora` | Bitácora de cambios de cada requisición |
| `cat_egreso_requisicion_estado` | 1 Pendiente, 2 En Revisión, 3 Aprobada, 4 Rechazada, 5 En Recursos Materiales, 6 En Cotización |
| `proveedores` | Catálogo de proveedores (`tb_egreso_proveedor` legado no se usa) |
| `ordenes_compra` | Orden de compra por requisición y datos de su CFDI |
| `modificaciones_presupuestales` | Traspasos, reducciones y ampliaciones; origen y destino por clave de línea, para sobrevivir a una re-importación del presupuesto |
| `secretarias`, `users` | Secretarías (prefijo de folios) y usuarios (`secretaria_id`) |
| `auditoria` | Bitácora de auditoría de los catálogos: usuario, acción, modelo e id, valores `antes`/`despues` (JSON), `lote` de importación, IP |
| `cat_egreso_objeto_gasto`, `cat_egreso_fuente_financiamiento`, `cat_egreso_programa` (+`clave`), `cat_egreso_proyecto`, `cat_banco` (+`clave`) | Catálogos del sistema anterior que ahora se administran en `/catalogos`. **No tienen AUTO_INCREMENT**: el id lo calcula la aplicación |
| `cat_egreso_unidad_administrativa` (+`responsable`, `cargo_responsable`), `departamentos`, `firmantes` | Estructura orgánica: dirección (centro gestor), sus departamentos y los firmantes de los PDF por tipo de documento |
| `ejercicios_fiscales`, `tipos_documento` | Ejercicios abierto/cerrado; tipos de documento (`requisicion`, `orden_compra`, `modificacion_presupuestal` se crean con la migración) |
| `proveedor_cuentas` | Cuentas bancarias (CLABE) de `proveedores` |
| `vehiculos` | Padrón vehicular |
| `roles`, `permissions`, `model_has_*`, `role_has_permissions` | Spatie (con columnas extra `nombre_mostrar` y `categoria`) |
| `tb_usuarios`, `cat_areas`, `cat_area_x_nombre_y_puesto`, `cat_pais`, `cat_estado`, `cat_municipio`… | Catálogos del sistema anterior requeridos por llaves foráneas |

El controlador de requisiciones llena las llaves legadas obligatorias (`id_tb_egreso_presupuesto`, `id_cat_area_x_nombre_y_puesto_*`, `id_tb_usuarios`, `id_cat_egreso_producto`) con el id `1` de cada catálogo, que debe existir.

## Política de migraciones

- **Una migración nunca borra información.** Solo agrega tablas, columnas o índices.
- Las que modifican tablas del sistema anterior empiezan con `if (! Schema::hasTable(...)) return;`: en la base SQLite de las pruebas esas tablas no existen.
- En servidores se corren **por ruta**, revisando antes con `--pretend` (ver [Instalación](instalacion.md#3-base-de-datos)).

## Hora y fechas

Laravel corre en **UTC** y MySQL guarda hora local (UTC−6): columnas legadas como `registro` quedan en hora local y las de Laravel (`created_at`, `suficiencia_fecha`, `resuelto_at`) en UTC. Ver [Arquitectura](arquitectura.md#backend).
