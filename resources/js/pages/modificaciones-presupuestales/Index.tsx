import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, FileText, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import EstadoModificacionBadge from '@/components/estado-modificacion-badge';
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
import { fechaLocal, money } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Modificaciones Presupuestales',
        href: '/modificaciones-presupuestales',
    },
];

interface Modificacion {
    id: number;
    folio_completo: string;
    tipo: string;
    estado: string;
    origen_descripcion: string | null;
    destino_descripcion: string | null;
    importe: number;
    created_at: string;
    resuelto_at: string | null;
    solicitante?: { name: string } | null;
    autorizador?: { name: string } | null;
}

interface Paginado<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

export default function Index({
    modificaciones,
    filters,
    tipos,
}: {
    modificaciones: Paginado<Modificacion>;
    filters: Record<string, string>;
    tipos: Record<string, string>;
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const [f, setF] = useState({
        folio: filters?.folio ?? '',
        tipo: filters?.tipo ?? '',
        estado: filters?.estado ?? '',
        partida: filters?.partida ?? '',
    });

    const aplicar = (valores = f) => {
        const params = Object.fromEntries(
            Object.entries(valores).filter(([, v]) => v),
        );
        router.get('/modificaciones-presupuestales', params, {
            preserveState: true,
            replace: true,
        });
    };
    const limpiar = () => {
        setF({ folio: '', tipo: '', estado: '', partida: '' });
        router.get('/modificaciones-presupuestales', {}, { replace: true });
    };
    const alEnter = (e: React.KeyboardEvent) => e.key === 'Enter' && aplicar();
    const elegir = (campo: 'tipo' | 'estado', valor: string) => {
        const nuevos = { ...f, [campo]: valor };
        setF(nuevos);
        aplicar(nuevos);
    };

    return (
        <>
            <Head title="Modificaciones Presupuestales" />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-medium">
                            Modificaciones Presupuestales
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Historial de traspasos, reducciones y ampliaciones.
                        </p>
                    </div>
                    {permisos.includes('modificaciones-create') && (
                        <Button asChild>
                            <Link href="/modificaciones-presupuestales/crear">
                                <Plus className="mr-2 h-4 w-4" /> Nueva
                                modificación
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Solicitudes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="p-2">
                                        Folio
                                        <input
                                            value={f.folio}
                                            onChange={(e) =>
                                                setF({
                                                    ...f,
                                                    folio: e.target.value,
                                                })
                                            }
                                            onKeyDown={alEnter}
                                            placeholder="..."
                                            className="mt-1 block w-28 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                        />
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Tipo
                                        <select
                                            value={f.tipo}
                                            onChange={(e) =>
                                                elegir('tipo', e.target.value)
                                            }
                                            className="mt-1 block w-28 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                        >
                                            <option value="">Todos</option>
                                            {Object.entries(tipos).map(
                                                ([v, n]) => (
                                                    <option key={v} value={v}>
                                                        {n}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Origen → Destino
                                        <input
                                            value={f.partida}
                                            onChange={(e) =>
                                                setF({
                                                    ...f,
                                                    partida: e.target.value,
                                                })
                                            }
                                            onKeyDown={alEnter}
                                            placeholder="Partida..."
                                            className="mt-1 block w-40 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                        />
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Importe
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Estado
                                        <select
                                            value={f.estado}
                                            onChange={(e) =>
                                                elegir('estado', e.target.value)
                                            }
                                            className="mt-1 block w-28 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                        >
                                            <option value="">Todos</option>
                                            <option value="pendiente">
                                                Pendiente
                                            </option>
                                            <option value="autorizada">
                                                Autorizada
                                            </option>
                                            <option value="rechazada">
                                                Rechazada
                                            </option>
                                        </select>
                                    </TableHead>
                                    <TableHead>Solicitó / Resolvió</TableHead>
                                    <TableHead className="p-2">
                                        <div className="flex gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-6 px-1"
                                                onClick={() => aplicar()}
                                            >
                                                <Search className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-6 px-1"
                                                onClick={limpiar}
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {modificaciones.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay modificaciones
                                            presupuestales.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {modificaciones.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="font-medium">
                                            {m.folio_completo}
                                            <div className="text-xs text-muted-foreground">
                                                {fechaLocal(
                                                    m.created_at,
                                                    false,
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {tipos[m.tipo] ?? m.tipo}
                                        </TableCell>
                                        <TableCell className="max-w-md text-xs">
                                            {m.origen_descripcion && (
                                                <div
                                                    className="truncate"
                                                    title={m.origen_descripcion}
                                                >
                                                    <span className="text-destructive">
                                                        −
                                                    </span>{' '}
                                                    {m.origen_descripcion}
                                                </div>
                                            )}
                                            {m.destino_descripcion && (
                                                <div
                                                    className="truncate"
                                                    title={
                                                        m.destino_descripcion
                                                    }
                                                >
                                                    <span className="text-green-700">
                                                        +
                                                    </span>{' '}
                                                    {m.destino_descripcion}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {money(m.importe)}
                                        </TableCell>
                                        <TableCell>
                                            <EstadoModificacionBadge
                                                estado={m.estado}
                                            />
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {m.solicitante?.name ?? '—'}
                                            {m.autorizador && (
                                                <div>{m.autorizador.name}</div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/modificaciones-presupuestales/${m.id}`}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/modificaciones-presupuestales/${m.id}/pdf`}
                                                        target="_blank"
                                                    >
                                                        <FileText className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {modificaciones.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap gap-1">
                                {modificaciones.links.map((l, i) => (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={
                                            l.active ? 'default' : 'outline'
                                        }
                                        disabled={!l.url}
                                        onClick={() =>
                                            l.url &&
                                            router.get(
                                                l.url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: l.label,
                                            }}
                                        />
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Index.layout = { breadcrumbs };
