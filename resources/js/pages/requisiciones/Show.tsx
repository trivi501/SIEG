import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileText, Send, XCircle } from 'lucide-react';
import { useState } from 'react';
import SearchableSelect from '@/components/searchable-select';
import SuficienciaBadge from '@/components/suficiencia-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { fechaLocal, money, numero } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
    { title: 'Detalle', href: '#' },
];

interface NombresPresupuesto {
    fuentes: Record<string, string>;
    proyectos: Record<string, string>;
    partidas: Record<string, string>;
}

interface PartidaValidada {
    clave: string;
    fuente: string;
    proyecto: string;
    partida: string;
    nombre_partida: string | null;
    existe: boolean;
    requerido: number;
    vigente: number;
    comprometido: number;
    disponible: number;
    faltante: number;
    suficiente: boolean;
}

interface Validacion {
    suficiente: boolean;
    total: number;
    partidas: PartidaValidada[];
}

interface Modificacion {
    id: number;
    folio_completo: string;
    tipo: string;
    estado: string;
    importe: number;
}

const conNombre = (clave: string | null, nombres: Record<string, string>) => {
    if (!clave) {
        return '—';
    }

    const nombre = nombres[clave];

    return nombre ? `${clave} - ${nombre}` : clave;
};

function TablaValidacion({
    validacion,
    nombres,
}: {
    validacion: Validacion;
    nombres: NombresPresupuesto;
}) {
    const cotizada = validacion.total > 0;

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/50 text-left">
                        <th className="p-2">Fuente / Proyecto / Partida</th>
                        {cotizada && (
                            <th className="p-2 text-right">Requerido</th>
                        )}
                        <th className="p-2 text-right">Vigente</th>
                        <th className="p-2 text-right">Comprometido</th>
                        <th className="p-2 text-right">Disponible</th>
                        {cotizada && (
                            <th className="p-2 text-center">Resultado</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {validacion.partidas.map((p) => (
                        <tr key={p.clave} className="border-b">
                            <td className="p-2 text-xs">
                                {conNombre(p.fuente, nombres.fuentes)} ·{' '}
                                {conNombre(p.proyecto, nombres.proyectos)} ·{' '}
                                {conNombre(p.partida, nombres.partidas)}
                                {!p.existe && (
                                    <span className="ml-1 text-destructive">
                                        (no existe en el presupuesto)
                                    </span>
                                )}
                            </td>
                            {cotizada && (
                                <td className="p-2 text-right">
                                    {money(p.requerido)}
                                </td>
                            )}
                            <td className="p-2 text-right">
                                {money(p.vigente)}
                            </td>
                            <td className="p-2 text-right">
                                {money(p.comprometido)}
                            </td>
                            <td
                                className={`p-2 text-right font-medium ${p.disponible <= 0 ? 'text-destructive' : ''}`}
                            >
                                {money(p.disponible)}
                            </td>
                            {cotizada && (
                                <td className="p-2 text-center text-xs">
                                    {p.suficiente ? (
                                        <span className="inline-flex items-center gap-1 text-green-700">
                                            <CheckCircle2 className="h-4 w-4" />{' '}
                                            Alcanza
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-destructive">
                                            <XCircle className="h-4 w-4" />{' '}
                                            Faltan {money(p.faltante)}
                                        </span>
                                    )}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({
    requisicion,
    isAdmin,
    nombresPresupuesto,
    validacion,
    puedeEditarse,
    proveedores,
    modificaciones,
}: {
    requisicion: any;
    isAdmin: boolean;
    nombresPresupuesto: NombresPresupuesto;
    validacion: Validacion;
    puedeEditarse: boolean;
    proveedores: { id: number; nombre: string; rfc: string | null }[];
    modificaciones: Modificacion[];
}) {
    const detalles = requisicion.tb_egreso_requisicion_detalles ?? [];
    const bitacoras = requisicion.tb_egreso_requisicion_bitacoras ?? [];
    const orden = requisicion.orden_compra;
    const page = usePage();
    const permisos = (page.props.userPermissions as string[] | undefined) ?? [];
    const puede = (p: string) => permisos.includes(p);
    const id = requisicion.id_tb_egreso_requisicion;
    const estado = requisicion.id_cat_egreso_requisicion_estado;
    const esPendiente = estado === 1 || estado === 4;
    const esRevision = estado === 2;
    const esAprobado = estado === 3;
    const esRecursosMateriales = estado === 5;
    const esCotizacion = estado === 6;
    const esComprometida = estado === 7;
    const extemporaneaPendiente =
        !!requisicion.extemporanea && !requisicion.extemporanea_autorizada_at;
    const [showEnviar, setShowEnviar] = useState(false);
    const [showResultado, setShowResultado] = useState(false);
    const [showOrden, setShowOrden] = useState(false);
    const [validando, setValidando] = useState(false);

    const ordenForm = useForm({
        proveedor_id: '',
        fecha: new Date().toISOString().slice(0, 10),
        observaciones: '',
    });
    const [nuevoProveedor, setNuevoProveedor] = useState(false);
    const proveedorForm = useForm({ nombre: '', rfc: '', activo: true });
    const xmlForm = useForm<{ xml: File | null }>({ xml: null });
    const pagoForm = useForm({
        pago_fecha: new Date().toISOString().slice(0, 10),
        pago_forma: 'transferencia',
        pago_referencia: '',
    });

    const post = (accion: string, options = {}) =>
        router.post(
            `/requisiciones/${id}/${accion}`,
            {},
            { preserveScroll: true, ...options },
        );

    const validarSuficiencia = () => {
        setValidando(true);
        post('suficiencia', {
            onSuccess: (p: any) => {
                if (
                    p.props.requisicion?.suficiencia !== null &&
                    p.props.requisicion?.suficiencia !== undefined
                ) {
                    setShowResultado(true);
                }
            },
            onFinish: () => setValidando(false),
        });
    };

    const generarOrden = (e: React.FormEvent) => {
        e.preventDefault();
        ordenForm.post(`/requisiciones/${id}/orden-compra`, {
            preserveScroll: true,
            onSuccess: () => setShowOrden(false),
        });
    };

    // Alta rápida del proveedor desde la orden de compra (mismo catálogo /catalogos/proveedores):
    // al guardarse queda asignado a la orden.
    const guardarProveedor = () => {
        const { nombre, rfc } = proveedorForm.data;

        proveedorForm.post('/catalogos/proveedores', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (p: any) => {
                const lista = (p.props.proveedores ?? []) as {
                    id: number;
                    nombre: string;
                    rfc: string | null;
                }[];
                const rfcBuscado = rfc.trim().toUpperCase();
                const nuevo = rfcBuscado
                    ? lista.find((x) => x.rfc === rfcBuscado)
                    : lista
                          .filter((x) => x.nombre === nombre.trim())
                          .sort((a, b) => b.id - a.id)[0];

                if (nuevo) {
                    ordenForm.setData('proveedor_id', String(nuevo.id));
                }

                proveedorForm.reset();
                setNuevoProveedor(false);
            },
        });
    };

    const cargarXml = (e: React.FormEvent) => {
        e.preventDefault();
        // orden?.id: el React Compiler memoiza dependencias fuera del handler, aun cuando no hay orden.
        xmlForm.post(`/ordenes-compra/${orden?.id}/xml`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => xmlForm.reset(),
        });
    };

    const generarClc = () => {
        if (
            !confirm(
                `¿Generar la CLC de la orden ${orden?.folio_completo} por ${money(orden?.importe ?? 0)}?`,
            )
        ) {
            return;
        }

        router.post(
            `/ordenes-compra/${orden?.id}/clc`,
            {},
            { preserveScroll: true },
        );
    };

    const registrarPago = (e: React.FormEvent) => {
        e.preventDefault();
        pagoForm.post(`/ordenes-compra/${orden?.id}/pago`, {
            preserveScroll: true,
        });
    };

    // De Recursos Materiales en adelante (cotización, comprometida, orden de compra, factura, CLC, pagada).
    const precios = estado >= 5;

    return (
        <>
            <Head title={`Requisición ${requisicion.folio_completo}`} />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/requisiciones">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">
                        Requisición {requisicion.folio_completo}
                    </h3>
                    <SuficienciaBadge suficiencia={requisicion.suficiencia} />
                </div>

                <div className="grid max-w-5xl gap-6">
                    {!!requisicion.extemporanea && (
                        <div
                            className={`flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm ${extemporaneaPendiente ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-green-200 bg-green-50 text-green-900'}`}
                        >
                            {extemporaneaPendiente ? (
                                <span>
                                    <strong>Requisición extemporánea:</strong>{' '}
                                    se capturó fuera del periodo de captura. No
                                    se puede enviar a revisión hasta que la
                                    autorice la Subdirección Administrativa
                                    (Adquisiciones).
                                </span>
                            ) : (
                                <span>
                                    <strong>Extemporánea autorizada</strong> por{' '}
                                    {requisicion.extemporanea_autorizador
                                        ?.name ?? '—'}{' '}
                                    el{' '}
                                    {fechaLocal(
                                        requisicion.extemporanea_autorizada_at,
                                    )}
                                    .
                                </span>
                            )}
                            {extemporaneaPendiente &&
                                puede('requisiciones-extemporaneas') && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            confirm(
                                                `¿Autorizar la requisición extemporánea ${requisicion.folio_completo}?`,
                                            ) && post('autorizar-extemporanea')
                                        }
                                    >
                                        <CheckCircle2 className="mr-1 h-4 w-4" />
                                        Autorizar extemporánea
                                    </Button>
                                )}
                        </div>
                    )}
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos Generales</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="font-semibold">Folio:</span>{' '}
                                {requisicion.folio}
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Folio Completo:
                                </span>{' '}
                                {requisicion.folio_completo}
                            </div>
                            <div>
                                <span className="font-semibold">Año:</span>{' '}
                                {requisicion.año}
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Fecha de trámite:
                                </span>{' '}
                                {requisicion.solicitado
                                    ? String(requisicion.solicitado).slice(
                                          0,
                                          10,
                                      )
                                    : '—'}
                            </div>
                            <div>
                                <span className="font-semibold">Registro:</span>{' '}
                                {requisicion.registro ?? '—'}
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Unidad Admin.:
                                </span>{' '}
                                {requisicion.cat_egreso_unidad_administrativa
                                    ?.nombre ?? '—'}
                            </div>
                            <div>
                                <span className="font-semibold">Estado:</span>{' '}
                                <Badge variant="secondary">
                                    {requisicion.cat_egreso_requisicion_estado
                                        ?.descripcion ?? '—'}
                                </Badge>
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Monto Total:
                                </span>{' '}
                                {requisicion.total
                                    ? money(requisicion.total)
                                    : '—'}
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Suficiencia:
                                </span>{' '}
                                {requisicion.suficiencia === null ||
                                requisicion.suficiencia === undefined ? (
                                    'Sin validar'
                                ) : (
                                    <SuficienciaBadge
                                        suficiencia={requisicion.suficiencia}
                                    />
                                )}
                                {requisicion.suficiencia_fecha && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        (
                                        {fechaLocal(
                                            requisicion.suficiencia_fecha,
                                        )}
                                        )
                                    </span>
                                )}
                            </div>
                            <div className="col-span-2">
                                <span className="font-semibold">
                                    Beneficiario:
                                </span>{' '}
                                {requisicion.beneficiario || '—'}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Concepto</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {requisicion.observaciones || 'Sin concepto'}
                        </CardContent>
                    </Card>

                    {detalles.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Partidas ({detalles.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-left">
                                                <th className="p-2">#</th>
                                                <th className="p-2">Fuente</th>
                                                <th className="p-2">Proy</th>
                                                <th className="p-2">Partida</th>
                                                <th className="p-2 text-center">
                                                    Cant
                                                </th>
                                                {precios && (
                                                    <th className="p-2 text-center">
                                                        Precio Unit.
                                                    </th>
                                                )}
                                                {precios && (
                                                    <th className="p-2 text-center">
                                                        Sub Total
                                                    </th>
                                                )}
                                                <th className="p-2">
                                                    Descripción / Artículo
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detalles.map(
                                                (d: any, i: number) => (
                                                    <tr
                                                        key={
                                                            d.id_tb_egreso_requisicion_detalle ||
                                                            i
                                                        }
                                                        className="border-b hover:bg-muted/20"
                                                    >
                                                        <td className="p-2 text-muted-foreground">
                                                            {i + 1}
                                                        </td>
                                                        <td className="p-2 text-xs">
                                                            {conNombre(
                                                                d.fuente,
                                                                nombresPresupuesto.fuentes,
                                                            )}
                                                        </td>
                                                        <td className="p-2 text-xs">
                                                            {conNombre(
                                                                d.proyecto,
                                                                nombresPresupuesto.proyectos,
                                                            )}
                                                        </td>
                                                        <td className="p-2 text-xs">
                                                            {conNombre(
                                                                d.partida,
                                                                nombresPresupuesto.partidas,
                                                            )}
                                                        </td>
                                                        <td className="p-2 text-center">
                                                            {numero(d.cantidad)}
                                                        </td>
                                                        {precios && (
                                                            <td className="p-2 text-center text-xs">
                                                                {d.precio_unitario
                                                                    ? money(
                                                                          d.precio_unitario,
                                                                      )
                                                                    : '—'}
                                                            </td>
                                                        )}
                                                        {precios && (
                                                            <td className="p-2 text-center text-xs">
                                                                {d.sub_total
                                                                    ? money(
                                                                          d.sub_total,
                                                                      )
                                                                    : '—'}
                                                            </td>
                                                        )}
                                                        <td className="p-2 text-xs whitespace-pre-wrap">
                                                            {d.descripcion ||
                                                                d.articulo ||
                                                                '—'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {validacion.partidas.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Presupuesto de las partidas
                                </CardTitle>
                                <p className="text-xs text-muted-foreground">
                                    Disponible = vigente (asignado ±
                                    modificaciones autorizadas) menos lo
                                    comprometido por otras requisiciones con
                                    suficiencia.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <TablaValidacion
                                    validacion={validacion}
                                    nombres={nombresPresupuesto}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {orden && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Orden de Compra {orden.folio_completo}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <span className="font-semibold">
                                            Proveedor:
                                        </span>{' '}
                                        {orden.proveedor?.nombre}{' '}
                                        {orden.proveedor?.rfc && (
                                            <span className="text-muted-foreground">
                                                ({orden.proveedor.rfc})
                                            </span>
                                        )}
                                    </div>
                                    <div>
                                        <span className="font-semibold">
                                            Fecha:
                                        </span>{' '}
                                        {String(orden.fecha).slice(0, 10)}
                                    </div>
                                    <div>
                                        <span className="font-semibold">
                                            Importe:
                                        </span>{' '}
                                        {money(orden.importe)}
                                    </div>
                                    <div>
                                        <span className="font-semibold">
                                            Factura:
                                        </span>{' '}
                                        {orden.xml_uuid ? (
                                            <Badge
                                                className="bg-green-100 text-green-800"
                                                variant="secondary"
                                            >
                                                Aceptada
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Pendiente
                                            </Badge>
                                        )}
                                    </div>
                                    <div>
                                        <span className="font-semibold">
                                            Pago:
                                        </span>{' '}
                                        {orden.pago_fecha ? (
                                            <Badge
                                                className="bg-green-100 text-green-800"
                                                variant="secondary"
                                            >
                                                Pagada
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Pendiente
                                            </Badge>
                                        )}
                                    </div>
                                    <div>
                                        <span className="font-semibold">
                                            CLC:
                                        </span>{' '}
                                        {orden.clc_folio_completo ? (
                                            <>
                                                {orden.clc_folio_completo}{' '}
                                                <span className="text-muted-foreground">
                                                    ({orden.clc_fecha})
                                                </span>
                                            </>
                                        ) : (
                                            <Badge variant="secondary">
                                                {orden.xml_uuid
                                                    ? 'Por generar'
                                                    : 'Al cargar la factura'}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`/ordenes-compra/${orden.id}/pdf`}
                                            target="_blank"
                                        >
                                            <FileText className="mr-1 h-4 w-4" />{' '}
                                            Imprimir orden de compra
                                        </a>
                                    </Button>
                                    {orden.xml_uuid && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={`/ordenes-compra/${orden.id}/xml`}
                                            >
                                                Descargar XML
                                            </a>
                                        </Button>
                                    )}
                                    {orden.clc_folio_completo && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={`/ordenes-compra/${orden.id}/clc`}
                                                target="_blank"
                                            >
                                                <FileText className="mr-1 h-4 w-4" />{' '}
                                                Imprimir CLC
                                            </a>
                                        </Button>
                                    )}
                                    {orden.xml_uuid &&
                                        !orden.clc_folio_completo &&
                                        puede('requisiciones-clc') && (
                                            <Button
                                                size="sm"
                                                onClick={generarClc}
                                            >
                                                <FileText className="mr-1 h-4 w-4" />{' '}
                                                Generar CLC
                                            </Button>
                                        )}
                                </div>

                                {orden.xml_uuid ? (
                                    <div className="grid grid-cols-2 gap-3 rounded-md border p-3">
                                        <div>
                                            <span className="font-semibold">
                                                UUID:
                                            </span>{' '}
                                            <span className="font-mono text-xs">
                                                {orden.xml_uuid}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="font-semibold">
                                                Emisor:
                                            </span>{' '}
                                            {orden.xml_nombre_emisor} (
                                            {orden.xml_rfc_emisor})
                                        </div>
                                        <div>
                                            <span className="font-semibold">
                                                Serie / Folio:
                                            </span>{' '}
                                            {[orden.xml_serie, orden.xml_folio]
                                                .filter(Boolean)
                                                .join(' ') || '—'}
                                        </div>
                                        <div>
                                            <span className="font-semibold">
                                                Total factura:
                                            </span>{' '}
                                            {money(orden.xml_total)}
                                        </div>
                                    </div>
                                ) : null}

                                {orden.xml_uuid && orden.pago_fecha && (
                                    <div className="grid grid-cols-2 gap-3 rounded-md border p-3">
                                        <div>
                                            <span className="font-semibold">
                                                Fecha de pago:
                                            </span>{' '}
                                            {orden.pago_fecha}
                                        </div>
                                        <div>
                                            <span className="font-semibold">
                                                Forma y referencia:
                                            </span>{' '}
                                            {orden.pago_forma === 'cheque'
                                                ? 'Cheque'
                                                : 'Transferencia'}{' '}
                                            {orden.pago_referencia}
                                        </div>
                                    </div>
                                )}

                                {orden.xml_uuid &&
                                    !orden.clc_folio_completo &&
                                    !orden.pago_fecha && (
                                        <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                            Factura aceptada: el área genera la
                                            CLC para solicitar el pago.
                                        </p>
                                    )}

                                {orden.clc_folio_completo &&
                                    !orden.pago_fecha &&
                                    puede('ordenes-compra-pagar') && (
                                        <form
                                            onSubmit={registrarPago}
                                            className="space-y-2 rounded-md border p-3"
                                        >
                                            <p className="font-semibold">
                                                Registrar pago
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Pago de {money(orden.importe)} a{' '}
                                                {orden.proveedor?.nombre}. Pasa
                                                el importe a "pagado" en el
                                                presupuesto.
                                            </p>
                                            <div className="flex flex-wrap items-end gap-2">
                                                <label className="grid gap-1 text-xs">
                                                    Fecha
                                                    <input
                                                        type="date"
                                                        value={
                                                            pagoForm.data
                                                                .pago_fecha
                                                        }
                                                        onChange={(e) =>
                                                            pagoForm.setData(
                                                                'pago_fecha',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="rounded-md border border-input px-2 py-1 text-sm"
                                                    />
                                                </label>
                                                <label className="grid gap-1 text-xs">
                                                    Forma de pago
                                                    <select
                                                        value={
                                                            pagoForm.data
                                                                .pago_forma
                                                        }
                                                        onChange={(e) =>
                                                            pagoForm.setData(
                                                                'pago_forma',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-sm"
                                                    >
                                                        <option value="transferencia">
                                                            Transferencia
                                                        </option>
                                                        <option value="cheque">
                                                            Cheque
                                                        </option>
                                                    </select>
                                                </label>
                                                <label className="grid gap-1 text-xs">
                                                    Referencia (folio de
                                                    transferencia o número de
                                                    cheque)
                                                    <input
                                                        value={
                                                            pagoForm.data
                                                                .pago_referencia
                                                        }
                                                        onChange={(e) =>
                                                            pagoForm.setData(
                                                                'pago_referencia',
                                                                e.target.value,
                                                            )
                                                        }
                                                        maxLength={100}
                                                        className="w-64 rounded-md border border-input px-2 py-1 text-sm"
                                                    />
                                                </label>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        !pagoForm.data
                                                            .pago_referencia ||
                                                        pagoForm.processing
                                                    }
                                                >
                                                    Registrar pago
                                                </Button>
                                            </div>
                                            {Object.values(pagoForm.errors).map(
                                                (error) => (
                                                    <p
                                                        key={error}
                                                        className="text-sm text-destructive"
                                                    >
                                                        {error}
                                                    </p>
                                                ),
                                            )}
                                        </form>
                                    )}

                                {!orden.xml_uuid &&
                                    puede('ordenes-compra-create') && (
                                        <form
                                            onSubmit={cargarXml}
                                            className="space-y-2 rounded-md border p-3"
                                        >
                                            <p className="font-semibold">
                                                Cargar factura (XML)
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                El total del CFDI debe coincidir
                                                con lo cotizado (
                                                {money(orden.importe)}) y el
                                                emisor debe ser el proveedor de
                                                la orden.
                                            </p>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="file"
                                                    accept=".xml,text/xml,application/xml"
                                                    onChange={(e) =>
                                                        xmlForm.setData(
                                                            'xml',
                                                            e.target
                                                                .files?.[0] ??
                                                                null,
                                                        )
                                                    }
                                                    className="text-sm"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        !xmlForm.data.xml ||
                                                        xmlForm.processing
                                                    }
                                                >
                                                    {xmlForm.processing
                                                        ? 'Validando...'
                                                        : 'Cargar XML'}
                                                </Button>
                                            </div>
                                            {xmlForm.errors.xml && (
                                                <p className="text-sm text-destructive">
                                                    {xmlForm.errors.xml}
                                                </p>
                                            )}
                                        </form>
                                    )}
                            </CardContent>
                        </Card>
                    )}

                    {modificaciones.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Modificaciones presupuestales relacionadas
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-1 text-sm">
                                    {modificaciones.map((m) => (
                                        <li key={m.id}>
                                            <Link
                                                href={`/modificaciones-presupuestales/${m.id}`}
                                                className="text-primary hover:underline"
                                            >
                                                {m.folio_completo}
                                            </Link>{' '}
                                            — {m.tipo} por {money(m.importe)} —{' '}
                                            <span className="font-medium">
                                                {m.estado}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}

                    {bitacoras.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Bitácora de Cambios ({bitacoras.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-left">
                                                <th className="p-2">#</th>
                                                <th className="p-2">Fecha</th>
                                                <th className="p-2">
                                                    Observaciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {bitacoras.map(
                                                (b: any, i: number) => (
                                                    <tr
                                                        key={
                                                            b.id_tb_egreso_requisicion_bitacora
                                                        }
                                                        className="border-b hover:bg-muted/20"
                                                    >
                                                        <td className="p-2 text-muted-foreground">
                                                            {i + 1}
                                                        </td>
                                                        <td className="p-2 text-xs text-muted-foreground">
                                                            {b.registro ?? '—'}
                                                        </td>
                                                        <td className="p-2 text-xs">
                                                            {b.observaciones}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/requisiciones">Volver</Link>
                        </Button>
                        {esPendiente && puedeEditarse && (
                            <>
                                <Button asChild>
                                    <Link href={`/requisiciones/${id}/edit`}>
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    onClick={() => setShowEnviar(true)}
                                    disabled={extemporaneaPendiente}
                                    title={
                                        extemporaneaPendiente
                                            ? 'Extemporánea: falta la autorización de la Subdirección Administrativa'
                                            : ''
                                    }
                                >
                                    <Send className="mr-2 h-4 w-4" /> Enviar a
                                    Revisión
                                </Button>
                            </>
                        )}
                        {esRevision && isAdmin && (
                            <>
                                <Button onClick={() => post('aprobar')}>
                                    Válida
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        const motivo = window.prompt(
                                            'Motivo del rechazo (obligatorio):',
                                        );

                                        if (motivo?.trim()) {
                                            router.post(
                                                `/requisiciones/${id}/rechazar`,
                                                { motivo },
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    No válida
                                </Button>
                            </>
                        )}
                        {(esAprobado || estado >= 5) && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/requisiciones/${id}/pdf`}
                                    target="_blank"
                                >
                                    <FileText className="mr-2 h-4 w-4" />{' '}
                                    Imprimir formato
                                </a>
                            </Button>
                        )}
                        {esAprobado && (
                            <Button onClick={() => post('recursos-materiales')}>
                                Enviar a Recursos Materiales
                            </Button>
                        )}
                        {esRecursosMateriales && (
                            <Button onClick={() => post('recibido')}>
                                Recibido - Iniciar Cotización
                            </Button>
                        )}
                        {esCotizacion &&
                            puedeEditarse &&
                            puede('requisiciones-edit') && (
                                <Button variant="outline" asChild>
                                    <Link href={`/requisiciones/${id}/edit`}>
                                        Editar Cotización
                                    </Link>
                                </Button>
                            )}
                        {esCotizacion &&
                            requisicion.suficiencia !== true &&
                            puede('requisiciones-suficiencia') && (
                                <Button
                                    onClick={validarSuficiencia}
                                    disabled={
                                        validando ||
                                        !(parseFloat(requisicion.total) > 0)
                                    }
                                    title={
                                        parseFloat(requisicion.total) > 0
                                            ? ''
                                            : 'Primero captura los precios de la cotización'
                                    }
                                >
                                    {validando
                                        ? 'Validando...'
                                        : 'Validar suficiencia presupuestal'}
                                </Button>
                            )}
                        {requisicion.suficiencia === false &&
                            puede('modificaciones-create') && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/modificaciones-presupuestales/crear?requisicion=${id}`}
                                    >
                                        Solicitar modificación presupuestal
                                    </Link>
                                </Button>
                            )}
                        {esComprometida &&
                            !orden &&
                            puede('ordenes-compra-create') && (
                                <Button onClick={() => setShowOrden(true)}>
                                    Asignar proveedor y generar orden de compra
                                </Button>
                            )}
                    </div>
                </div>
            </div>

            <Dialog open={showEnviar} onOpenChange={setShowEnviar}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            ¿Enviar esta requisición a revisión?
                        </DialogTitle>
                        <DialogDescription>
                            No podrá modificarla ni editarla después de
                            enviarla. La requisición será revisada por el área
                            de Presupuesto.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowEnviar(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={() =>
                                post('enviar', {
                                    onSuccess: () => setShowEnviar(false),
                                })
                            }
                        >
                            Enviar a Revisión
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showResultado} onOpenChange={setShowResultado}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle
                            className={`flex items-center gap-2 ${requisicion.suficiencia ? 'text-green-700' : 'text-destructive'}`}
                        >
                            {requisicion.suficiencia ? (
                                <CheckCircle2 className="h-5 w-5" />
                            ) : (
                                <XCircle className="h-5 w-5" />
                            )}
                            {requisicion.suficiencia
                                ? 'Con suficiencia presupuestal'
                                : 'Sin suficiencia presupuestal'}
                        </DialogTitle>
                        <DialogDescription>
                            {requisicion.suficiencia
                                ? `El monto de ${money(validacion.total)} quedó comprometido. Ya se puede generar la orden de compra.`
                                : 'Al menos una partida no alcanza. Se requiere una modificación presupuestal (traspaso o ampliación) antes de volver a validar.'}
                        </DialogDescription>
                    </DialogHeader>
                    <TablaValidacion
                        validacion={validacion}
                        nombres={nombresPresupuesto}
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowResultado(false)}
                        >
                            Cerrar
                        </Button>
                        {requisicion.suficiencia === false &&
                            puede('modificaciones-create') && (
                                <Button asChild>
                                    <Link
                                        href={`/modificaciones-presupuestales/crear?requisicion=${id}`}
                                    >
                                        Solicitar modificación presupuestal
                                    </Link>
                                </Button>
                            )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showOrden} onOpenChange={setShowOrden}>
                <DialogContent>
                    <form onSubmit={generarOrden} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                Asignar proveedor y generar orden de compra
                            </DialogTitle>
                            <DialogDescription>
                                Requisición {requisicion.folio_completo} por{' '}
                                {money(requisicion.total)}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Proveedor
                            </label>
                            <SearchableSelect
                                value={ordenForm.data.proveedor_id}
                                onChange={(v) =>
                                    ordenForm.setData('proveedor_id', v)
                                }
                                options={proveedores.map((p) => ({
                                    value: String(p.id),
                                    label: p.rfc
                                        ? `${p.nombre} (${p.rfc})`
                                        : p.nombre,
                                }))}
                                showValue={false}
                                panelWidth={420}
                                inline
                            />
                            {proveedores.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    No hay proveedores activos.
                                </p>
                            )}
                            {ordenForm.errors.proveedor_id && (
                                <p className="text-sm text-destructive">
                                    {ordenForm.errors.proveedor_id}
                                </p>
                            )}
                            {puede('proveedores-create') && !nuevoProveedor && (
                                <button
                                    type="button"
                                    onClick={() => setNuevoProveedor(true)}
                                    className="text-xs text-primary hover:underline"
                                >
                                    + El proveedor no está en la lista: darlo de
                                    alta
                                </button>
                            )}
                        </div>
                        {nuevoProveedor && (
                            <div className="space-y-2 rounded-md border p-3">
                                <p className="text-sm font-medium">
                                    Nuevo proveedor
                                </p>
                                <input
                                    value={proveedorForm.data.nombre}
                                    onChange={(e) =>
                                        proveedorForm.setData(
                                            'nombre',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nombre o razón social"
                                    maxLength={300}
                                    className="block w-full rounded-md border border-input px-2 py-1 text-sm"
                                />
                                <input
                                    value={proveedorForm.data.rfc}
                                    onChange={(e) =>
                                        proveedorForm.setData(
                                            'rfc',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="RFC (se valida contra la factura)"
                                    maxLength={13}
                                    className="block w-full rounded-md border border-input px-2 py-1 font-mono text-sm"
                                />
                                {Object.values(proveedorForm.errors).map(
                                    (error) => (
                                        <p
                                            key={error}
                                            className="text-xs text-destructive"
                                        >
                                            {error}
                                        </p>
                                    ),
                                )}
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={guardarProveedor}
                                        disabled={
                                            !proveedorForm.data.nombre.trim() ||
                                            proveedorForm.processing
                                        }
                                    >
                                        Guardar y asignar
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            proveedorForm.reset();
                                            proveedorForm.clearErrors();
                                            setNuevoProveedor(false);
                                        }}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </div>
                        )}
                        <div className="space-y-1">
                            <label className="text-sm font-medium">Fecha</label>
                            <input
                                type="date"
                                value={ordenForm.data.fecha}
                                onChange={(e) =>
                                    ordenForm.setData('fecha', e.target.value)
                                }
                                className="block rounded-md border-input px-2 py-1 text-sm shadow-sm"
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Observaciones
                            </label>
                            <textarea
                                value={ordenForm.data.observaciones}
                                onChange={(e) =>
                                    ordenForm.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm"
                                rows={3}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowOrden(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    !ordenForm.data.proveedor_id ||
                                    ordenForm.processing
                                }
                            >
                                Generar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Show.layout = { breadcrumbs };
