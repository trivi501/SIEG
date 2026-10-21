# Módulos

Menú lateral: **Panel**, **Presupuesto**, **Ejercicio y control del gasto**, **Requisiciones** (listado, nueva, Recursos Materiales, proveedores, modificaciones presupuestales), **Catálogos**, **Secretarías y direcciones** y **Administración**. Cada entrada aparece solo con su permiso (ver [Permisos](permisos.md)).

## Panel

`/dashboard` — Resumen del presupuesto de las unidades del usuario (aprobado, modificado, vigente, en trámite, comprometido, devengado, pagado, disponible) y lo pendiente en cada etapa: requisiciones por enviar, en revisión, en Recursos Materiales, con suficiencia sin orden de compra y modificaciones por autorizar. Cada tarjeta lleva a su listado.

---

## Presupuesto

`/presupuesto` (solo Admin) — Presupuesto de egresos importado desde Excel a la tabla `presupuesto 2024`. Cada **línea** es una combinación **unidad administrativa + fuente de financiamiento + proyecto + partida**, con su importe anual y mensual (DECIMAL con centavos).

- **Nueva partida** (`/presupuesto/crear`, permiso `presupuesto-create`): se eligen secretaría o dirección, fuente, proyecto y partida **de los catálogos**; los nombres, el capítulo y el tipo de gasto se toman de ahí. No se puede repetir la combinación de las cuatro claves.
- **Distribución mensual: ni de más ni de menos.** Al dar de alta, editar o importar, los 12 meses deben sumar **exactamente** el importe total (al centavo). La pantalla muestra en vivo lo distribuido y lo que falta o sobra, ofrece *Repartir en 12 meses iguales* y no deja guardar hasta que cuadre; el servidor lo vuelve a validar. Al **importar**, si el archivo trae *Importe total* y algún renglón no cuadra, **no se importa nada** y el mensaje lista los renglones (si no trae la columna, el total es la suma de los meses).
- Las líneas anteriores que no cuadran (redondeos de cuando los importes se guardaban sin centavos) se marcan en la lista con lo que falta o sobra y se pueden filtrar con *Solo distribución que no cuadra*; al editarlas hay que corregir la distribución.

`PresupuestoService` calcula para cada línea:

| Saldo | Cálculo |
|---|---|
| Aprobado | Importe aprobado (importado) |
| Modificado | Suma de modificaciones presupuestales **autorizadas** (+ destino, − origen) |
| Vigente | Aprobado ± modificado |
| Comprometido | **Acumulado**: todas las requisiciones **con suficiencia** (incluye lo ya devengado y pagado) |
| En trámite | Requisiciones cotizadas que aún no tienen suficiencia |
| Devengado | **Acumulado**: todo el historial de órdenes de compra con **factura XML** (incluye lo pagado) |
| Pagado | Todo el historial de órdenes de compra con **pago registrado** |
| Disponible | Vigente − comprometido (acumulado) |

**Consulta por área**: con `presupuesto-index`, cada área ve solo las líneas de su unidad y en solo lectura (sin *Importar*, *Nueva partida*, *Editar* ni *Limpiar todo*, que dependen de `presupuesto-import`, `-create`, `-edit` y `-delete`); Admin y Control Presupuestal ven todo. Igual en *Ejercicio y control del gasto* (`gasto-index`): el área ve solo las partidas de su unidad, su detalle y su Excel, sin *Correr saldos*. La lista muestra el **vigente anual** y el **vigente al mes de la consulta** (aprobado de enero a ese mes ± modificaciones autorizadas hasta ese mes).

