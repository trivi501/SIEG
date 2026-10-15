import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import EstadoModificacionBadge from '@/components/estado-modificacion-badge';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fecha, fechaLocal, money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';

interface RequisicionPartida {
    id: number;
    folio: string;
    fecha: string | null;
    estado_id: number;
    estado: string | null;
    momento: string | null;
    importe: number;
    suficiencia_fecha: string | null;
    cancelada_at: string | null;
    orden: {
        id: number;
        folio: string;
        fecha: string | null;
        importe: number;
        proveedor: string | null;
        factura: string | null;
        factura_fecha: string | null;
        clc: string | null;
        clc_fecha: string | null;
        clc_estado: string | null;
        pago_fecha: string | null;
        cancelada: boolean;
    } | null;
}

interface ModificacionPartida {
    id: number;
    folio: string;
    tipo: string;
    estado: string;
    lado: 'Emisor' | 'Receptor';
    contraparte: string | null;
    mes: string | null;
    importe: number;
    fecha: string | null;
    resuelto_at: string | null;
}

interface Detalle {
    requisiciones: RequisicionPartida[];
    modificaciones: ModificacionPartida[];
}

/** Fecha guardada como Y-m-d (o Y-m-d H:i:s de MySQL) en dd/mm/aaaa, sin cambiar de zona. */
const dia = (valor: string | null | undefined) => fecha(valor);

const coloresMomento: Record<string, string> = {
    'En trámite': 'bg-slate-100 text-slate-700',
    Comprometido: 'bg-amber-100 text-amber-800',
    Devengado: 'bg-blue-100 text-blue-800',
    Pagado: 'bg-green-100 text-green-800',
};

/**
 * Detalle de una partida del ejercicio del gasto: requisiciones que la usan (con su orden de compra,
 * CLC, proveedor, importe y fechas) y modificaciones presupuestales en que es emisora o receptora.
 */
