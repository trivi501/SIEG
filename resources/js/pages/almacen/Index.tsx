import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Paginas, situacion } from '@/components/almacen-comun';
import type { OrdenAlmacen, Paginado } from '@/components/almacen-comun';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fechaLocal, numero } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Almacén', href: '/almacen' },
    { title: 'Órdenes de compra', href: '/almacen' },
];

export default function Index({
    ordenes,
    filtros,
}: {
    ordenes: Paginado<OrdenAlmacen>;
    filtros: { buscar?: string; situacion?: string };
}) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const ir = (cambios: Record<string, string>) =>
        router.get(
            '/almacen',
            Object.fromEntries(
                Object.entries({
                    buscar,
                    situacion: filtros.situacion ?? '',
                    ...cambios,
                }).filter(([, v]) => v),
            ),
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Almacén" />
            <div className="space-y-4 p-6">
                <div>
                    <h3 className="text-lg font-medium">
                        Almacén · Órdenes de compra
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Órdenes de compra que Recursos Materiales asignó al
                        almacén. Las entradas (lo que se recibe del proveedor) y
                        las salidas (lo que se entrega al área) se registran por
                        partes, conforme a la orden de compra.
                    </p>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            <div className="flex rounded-md border text-xs font-normal">
                                {(
                                    [
                                        ['', 'Todas'],
                                        ['por_recibir', 'Por recibir'],
                                        ['en_existencia', 'En existencia'],
                                        ['entregadas', 'Entregadas'],
                                    ] as const
                                ).map(([valor, texto]) => (
                                    <button
                                        key={valor}
                                        type="button"
                                        onClick={() => ir({ situacion: valor })}
                                        className={`px-2.5 py-1 first:rounded-l-md last:rounded-r-md ${(filtros.situacion ?? '') === valor ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                    >
                                        {texto}
                                    </button>
                                ))}
                            </div>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    ir({});
                                }}
                                className="ml-auto flex items-center gap-1"
                            >
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        type="search"
                                        value={buscar}
                                        onChange={(e) =>
                                            setBuscar(e.target.value)
                                        }
                                        placeholder="O.C., requisición, proveedor, área…"
                                        className="w-72 rounded-md border border-input bg-background py-1.5 pr-2 pl-7 text-sm font-normal"
                                    />
                                </div>
                                {(filtros.buscar || filtros.situacion) && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setBuscar('');
                                            router.get('/almacen');
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                            </form>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Orden de compra</TableHead>
                                    <TableHead>Requisición</TableHead>
                                    <TableHead>Área</TableHead>
                                    <TableHead>Proveedor</TableHead>
                                    <TableHead className="text-right">
                                        Pedido
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Recibido
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Entregado
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Existencia
                                    </TableHead>
                                    <TableHead>Situación</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {ordenes.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={10}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            No hay órdenes de compra en el
                                            almacén con estos filtros.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {ordenes.data.map((o) => {
                                    const s = situacion(o);

                                    return (
                                        <TableRow key={o.id}>
                                            <TableCell className="font-medium">
                                                {o.orden}
                                                <div className="text-xs font-normal text-muted-foreground">
                                                    Asignada{' '}
                                                    {fechaLocal(
                                                        o.asignada,
                                                        false,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {o.requisicion ?? '—'}
                                            </TableCell>
                                            <TableCell className="max-w-56 text-xs whitespace-normal">
                                                {o.unidad ?? '—'}
                                            </TableCell>
                                            <TableCell className="max-w-56 text-xs whitespace-normal">
                                                {o.proveedor ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {numero(o.pedido)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {numero(o.recibido)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {numero(o.entregado)}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                {numero(o.existencia)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="secondary"
                                                    className={s.clase}
                                                >
                                                    {s.texto}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/almacen/${o.id}`}
                                                        title="Ver entradas y salidas"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                        <Paginas paginado={ordenes} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Index.layout = { breadcrumbs };