**Saldos mensuales y corrida de saldos.** Además del disponible anual, cada línea tiene un **disponible del mes** = saldo corrido + asignado del mes (calendario) ± modificaciones autorizadas en el mes − comprometido en el mes (por fecha de suficiencia). Cada mes se *corren los saldos* (de forma **manual**): lo que quedó disponible en el mes anterior se suma al mes actual como **saldo corrido** (si se excedió, se resta). Se guarda en `saldos_mensuales` sin tocar el calendario autorizado y queda congelado (un movimiento tardío de un mes ya corrido no cambia lo corrido). Enero empieza en cero. No se programa: se corre con el botón *Correr saldos* (permiso `presupuesto-correr-saldos`) o con el comando `presupuesto:correr-saldos`; mientras no se corre, el saldo corrido se calcula con los movimientos. Una sola corrida por mes (el comando la puede recalcular con `--forzar`).

Los momentos son **acumulativos**: lo comprometido sigue en comprometido al cargar la factura (y suma al devengado) y al pagar (y suma al pagado); comprometido ≥ devengado ≥ pagado y el disponible solo descuenta el comprometido. Una orden o CLC cancelada sale de devengado / pagado. La lista de `/presupuesto` muestra por línea la **clave del proyecto** (con filtro) y los importes **aprobado, modificado, vigente, comprometido, devengado y pagado**.

Las modificaciones se guardan por clave de línea (no por id), así que sobreviven a una re-importación del presupuesto.

`/requisiciones/gasto` ("Ejercicio y control del gasto") muestra estos saldos por línea de las unidades del usuario (Admin y Control Presupuestal: todas) —aprobado, modificado, vigente, comprometido, devengado, pagado, disponible, saldo corrido y disponible del mes; sin "en trámite"—, con filtro por unidad, búsqueda y opción de ver solo partidas con movimiento. La tabla muestra 20 partidas por página (los totales son de todas) y se puede desplazar de lado arrastrándola con el mouse. **Exportar a Excel** descarga todo lo que cumple los filtros, no solo la página (mismos filtros y búsqueda), con totales por fórmula e importes como números.

Al hacer clic en una partida se abre su **detalle** (`/requisiciones/gasto/detalle?clave=…`, JSON): las requisiciones que la usan con estado, momento (en trámite, comprometido, devengado, pagado), importe de esa partida, fecha de suficiencia, orden de compra (folio, fecha, importe), proveedor, factura, CLC (folio, fecha y paso del trámite) y fecha de pago; las canceladas y rechazadas aparecen sin contar en el total. Abajo, las modificaciones presupuestales en que la partida es emisora o receptora, con el importe con signo, periodo, estado y fechas.

## Requisiciones

`/requisiciones` — Cada usuario ve solo las requisiciones que genera **su secretaría o dirección** (se asigna en Administración → Usuarios). Admin y Control Presupuestal ven y trabajan las de todas las áreas.

- Captura: fecha de trámite, unidad administrativa, concepto y renglones con **Partida / Proyecto / Fuente** (tres columnas encadenadas: cada una solo ofrece valores que combinan con lo ya elegido y que tienen disponible), cantidad y descripción. Al guardar se valida que cada renglón exista en el presupuesto de la unidad.
- Folio: prefijo de la secretaría o dirección + consecutivo anual (`REQ-0001` si no tiene prefijo).
- **Calendario de captura** (`/catalogos/calendario-captura`, menú *Requisiciones → Calendario de captura*, permisos `catalogos-calendario-captura-*`, Admin): el Admin define para **cada mes** la **fecha de inicio y la de fin** de la captura (`calendario_captura`, un registro por año y mes; el de un mes puede empezar a fines del anterior). Un mes sin registro usa los días del ejercicio (`/catalogos/ejercicios`, *Captura de requisiciones: del día / al día*; por omisión del 1 al 7). La pantalla de captura avisa si el periodo está abierto, con sus fechas (`EjercicioFiscal::periodoDelMes()` / `enPeriodoDeCaptura()`). Lo que se captura fuera del periodo queda **extemporáneo**: no se puede *Enviar a revisión* hasta que la Subdirección Administrativa (Adquisiciones, permiso `requisiciones-extemporaneas`) pulsa *Autorizar extemporánea*; al autorizarla se envía sola a revisión; se guarda quién y cuándo (`extemporanea_user_id`, `extemporanea_autorizada_at`) y queda en la bitácora. La lista marca *Extemporánea por autorizar / autorizada*.
- El monto total siempre es la suma de los conceptos.