export default function DetallePartida({
    linea,
    onClose,
}: {
    linea: Required<LineaPresupuesto> | null;
    onClose: () => void;
}) {
    // Respuesta por clave: al abrir otra partida no se muestra la anterior mientras carga.
    const [respuesta, setRespuesta] = useState<{
        clave: string;
        detalle?: Detalle;
        error?: string;
    } | null>(null);
    const clave = linea?.clave ?? null;
    const actual = respuesta?.clave === clave ? respuesta : null;
    const detalle = actual?.detalle ?? null;
    const error = actual?.error ?? null;

    useEffect(() => {
        if (!clave) {
            return;
        }

        const control = new AbortController();

        fetch(
            `/requisiciones/gasto/detalle?${new URLSearchParams({ clave }).toString()}`,
            {
                headers: { Accept: 'application/json' },
                signal: control.signal,
            },
        )
            .then((r) => {
                if (!r.ok) {
                    throw new Error(`Error ${r.status}`);
                }

                return r.json() as Promise<Detalle>;
            })
            .then((d) => setRespuesta({ clave, detalle: d }))
            .catch((e: Error) => {
                if (e.name !== 'AbortError') {
                    setRespuesta({
                        clave,
                        error: 'No se pudo cargar el detalle de la partida.',
                    });
                }
            });

        return () => control.abort();
    }, [clave]);

    const vigentes =
        detalle?.requisiciones.filter((r) => r.momento !== null) ?? [];
    const totalRequisiciones = vigentes.reduce((s, r) => s + r.importe, 0);
    const totalModificaciones =
        detalle?.modificaciones
            .filter((m) => m.estado === 'autorizada')
            .reduce((s, m) => s + m.importe, 0) ?? 0;

    return (
        <Dialog open={linea !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-6xl [&>*]:min-w-0">
                <DialogHeader>
                    <DialogTitle>
                        Partida {linea?.partida} {linea?.nombre_partida}
                    </DialogTitle>
                    <DialogDescription>
                        {linea?.unidad_clave} {linea?.unidad} · Proyecto{' '}
                        {linea?.proyecto} {linea?.nombre_proyecto} · Fuente{' '}
                        {linea?.fuente} {linea?.nombre_fuente}
                    </DialogDescription>
                </DialogHeader>

                {linea && (
                    <div className="grid grid-cols-2 gap-2 rounded-md bg-muted/40 p-3 text-xs sm:grid-cols-4 lg:grid-cols-7">
                        {(
                            [
                                ['Aprobado', linea.asignado],
                                ['Modificado', linea.modificado],
                                ['Vigente', linea.vigente],
                                ['Comprometido', linea.comprometido],
                                ['Devengado', linea.devengado],
                                ['Pagado', linea.pagado],
                                ['Disponible', linea.disponible],
                            ] as const
                        ).map(([titulo, valor]) => (
                            <div key={titulo}>
                                <div className="text-muted-foreground">
                                    {titulo}
                                </div>
                                <div
                                    className={`font-medium ${Number(valor) < 0 ? 'text-destructive' : ''}`}
                                >
                                    {money(valor)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {error && <p className="text-sm text-destructive">{error}</p>}
                {!detalle && !error && (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Cargando...
                    </p>
                )}

                {detalle && (
                    <div className="space-y-6">
                        <section>
                            <h4 className="mb-2 text-sm font-semibold">
                                Requisiciones relacionadas (
                                {detalle.requisiciones.length})
                            </h4>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Requisición</TableHead>
                                            <TableHead>Estado</TableHead>
                                            <TableHead className="text-right">
                                                Importe partida
                                            </TableHead>
                                            <TableHead>Suficiencia</TableHead>
                                            <TableHead>
                                                Orden de compra
                                            </TableHead>
                                            <TableHead>Proveedor</TableHead>
                                            <TableHead>Factura</TableHead>
                                            <TableHead>CLC</TableHead>
                                            <TableHead>Pago</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {detalle.requisiciones.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={9}
                                                    className="text-center text-muted-foreground"
                                                >
                                                    Ninguna requisición usa esta
                                                    partida.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {detalle.requisiciones.map((r) => (
                                            <TableRow
                                                key={r.id}
                                                className={
                                                    r.momento === null
                                                        ? 'opacity-60'
                                                        : ''
                                                }
                                            >
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    <Link
                                                        href={`/requisiciones/${r.id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {r.folio}
                                                    </Link>
                                                    <div className="text-muted-foreground">
                                                        {dia(r.fecha)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    <div>{r.estado ?? '—'}</div>
                                                    {r.momento ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className={
                                                                coloresMomento[
                                                                    r.momento
                                                                ] ?? ''
                                                            }
                                                        >
                                                            {r.momento}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            No cuenta en el
                                                            presupuesto
                                                            {r.cancelada_at &&
                                                                ` (cancelada ${fechaLocal(r.cancelada_at, false)})`}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-xs whitespace-nowrap">
                                                    {money(r.importe)}
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {fechaLocal(
                                                        r.suficiencia_fecha,
                                                        false,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {r.orden ? (
                                                        <>
                                                            <div className="font-medium">
                                                                {r.orden.folio}
                                                                {r.orden
                                                                    .cancelada && (
                                                                    <span className="ml-1 font-normal text-red-700">
                                                                        (cancelada)
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                {dia(
                                                                    r.orden
                                                                        .fecha,
                                                                )}{' '}
                                                                ·{' '}
                                                                {money(
                                                                    r.orden
                                                                        .importe,
                                                                )}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="max-w-48 text-xs whitespace-normal">
                                                    {r.orden?.proveedor ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {r.orden?.factura ? (
                                                        <>
                                                            <div>
                                                                {
                                                                    r.orden
                                                                        .factura
                                                                }
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                {fechaLocal(
                                                                    r.orden
                                                                        .factura_fecha,
                                                                    false,
                                                                )}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {r.orden?.clc ? (
                                                        <>
                                                            <div className="font-medium">
                                                                {r.orden.clc}
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                {dia(
                                                                    r.orden
                                                                        .clc_fecha,
                                                                )}{' '}
                                                                ·{' '}
                                                                {
                                                                    r.orden
                                                                        .clc_estado
                                                                }
                                                            </div>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {dia(r.orden?.pago_fecha)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {vigentes.length > 0 && (
                                            <TableRow className="font-semibold">
                                                <TableCell
                                                    colSpan={2}
                                                    className="text-xs"
                                                >
                                                    Total (sin canceladas ni
                                                    rechazadas)
                                                </TableCell>
                                                <TableCell className="text-right text-xs whitespace-nowrap">
                                                    {money(totalRequisiciones)}
                                                </TableCell>
                                                <TableCell colSpan={6} />
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </section>

                        <section>
                            <h4 className="mb-2 text-sm font-semibold">
                                Modificaciones presupuestales (
                                {detalle.modificaciones.length})
                            </h4>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Folio</TableHead>
                                            <TableHead>Tipo</TableHead>
                                            <TableHead>Esta partida</TableHead>
                                            <TableHead>Contraparte</TableHead>
                                            <TableHead>Periodo</TableHead>
                                            <TableHead className="text-right">
                                                Importe
                                            </TableHead>
                                            <TableHead>Estado</TableHead>
                                            <TableHead>Fechas</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {detalle.modificaciones.length ===
                                            0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={8}
                                                    className="text-center text-muted-foreground"
                                                >
                                                    Esta partida no tiene
                                                    modificaciones.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {detalle.modificaciones.map((m) => (
                                            <TableRow key={m.id}>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    <Link
                                                        href={`/modificaciones-presupuestales/${m.id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {m.folio}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {m.tipo}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {m.lado}
                                                </TableCell>
                                                <TableCell className="max-w-64 text-xs whitespace-normal text-muted-foreground">
                                                    {m.contraparte ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-xs capitalize">
                                                    {m.mes ?? '—'}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right text-xs whitespace-nowrap ${m.importe < 0 ? 'text-destructive' : 'text-green-700 dark:text-green-400'}`}
                                                >
                                                    {m.importe > 0 ? '+' : ''}
                                                    {money(m.importe)}
                                                </TableCell>
                                                <TableCell>
                                                    <EstadoModificacionBadge
                                                        estado={m.estado}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    <div>
                                                        Solicitada{' '}
                                                        {fechaLocal(
                                                            m.fecha,
                                                            false,
                                                        )}
                                                    </div>
                                                    {m.resuelto_at && (
                                                        <div className="text-muted-foreground">
                                                            Resuelta{' '}
                                                            {fechaLocal(
                                                                m.resuelto_at,
                                                                false,
                                                            )}
                                                        </div>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {detalle.modificaciones.length > 0 && (
                                            <TableRow className="font-semibold">
                                                <TableCell
                                                    colSpan={5}
                                                    className="text-xs"
                                                >
                                                    Neto autorizado
                                                </TableCell>
                                                <TableCell className="text-right text-xs whitespace-nowrap">
                                                    {money(totalModificaciones)}
                                                </TableCell>
                                                <TableCell colSpan={2} />
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </section>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
