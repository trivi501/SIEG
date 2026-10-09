import { Head, Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import CambiosAuditoria, { AccionBadge } from '@/components/cambios-auditoria';
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
import { fechaLocal } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bitácora de auditoría', href: '/auditoria' },
];

interface Movimiento {
    id: number;
    fecha: string;
    usuario: string | null;
    accion: string;
    tipo: string;
    auditable_type: string;
    registro: string;
    catalogo: string | null;
    etiquetas: Record<string, string>;
    descripcion: string | null;
    lote: string | null;
    antes: Record<string, unknown> | null;
    despues: Record<string, unknown> | null;
    ip: string | null;
}

interface Filtros {
    tipo?: string;
    usuario?: string;
    accion?: string;
    desde?: string;
    hasta?: string;
    lote?: string;
    registro?: string;
}

const control =
    'rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm';

export default function Index({
    registros,
    filtros,
    tipos,
    usuarios,
    acciones,
}: {
    registros: {
        data: Movimiento[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filtros: Filtros;
    tipos: { valor: string; etiqueta: string }[];
    usuarios: { id: number; name: string }[];
    acciones: string[];
}) {
    const [valores, setValores] = useState<Filtros>(filtros ?? {});

    const aplicar = (cambios: Filtros) => {
        const siguientes = { ...valores, ...cambios };
        setValores(siguientes);
        router.get(
            '/auditoria',
            Object.fromEntries(
                Object.entries(siguientes).filter(([, v]) => v),
            ) as Record<string, string>,
            { preserveState: true, replace: true },
        );
    };

    const hayFiltros = Object.values(filtros ?? {}).some(Boolean);

    return (
        <>
            <Head title="Bitácora de auditoría" />
            <div className="space-y-4 p-4 sm:p-6">
                <div>
                    <h3 className="text-lg font-medium">
                        Bitácora de auditoría
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Quién dio de alta, modificó o dio de baja cada registro
                        de los catálogos, con el valor anterior y el nuevo.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 font-normal">
                            <select
                                value={valores.tipo ?? ''}
                                onChange={(e) =>
                                    aplicar({ tipo: e.target.value })
                                }
                                className={control}
                            >
                                <option value="">Todos los catálogos</option>
                                {tipos.map((t) => (
                                    <option key={t.valor} value={t.valor}>
                                        {t.etiqueta}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={valores.usuario ?? ''}
                                onChange={(e) =>
                                    aplicar({ usuario: e.target.value })
                                }
                                className={control}
                            >
                                <option value="">Todos los usuarios</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={valores.accion ?? ''}
                                onChange={(e) =>
                                    aplicar({ accion: e.target.value })
                                }
                                className={control}
                            >
                                <option value="">Todas las acciones</option>
                                {acciones.map((a) => (
                                    <option key={a} value={a}>
                                        {a}
                                    </option>
                                ))}
                            </select>
                            <label className="flex items-center gap-1 text-sm">
                                Desde
                                <input
                                    type="date"
                                    value={valores.desde ?? ''}
                                    onChange={(e) =>
                                        aplicar({ desde: e.target.value })
                                    }
                                    className={control}
                                />
                            </label>
                            <label className="flex items-center gap-1 text-sm">
                                Hasta
                                <input
                                    type="date"
                                    value={valores.hasta ?? ''}
                                    onChange={(e) =>
                                        aplicar({ hasta: e.target.value })
                                    }
                                    className={control}
                                />
                            </label>
                            {hayFiltros && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setValores({});
                                        router.get(
                                            '/auditoria',
                                            {},
                                            { replace: true },
                                        );
                                    }}
                                >
                                    <X className="mr-1 h-4 w-4" /> Quitar
                                    filtros
                                </Button>
                            )}
                            <span className="ml-auto text-sm text-muted-foreground">
                                {registros.total} movimientos
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Catálogo</TableHead>
                                    <TableHead>Cambios</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {registros.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay movimientos.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {registros.data.map((m) => (
                                    <TableRow key={m.id} className="align-top">
                                        <TableCell className="whitespace-nowrap">
                                            {fechaLocal(m.fecha)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {m.usuario ?? 'Sistema'}
                                            {m.ip && (
                                                <div className="text-xs text-muted-foreground">
                                                    {m.ip}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <AccionBadge accion={m.accion} />
                                        </TableCell>
                                        <TableCell className="min-w-40">
                                            {m.catalogo ? (
                                                <Link
                                                    href={`/catalogos/${m.catalogo}`}
                                                    className="underline-offset-2 hover:underline"
                                                >
                                                    {m.tipo}
                                                </Link>
                                            ) : (
                                                m.tipo
                                            )}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    aplicar({
                                                        tipo: m.auditable_type,
                                                        registro: m.registro,
                                                    })
                                                }
                                                className="block text-xs text-muted-foreground hover:underline"
                                                title="Ver todos los movimientos de este registro"
                                            >
                                                Registro #{m.registro}
                                            </button>
                                            {m.lote && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        aplicar({
                                                            lote: m.lote ?? '',
                                                        })
                                                    }
                                                    className="block text-left text-xs text-muted-foreground hover:underline"
                                                    title="Ver todo el lote"
                                                >
                                                    {m.descripcion ?? 'Lote'}
                                                </button>
                                            )}
                                        </TableCell>
                                        <TableCell className="min-w-64">
                                            <CambiosAuditoria
                                                antes={m.antes}
                                                despues={m.despues}
                                                etiquetas={m.etiquetas}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {registros.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap gap-1">
                                {registros.links.map((l, i) => (
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