```mermaid
flowchart TD
    P[1 Pendiente] -->|Enviar a revisión| R[2 En revisión]
    R -->|Válida| A[3 Aprobada]
    R -->|No válida| N[4 Rechazada]
    N -->|Se corrige y reenvía| R
    A -->|Imprimir formato, firma del titular,<br/>enviar a Recursos Materiales| M[5 En Recursos Materiales]
    M -->|Recibido| C[6 En cotización]
    C -->|Capturar precios e IVA| C
    C -->|Validar suficiencia| S{¿Alcanza el<br/>presupuesto?}
    S -- Sí --> CS[7 Comprometida]
    S -- No --> SS[Sin suficiencia]
    SS -->|Modificación presupuestal autorizada| C
    CS -->|Asignar proveedor| OC[8 Orden de compra]
    OC -->|Cargar XML: comprometido → devengado| XML[9 Con factura]
    XML -->|El área genera la CLC| CLC[10 CLC generada]
    CLC -->|Registrar pago: devengado → pagado| PG[11 Pagada]
```

Los estados 7 a 11 se agregaron al catálogo `cat_egreso_requisicion_estado` (migración `2026_10_12_100000`, que además pasó a su estado las requisiciones que ya tenían suficiencia y seguían "En cotización").

**Refactura** (`ordenes-compra-refacturar`, Admin): cuando el proveedor vuelve a facturar, el botón *Refacturar* de la orden de compra (con motivo) quita la factura vigente y la guarda en el historial (`ordenes_compra_refacturas`), cancela su CLC si la tenía (movimiento *cancelar* en el trámite) y la requisición regresa a *Orden de compra*. Al cargar el nuevo XML se **genera sola una CLC nueva** con otro folio (los folios sustituidos no se repiten). Una CLC ya pagada no se refactura.

**Regresar al área** (`requisiciones-devolver`, Recursos Materiales): en *En Recursos Materiales* o *En cotización* (sin suficiencia), con motivo — p. ej. un producto que no está en stock —; queda **No válida (4)** para que el área la modifique y la vuelva a enviar.

**Vehículos** (menú *Vehículos*):

- **Padrón vehicular** (`/catalogos/vehiculos`): cada vehículo es **resguardo de una secretaría** y está **asignado a un área** (departamento) de ella; el área debe ser de esa secretaría.
- **Mantenimiento** (`/vehiculos/mantenimientos`, `vehiculos-mantenimiento`, rol de prueba *Taller vehicular*): se **recibe** el vehículo en el taller (folio `MV-AÑO-0001`, fecha, kilometraje, preventivo/correctivo, falla o servicio, quién lo trae) y queda *En taller*. Desde ahí, *Requisición* abre la captura **a nombre de la secretaría de resguardo** (aunque el taller sea de otra unidad), con el concepto prellenado; la requisición queda ligada al mantenimiento y sigue el trámite normal (el taller la puede consultar). Al terminar se **entrega** (fecha, quién lo recibe, trabajo realizado) y el vehículo vuelve a *En servicio*.

**Almacén** (`/almacen`, `App\Services\Almacen`), conforme a la orden de compra:

