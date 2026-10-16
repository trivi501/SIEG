import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Paginas } from '@/components/almacen-comun';
import type { Paginado } from '@/components/almacen-comun';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { money, numero } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

interface Concepto {
    detalle_id: number;
    descripcion: string;
    orden_id: number;
    orden: string;
    requisicion: string | null;
    unidad: string | null;
    proveedor: string | null;
    pedido: number;
    recibido: number;
    entregado: number;
    existencia: number;
    precio_unitario: number;
    valor: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Almacén', href: '/almacen' },
    { title: 'Inventario', href: '/almacen/inventario' },
];

export default function Inventario({
    conceptos,
    filtros,
    totales,
}: {
    conceptos: Paginado<Concepto>;
    filtros: { buscar?: string; todo?: string };
    totales: { existencia: number; valor: number };
}) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const ir = (cambios: Record<string, string>) =>
        router.get(
            '/almacen/inventario',
            Object.fromEntries(
                Object.entries({
                    buscar,
                    todo: filtros.todo ?? '',
                    ...cambios,
                }).filter(([, v]) => v),
            ),
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Inventario de almacén" />
            <div className="space-y-4 p-6">
                <div>
                    <h3 className="text-lg font-medium">
                        Inventario de entradas de almacén
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Por concepto de cada orden de compra: lo recibido, lo
                        entregado al área y lo que queda en existencia (valuado
                        al precio de la orden de compra).
                    </p>
                </div>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex flex-wrap items-center gap-3 text-base">
                            Existencia {numero(totales.existencia)} ·{' '}
                            {money(totales.valor)}
                            <label className="flex items-center gap-1 text-xs font-normal">
                                <input
                                    type="checkbox"
                                    checked={!!filtros.todo}
                                    onChange={(e) =>
                                        ir({
                                            todo: e.target.checked ? '1' : '',
                                        })
                                    }
                                />
                                Incluir lo ya entregado
                            </label>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    ir({});
                                }}
                                className="relative ml-auto"
                            >
                                <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    type="search"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    placeholder="Descripción, O.C., requisición, área…"
                                    className="w-72 rounded-md border border-input bg-background py-1.5 pr-2 pl-7 text-sm font-normal"
                                />
                            </form>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead>Orden de compra</TableHead>
                                    <TableHead>Área</TableHead>
                                    <TableHead className="text-right">
                                        Recibido
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Entregado
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Existencia
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Valor
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {conceptos.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            No hay existencias con estos
                                            filtros.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {conceptos.data.map((c) => (
                                    <TableRow
                                        key={`${c.orden_id}-${c.detalle_id}`}
                                    >
                                        <TableCell className="max-w-md whitespace-normal">
                                            {c.descripcion}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <Link
                                                href={`/almacen/${c.orden_id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {c.orden}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                {c.requisicion} ·{' '}
                                                {c.proveedor ?? '—'}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-48 text-xs whitespace-normal">
                                            {c.unidad ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.recibido)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {numero(c.entregado)}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {numero(c.existencia)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {money(c.valor)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Paginas paginado={conceptos} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Inventario.layout = { breadcrumbs };
