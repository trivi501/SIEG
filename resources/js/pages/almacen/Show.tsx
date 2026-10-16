import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileText, PackageMinus, PackagePlus } from 'lucide-react';
import { useState } from 'react';
import { situacion } from '@/components/almacen-comun';
import type { OrdenAlmacen } from '@/components/almacen-comun';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fecha, money, numero } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

interface Concepto {
    detalle_id: number;
    descripcion: string;
    pedido: number;
    precio_unitario: number;
    recibido: number;
    entregado: number;
    por_recibir: number;
    existencia: number;
}

interface Movimiento {
    id: number;
    folio: string;
    fecha: string | null;
    usuario: string | null;
    recibe?: string | null;
    observaciones: string | null;
    renglones: number;
    cantidad: number;
}

type Tipo = 'entrada' | 'salida';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Almacén', href: '/almacen' },
    { title: 'Orden de compra', href: '#' },
];

export default function Show({
    orden,
    conceptos,
    entradas,
    salidas,
    puede,
}: {
    orden: OrdenAlmacen & {
        fecha: string | null;
        importe: number;
        factura: string | null;
    };
    conceptos: Concepto[];
    entradas: Movimiento[];
    salidas: Movimiento[];
    puede: { entrada: boolean; salida: boolean };
}) {
    const [tipo, setTipo] = useState<Tipo | null>(null);
    const [datos, setDatos] = useState({
        fecha: new Date().toISOString().slice(0, 10),
        recibe: '',
        observaciones: '',
    });
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [errores, setErrores] = useState<string[]>([]);
    const [guardando, setGuardando] = useState(false);
    const s = situacion(orden);
    const limite = (c: Concepto) =>
        tipo === 'salida' ? c.existencia : c.por_recibir;

    // Al abrir, propone todo lo que falta por recibir (entrada) o lo que hay en existencia (salida).
    const abrir = (t: Tipo) => {
        setTipo(t);
        setErrores([]);
        setDatos({
            fecha: new Date().toISOString().slice(0, 10),
            recibe: '',
            observaciones: '',
        });
        setCantidades(
            Object.fromEntries(
                conceptos.map((c) => [
                    c.detalle_id,
                    String(t === 'salida' ? c.existencia : c.por_recibir),
                ]),
            ),
        );
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        setGuardando(true);
        router.post(
            `/almacen/${orden.id}/${tipo === 'salida' ? 'salidas' : 'entradas'}`,
            { ...datos, cantidades },
            {
                preserveScroll: true,
                onSuccess: () => setTipo(null),
                onError: (er) => setErrores(Object.values(er)),
                onFinish: () => setGuardando(false),
            },
        );
    };

    const movimientos = (
        titulo: string,
        lista: Movimiento[],
        pdfs: (m: Movimiento) => { href: string; texto: string }[],
        conRecibe = false,
    ) => (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">
                    {titulo} ({lista.length})
                </CardTitle>
            </CardHeader>
            <CardContent>
                {lista.length === 0 ? (
                    <p className="py-2 text-sm text-muted-foreground">
                        Todavía no hay registros.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Folio</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead className="text-right">
                                    Cantidad
                                </TableHead>
                                {conRecibe && <TableHead>Recibe</TableHead>}
                                <TableHead>Registró</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lista.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell className="font-medium">
                                        {m.folio}
                                        {m.observaciones && (
                                            <div className="max-w-xs text-xs font-normal whitespace-normal text-muted-foreground">
                                                {m.observaciones}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>{fecha(m.fecha)}</TableCell>
                                    <TableCell className="text-right">
                                        {numero(m.cantidad)}
                                        <div className="text-xs text-muted-foreground">
                                            {m.renglones} concepto
                                            {m.renglones === 1 ? '' : 's'}
                                        </div>
                                    </TableCell>
                                    {conRecibe && (
                                        <TableCell className="text-xs">
                                            {m.recibe ?? '—'}
                                        </TableCell>
                                    )}
                                    <TableCell className="text-xs text-muted-foreground">
                                        {m.usuario ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {pdfs(m).map((p) => (
                                                <Button
                                                    key={p.href}
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={p.href}
                                                        target="_blank"
                                                        title={p.texto}
                                                    >
                                                        <FileText className="mr-1 h-4 w-4" />
                                                        {p.texto}
                                                    </a>
                                                </Button>
                                            ))}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );

    return (
        <>
            <Head title={`Almacén ${orden.orden}`} />
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/almacen">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">
                        Orden de compra {orden.orden}
                    </h3>
                    <Badge variant="secondary" className={s.clase}>
                        {s.texto}
                    </Badge>
                    <div className="ml-auto flex flex-wrap gap-2">
                        {puede.entrada && (
                            <Button
                                onClick={() => abrir('entrada')}
                                disabled={orden.por_recibir <= 0}
                                title={
                                    orden.por_recibir <= 0
                                        ? 'Ya se recibió todo lo de la orden de compra'
                                        : ''
                                }
                            >
                                <PackagePlus className="mr-1 h-4 w-4" />
                                Registrar entrada
                            </Button>
                        )}
                        {puede.salida && (
                            <Button
                                variant="outline"
                                onClick={() => abrir('salida')}
                                disabled={orden.existencia <= 0}
                                title={
                                    orden.existencia <= 0
                                        ? 'No hay existencia para entregar'
                                        : ''
                                }
                            >
                                <PackageMinus className="mr-1 h-4 w-4" />
                                Registrar salida
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardContent className="grid gap-3 pt-6 text-sm sm:grid-cols-3">
                        <div>
                            <span className="font-semibold">Requisición:</span>{' '}
                            {orden.requisicion ?? '—'}
                        </div>
                        <div>
                            <span className="font-semibold">Fecha O.C.:</span>{' '}
                            {fecha(orden.fecha)}
                        </div>
                        <div>
                            <span className="font-semibold">Importe:</span>{' '}
                            {money(orden.importe)}
                        </div>
                        <div className="sm:col-span-2">
                            <span className="font-semibold">Área:</span>{' '}
                            {orden.unidad ?? '—'}
                        </div>
                        <div>
                            <span className="font-semibold">Factura:</span>{' '}
                            {orden.factura ?? 'Pendiente'}
                        </div>
                        <div className="sm:col-span-3">
                            <span className="font-semibold">Proveedor:</span>{' '}
                            {orden.proveedor ?? '—'}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">
                            Conforme a la orden de compra
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead className="text-right">
                                        Pedido
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Recibido
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Por recibir
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Entregado
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Existencia
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {conceptos.map((c) => (
                                    <TableRow key={c.detalle_id}>
                                        <TableCell className="max-w-md whitespace-normal">
                                            {c.descripcion}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.pedido)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.recibido)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.por_recibir)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.entregado)}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {numero(c.existencia)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {movimientos('Entradas de almacén', entradas, (m) => [
                    {
                        href: `/almacen/entradas/${m.id}/pdf`,
                        texto: 'Entrada',
                    },
                    {
                        href: `/almacen/entradas/${m.id}/acta`,
                        texto: 'Acta de entrega',
                    },
                ])}
                {movimientos(
                    'Salidas de almacén',
                    salidas,
                    (m) => [
                        {
                            href: `/almacen/salidas/${m.id}/pdf`,
                            texto: 'Salida',
                        },
                    ],
                    true,
                )}
            </div>

            <Dialog
                open={tipo !== null}
                onOpenChange={(a) => !a && !guardando && setTipo(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <form onSubmit={guardar} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {tipo === 'salida'
                                    ? 'Registrar salida de almacén'
                                    : 'Registrar entrada de almacén'}
                            </DialogTitle>
                            <DialogDescription>
                                {tipo === 'salida'
                                    ? `Lo que se entrega a ${orden.unidad ?? 'el área'}; hasta lo que hay en existencia.`
                                    : 'Lo que se recibe del proveedor conforme a la orden de compra; hasta lo que falta por recibir. Puede ser por partes.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-sm">
                                Fecha
                                <input
                                    type="date"
                                    value={datos.fecha}
                                    onChange={(e) =>
                                        setDatos({
                                            ...datos,
                                            fecha: e.target.value,
                                        })
                                    }
                                    className="rounded-md border border-input px-2 py-1.5"
                                />
                            </label>
                            {tipo === 'salida' && (
                                <label className="grid gap-1 text-sm">
                                    Recibe (del área)
                                    <input
                                        value={datos.recibe}
                                        onChange={(e) =>
                                            setDatos({
                                                ...datos,
                                                recibe: e.target.value,
                                            })
                                        }
                                        maxLength={200}
                                        placeholder="Nombre de quien recibe"
                                        className="rounded-md border border-input px-2 py-1.5"
                                    />
                                </label>
                            )}
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead className="text-right">
                                        {tipo === 'salida'
                                            ? 'Existencia'
                                            : 'Por recibir'}
                                    </TableHead>
                                    <TableHead className="w-32 text-right">
                                        Cantidad
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {conceptos.map((c) => (
                                    <TableRow key={c.detalle_id}>
                                        <TableCell className="max-w-sm text-xs whitespace-normal">
                                            {c.descripcion}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(limite(c))}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <input
                                                type="number"
                                                min={0}
                                                max={limite(c)}
                                                step="0.01"
                                                disabled={limite(c) <= 0}
                                                value={
                                                    cantidades[c.detalle_id] ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    setCantidades({
                                                        ...cantidades,
                                                        [c.detalle_id]:
                                                            e.target.value,
                                                    })
                                                }
                                                className="w-28 rounded-md border border-input px-2 py-1 text-right disabled:opacity-50"
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <label className="grid gap-1 text-sm">
                            Observaciones
                            <textarea
                                value={datos.observaciones}
                                onChange={(e) =>
                                    setDatos({
                                        ...datos,
                                        observaciones: e.target.value,
                                    })
                                }
                                maxLength={1000}
                                rows={2}
                                className="rounded-md border border-input px-2 py-1.5"
                            />
                        </label>
                        {errores.map((er) => (
                            <p key={er} className="text-sm text-destructive">
                                {er}
                            </p>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setTipo(null)}
                                disabled={guardando}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={guardando}>
                                {guardando ? 'Guardando...' : 'Registrar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Show.layout = { breadcrumbs };