- Recursos Materiales **asigna** la orden de compra al almacén (*Asignar al almacén* en la requisición, `ordenes-compra-almacen`).
- El almacén registra a mano sus **entradas** (lo que recibe del proveedor, por partes, hasta lo pedido; folio `EA-AÑO-0001`, `almacen-entradas`) y sus **salidas** al área de la requisición (por partes, hasta la existencia; folio `SA-AÑO-0001`, `almacen-salidas`). Cada movimiento queda en la bitácora de la requisición.
- **Inventario** (`/almacen/inventario`): por concepto, recibido, entregado y existencia valuada al precio de la orden.
- Formatos (lo de una orden cancelada no se imprime): **Acta de entrega** (por entrada, todas las órdenes: entrega el proveedor, recibe el usuario de Materiales que registró la entrada, con la fecha de la entrada); **Entrada de almacén** (recibe la receptora de insumos — rol *Elabora* — y autoriza el jefe de Recursos Materiales — *Autoriza* —, en Catálogos → Firmantes); **Salida de almacén** (logo, folio, secretaría, fecha, cantidad y descripción; firman quien entrega, el titular de Recursos Materiales y el titular del área que autorizó la requisición).
- Rol de prueba **Almacén** (`almacen.prueba@sieg.test`).

**Facturas** (`/facturas`, `facturas-index`): todas las facturas cargadas (también las canceladas con su orden) con emisor, folio/UUID, fecha, monto (ISR), validación en el SAT (y EFOS) y el estatus de su trámite: requisición (FUR), orden de compra y CLC. Filtros por texto, estado en el SAT, aceptadas/canceladas y fechas; paginada de 25. Quien participa en el trámite ve todas; el área, las de su secretaría.

**Firmas de los formatos**: requisición (FUR) — Solicitante: enlace que la capturó; Autorizó: titular del área. Orden de compra — Solicita: Recursos Materiales; Autoriza: Administración. CLC — Solicitante: titular del área; Autoriza: Tesorería y Sindicatura. Modificación (traspaso) — Solicita: titular del área.

**Cancelación escalonada** (en cualquier estado, de lo último a lo primero; cada paso con motivo obligatorio y bitácora):

1. *Cancelar CLC* (`clc-cancelar`), según el paso del trámite (`TramiteClc::CANCELA_HASTA`): el **área** (las de su unidad) hasta que Recursos Materiales la recibe; **Recursos Materiales** hasta que la recibe Control Presupuestal; **Control Presupuestal** hasta que llega a Sindicatura; después, aun pagada, solo **Admin**. Sale de las bandejas (estado de trámite **Cancelada**, filtro *Canceladas* en Seguimiento), el pago queda anulado (deja de contar como pagado; folio y número de transferencia se conservan como historial) y la requisición regresa a **9 Con factura**. No se genera otra CLC.
2. *Cancelar orden de compra* (`ordenes-compra-cancelar`): solo con la CLC cancelada (o sin CLC). **Cancela también su factura**: el UUID pasa a `xml_uuid_cancelado` y esa misma factura se puede cargar **una sola vez más** en otra orden. Deja de contar como devengado y la requisición regresa a **7 Comprometida**.
3. *Cancelar requisición*: con la orden cancelada (o sin orden). Es cuando el presupuesto se libera.

Lo cancelado ya no se imprime: ni la CLC, ni la orden de compra, ni el formato de la requisición.

**Cancelar requisición sin orden de compra**: botón *Cancelar requisición* (modal con motivo obligatorio). Con el permiso `requisiciones-cancelar` en cualquiera de esos estados; el enlace de la secretaría, las suyas pendientes o rechazadas. Queda en estado **12 Cancelada** con el motivo, quién y cuándo, y deja de contar en el presupuesto (en trámite o comprometido). En los saldos mensuales, lo que estaba comprometido se queda en el mes de la suficiencia y se **libera en el mes de la cancelación** (el mes vigente), no en el mes original.

**Cuándo se puede editar:** en Pendiente y Rechazada; en cotización solo los precios y el IVA, hasta que tenga suficiencia. En revisión, aprobada y en Recursos Materiales no se puede modificar (también se bloquea en el servidor). El formato FUR se imprime desde que es válida.

## Recursos Materiales

`/recursos-materiales` — Bandeja de todas las secretarías con requisiciones de Recursos Materiales en adelante (en cotización, comprometida, orden de compra, con factura, CLC generada, pagada).

1. **Recibido**: inicia la cotización.
2. **Editar cotización**: precio unitario por concepto y si **lleva IVA** (16 %).
3. **Validar suficiencia presupuestal**: compara lo cotizado contra el disponible de cada línea. Un mensaje muestra el resultado por partida (requerido, disponible, faltante) y la requisición queda **Con suficiencia** o **Sin suficiencia**. Si se cambian los precios después, hay que volver a validar.
4. **Sin suficiencia**: el botón *Solicitar modificación presupuestal* abre la solicitud con la partida que no alcanza y el importe faltante ya llenos.
5. **Con suficiencia**: la requisición pasa a **Comprometida** (ya no aparece "En cotización") y se habilita *Asignar proveedor y generar orden de compra*.

## Órdenes de compra y facturas

- Solo para requisiciones **Comprometidas**. Se asigna el **proveedor** (`/catalogos/proveedores`, catálogo con RFC) o, si no está, se da de alta ahí mismo (nombre y RFC; requiere `proveedores-create`), la fecha y observaciones. Folio `OC-AÑO-0001`; el importe es el total de la requisición. PDF imprimible. La requisición pasa a **Orden de Compra**.
- **Cargar factura (XML CFDI 3.3/4.0)**: se rechaza si
  - el total del CFDI no coincide con lo cotizado (tolerancia de 1 centavo),
  - el RFC del emisor no es el del proveedor de la orden,
  - el XML no está timbrado (sin UUID),
  - **la factura ya está cargada en otra orden**: mismo folio fiscal (UUID), o misma serie y folio del mismo emisor aunque traiga otro UUID (factura vuelta a timbrar). El aviso dice en qué orden y requisición está. El UUID además es único en la base de datos, así que dos cargas simultáneas del mismo XML no pueden pasar.
- **ISR retenido**: se toma del XML (retenciones del comprobante con Impuesto 001, `xml_isr`). No cambia el importe del gasto: lo cotizado se compara con Total + ISR (el Total del CFDI ya viene sin la retención), el presupuesto ejerce el importe completo y al proveedor se le paga **importe − ISR** (columna I.S.R. y *Total(Imp − ISR)* de la CLC, diálogo de pago y bitácora).
- **Estado en el SAT** (`App\Services\EstadoCfdiSat`, servicio público de verificación de CFDI): al cargar el XML se consulta con RFC emisor, RFC receptor, total, UUID y últimos 8 caracteres del sello. **Cancelada** o **No encontrada** (apócrifa o recién timbrada: el SAT tarda hasta 72 h) se rechaza; si el emisor está en la lista de **EFOS** se avisa. Solo las facturas **aceptadas** (vigentes) se guardan: si el SAT no responde, tampoco se acepta (intentar más tarde). Antes de **pagar** (trámite de CLC) se vuelve a consultar: una factura cancelada detiene el pago. El estado y la fecha de la última consulta se ven en la requisición y en el trámite de CLC, con el botón *Verificar en el SAT* (`ordenes-compra-verificar-sat`).
- El XML se guarda en el disco privado (`storage/app/private/ordenes-compra/`) y se puede descargar. Con la factura cargada ("factura aceptada"), la requisición pasa a **Con Factura** y el importe sale de comprometido y pasa a **devengado**.
- **CLC (cuenta por liquidar certificada)**: con la factura aceptada, el **área** de la requisición (permiso `requisiciones-clc`; Admin y Recursos Materiales también) genera la CLC: folio `CLC-AÑO-0001`, una por orden. Su PDF trae beneficiario, RFC, cuenta bancaria del proveedor (la del catálogo de proveedores; si no tiene, la principal de sus cuentas adicionales), factura y UUID, el importe por clave presupuestal y firmas (tipo de documento `clc` en Firmantes). La requisición pasa a **CLC Generada**.
- **Registrar pago** (permiso `ordenes-compra-pagar`, solo con la CLC generada y una sola vez): fecha (no anterior a la orden ni futura), forma (transferencia o cheque) y referencia. Con **transferencia**, el sistema asigna solo el **número de transferencia** consecutivo por año de la fecha de pago (`TRF-2026-0001`, uno por CLC; base de la futura posición bancaria) y la referencia bancaria es opcional; con **cheque**, el número de cheque es obligatorio. El importe pasa de devengado a **pagado**, la requisición queda **Pagada** y queda en la bitácora.

## Trámite de CLC

`/clc` — La CLC física recorre las áreas antes del pago. Al **generarla** queda en el área, que la **envía a Recursos Materiales** (pestaña *Mi área*; solo las de su secretaría, sin relación) y después ve su **seguimiento** (pestaña *Seguimiento*, "Mis CLC"). Cada **recepción** se marca con casillas (las que no se marcan siguen pendientes). Solo los envíos **Control Presupuestal → Sindicatura** y **Sindicatura → Control Presupuestal** generan **relación** (`REL-AÑO-0001`, PDF con la lista, total, casilla de recibida y firmas de quien entrega y quien recibe). Todo queda en `clc_movimientos` y se ve en la requisición ("Trámite de la CLC").

| Paso | Estado | Quién (permiso) |
|---|---|---|
| El área la envía a RM | Generada → Enviada a Recursos Materiales | área (`requisiciones-clc`, solo las de su secretaría) |
| RM la recibe y la envía a CP | En Recursos Materiales → Enviada a Control Presupuestal | `clc-recursos-materiales` |
| CP la recibe, la revisa y la **devuelve** a RM con observaciones (obligatorias) o la envía a Sindicatura (relación) | En revisión → Devuelta / Enviada a Sindicatura | `clc-control-presupuestal` |
| Sindicatura marca las que recibió y las regresa a CP (relación) | En Sindicatura → Enviada a CP (firmada) | `clc-sindicatura` |
| CP marca las que recibió y están correctas, y las envía a Egresos | En CP (firmada) → Enviada a Egresos | `clc-control-presupuestal` |
| Egresos las recibe y las **marca pagadas** (fecha, forma, referencia) | En Egresos → Pagada | `clc-egresos` + `ordenes-compra-pagar` |

**Quién ve qué:** Control Presupuestal y Admin ven todas las bandejas, el seguimiento de todas y las relaciones (solo actúan con su permiso); Recursos Materiales, Sindicatura y Egresos ven las CLC de su bandeja; Sindicatura también las relaciones; el área, las de su secretaría (para enviarlas y darles seguimiento).

**Roles y usuarios de prueba:** `php artisan clc:usuarios-prueba` crea (o completa sin quitar permisos) los roles *Área solicitante*, *Recursos Materiales*, *Control Presupuestal*, *Sindicatura* y *Egresos* (`UsuariosPruebaClc::ROLES`) y un usuario `{rol}.prueba@sieg.test` por rol; la contraseña queda en `storage/app/private/usuarios-prueba-clc.txt`. En servidor: `--solo-roles` (o `--force`). Control Presupuestal también administra usuarios, roles y permisos.

## Modificaciones presupuestales

`/modificaciones-presupuestales`

| Tipo | Efecto |
|---|---|
| Traspaso | Quita de una línea (origen) y da a otra (destino), **solo de la misma fuente de financiamiento** |
| Reducción | Solo disminuye una línea (p. ej. no se alcanzó la meta de la Ley de Ingresos) |
| Ampliación | Solo aumenta una línea |

1. El área registra la solicitud (folio `MP-AÑO-0001`): la línea **emisora** (origen) y la **receptora** (destino), el **periodo** (mes) de cada una —del mes actual a diciembre—, importe y justificación. El emisor no puede quedar en negativo ni en el año ni en el mes elegido: el formulario muestra, de cada lado, la fuente, el disponible del periodo y cómo queda; si el emisor no alcanza avisa cuánto falta y no deja registrar. En un traspaso, el receptor solo ofrece partidas de la fuente del emisor (también se valida al registrar y al aceptar).
2. Se imprime el formato para firma.
3. El **Jefe de Control Presupuestal** (permiso `modificaciones-autorizar`) la **acepta** o la **rechaza** (con motivo obligatorio), desde el detalle o directo en la lista (botones *Aceptar* / *Rechazar* en las pendientes). Al aceptar se vuelve a revisar el disponible del emisor (anual y del mes) y el presupuesto vigente cambia de inmediato. En los saldos mensuales el movimiento cuenta en el periodo de cada lado (las modificaciones anteriores, sin periodo, en el mes en que se autorizaron).
4. El listado es el **historial**, con filtros por folio, tipo, estado y partida; muestra el periodo junto al emisor y al receptor.

Las áreas solo ven y usan líneas de sus unidades (no pueden modificar las de otra área); quien autoriza (Control Presupuestal) ve todas y siempre puede registrar traspasos y modificaciones.

**Periodo de captura**: el Admin define en el **Calendario de captura de modificaciones** (`/catalogos/calendario-modificaciones`, menú *Requisiciones → Calendario de modificaciones*, permisos `catalogos-calendario-modificaciones-*`) la fecha de inicio y la de fin de cada mes (un registro por año y mes; el de un mes puede empezar a fines del anterior). Las áreas solo registran modificaciones dentro de ese periodo. Un mes sin registro usa la regla por omisión: del día 1 hasta **3 días antes del fin de mes** (en octubre, hasta el 28), en hora de México (`ModificacionPresupuestal::periodoCaptura()` / `proximaApertura()`). La lista y el formulario muestran el aviso y ocultan / desactivan el botón; el servidor también lo rechaza. Control Presupuestal y Admin capturan todo el mes.

---

## Usuarios: título, cargo y a quién pertenecen

En Administración → Usuarios cada usuario tiene **título** (L.C., LIC., MTRA.…), **cargo** (p. ej. ENLACE ADMINISTRATIVO) y **pertenece a** una de dos opciones, sin mezclarse:

- **Secretaría o dirección**: ve sus requisiciones y presupuesto; firma por el área el titular de la secretaría (Firmantes o el titular de la unidad).
- **Regidor** (catálogo `/catalogos/regidores`: título, nombre, REGIDOR/REGIDORA, comisión): es su enlace; para el presupuesto queda en **Cabildo** (clave 140) y el **regidor firma** sus formatos.

En los formatos: FUR *Solicitante* = quien capturó (título, nombre y cargo) y *Autorizó* = su regidor o el titular de su secretaría; CLC *Solicita* = igual que *Autorizó* del FUR y *Elaboró* = quien la generó con su título; la modificación presupuestal firma el regidor del solicitante o el titular (`Firmante::titularDe`).

## Catálogos generales

`/catalogos` — Portada con los catálogos que el usuario puede ver, por grupo. Cada catálogo (`/catalogos/{slug}`) tiene la misma pantalla:

- **Altas y modificaciones** en un diálogo; la validación (obligatorios, únicos, formatos) es la misma al capturar y al importar.
- **Bajas lógicas**: *Dar de baja* deja el registro inactivo y se puede *Reactivar*. Nada se borra, porque hay documentos que los referencian.
- **Historial** de cada registro: quién, cuándo y qué cambió (valor anterior → nuevo).
- **Importación masiva** desde Excel o CSV: se descarga la plantilla (con una hoja de instrucciones), se sube y se ve una **vista previa** con altas, modificaciones, sin cambios y errores por renglón. No se guarda nada hasta confirmar, y no se puede confirmar si algún renglón tiene errores. Si el registro ya existe (por su llave, p. ej. la clave o el número económico) se actualiza, y las celdas vacías no borran el valor actual.

| Grupo | Catálogo (`slug`) | Notas |
|---|---|---|
| Estructura orgánica | Secretarías y direcciones (`unidades-administrativas`) | Un solo catálogo: dependencias y centros gestores (`cat_egreso_unidad_administrativa`); prefijo de folio, titular y cuántos usuarios tiene. Llave: clave + año |
| | Departamentos (`departamentos`) | Dentro de cada dirección |
| | Responsables y firmantes (`firmantes`) | Por tipo de documento y rol (elabora, revisa, autoriza, visto bueno), con vigencia. Uno con dirección tiene prioridad sobre el general |
| Clasificación presupuestal | Objeto del Gasto (`objeto-gasto`) | Partida específica; capítulo, concepto y genérica se derivan de la clave (CONAC). Tipo de gasto |
| | Fuentes de financiamiento, Programas, Proyectos | Claves de `CLAVE3` y `CLAVE6` del presupuesto |
| Ejercicios y documentos | Ejercicios fiscales (`ejercicios`) | Abierto / cerrado: un ejercicio **cerrado** bloquea requisiciones nuevas de ese año |
| | Tipos de documento (`tipos-documento`) | `requisicion`, `orden_compra` y `modificacion_presupuestal` los usan los PDF para buscar firmantes |
| Proveedores y bancos | Proveedores (`proveedores`) | RFC validado; banco, CLABE (con dígito verificador, única) y número de cuenta; **vigencia** (vencida no se le asigna ni reasigna orden de compra); permisos `proveedores-*`. El proveedor de una orden de compra se reasigna (*Cambiar*, `ordenes-compra-create`) mientras no tenga factura |
| | Cuentas bancarias (`cuentas-bancarias`) | CLABE con dígito verificador; el banco se toma de sus 3 primeros dígitos; una sola cuenta principal por proveedor |
| | Bancos (`bancos`) | `cat_banco` con clave de 3 dígitos |
| Padrón vehicular | Vehículos (`vehiculos`) | Número económico, placas, serie, secretaría o dirección asignada, resguardante. Los cambios de resguardante quedan en el historial |

**Firmantes en los PDF.** La requisición (*Autorizó*), la orden de compra (*Elaboró*, *Autorizó*) y la modificación presupuestal (*Procede / No procede*) imprimen nombre y cargo del firmante vigente en la fecha del documento; sin firmante configurado conservan el texto anterior.

**Carga inicial.** `php artisan catalogos:inicializar` agrega a fuentes, proyectos y partidas las claves que ya usa el presupuesto importado, la lista de bancos y el ejercicio fiscal actual. Solo agrega lo que falta (`--pretend` muestra cuántos). Al importar el presupuesto, el mensaje avisa qué claves no están en los catálogos.

---

## Administración

- `/auditoria` — **Bitácora de auditoría** de todo el sistema: altas, modificaciones, bajas, reactivaciones y eliminaciones de requisiciones (y sus conceptos), órdenes de compra, modificaciones presupuestales, presupuesto, catálogos, secretarías, usuarios (incluidos cambios de rol y de contraseña, sin guardar la contraseña), roles y sus permisos y tickets; además los **accesos**: inicio y cierre de sesión e intentos fallidos con el correo usado. Cada movimiento lleva usuario, IP, fecha y valores anterior/nuevo. Filtros por módulo, usuario, acción y fechas; el nombre del módulo lleva al registro, *Registro #* muestra todo lo de ese registro y la descripción del lote muestra toda una importación o borrado masivo.
- `/settings/users` — Usuarios: rol y **secretaría o dirección** (una sola; define qué requisiciones y presupuesto ve).
- `/settings/roles`, `/settings/permissions` — Roles y permisos (ver [Permisos](permisos.md)).
- `/support-tickets` — Tickets de soporte con comentarios y notificaciones.
- `/logs` — Visor del log de Laravel.
