import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
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
import { money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
    { title: 'Cómo va el gasto', href: '#' },
];

interface UnidadAdministrativa {
    id_cat_egreso_unidad_administrativa: number;
    nombre: string;
}

type Columna =
    | 'asignado'
    | 'modificado'
    | 'vigente'
    | 'comprometido'
    | 'en_tramite'
    | 'disponible';

const columnas: { key: Columna; titulo: string; ayuda: string }[] = [
    { key: 'asignado', titulo: 'Asignado', ayuda: 'Presupuesto importado' },
    {
        key: 'modificado',
        titulo: 'Modificado',
        ayuda: 'Traspasos, ampliaciones y reducciones autorizadas',
    },
    { key: 'vigente', titulo: 'Vigente', ayuda: 'Asignado ± modificado' },
    {
        key: 'comprometido',
        titulo: 'Comprometido',
        ayuda: 'Requisiciones con suficiencia',
    },
    {
        key: 'en_tramite',
        titulo: 'En trámite',
        ayuda: 'Requisiciones cotizadas que aún no tienen suficiencia',
    },
    {
        key: 'disponible',
        titulo: 'Disponible',
        ayuda: 'Vigente − comprometido',
    },
];

export default function Gasto({
    filas,
    unidadesDisponibles,
    unidadSeleccionada,
    conMovimiento,
}: {
    filas: Required<LineaPresupuesto>[];
    unidadesDisponibles: UnidadAdministrativa[];
    unidadSeleccionada?: string | null;
    conMovimiento?: boolean;
}) {
    const [buscar, setBuscar] = useState('');

    const terminos = buscar.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const visibles = terminos.length
        ? filas.filter((f) => {
              const texto =
                  `${f.unidad} ${f.fuente} ${f.nombre_fuente} ${f.proyecto} ${f.nombre_proyecto} ${f.partida} ${f.nombre_partida}`.toLowerCase();

              return terminos.every((t) => texto.includes(t));
          })
        : filas;

    const totales = columnas.reduce(
        (acc, c) => ({
            ...acc,
            [c.key]: visibles.reduce((s, f) => s + Number(f[c.key] ?? 0), 0),
        }),
        {} as Record<Columna, number>,
    );

    const recargar = (params: {
        unidad?: string | null;
        con_movimiento?: boolean;
    }) => {
        const unidad =
            params.unidad !== undefined ? params.unidad : unidadSeleccionada;
        const movimiento =
            params.con_movimiento !== undefined
                ? params.con_movimiento
                : conMovimiento;
        router.get(
            '/requisiciones/gasto',
            {
                ...(unidad ? { unidad } : {}),
                ...(movimiento ? { con_movimiento: 1 } : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Cómo va el gasto" />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/requisiciones">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">Cómo va el gasto</h3>
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-4">
                    {unidadesDisponibles.length > 1 && (
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">
                                Unidad administrativa:
                            </span>
                            <select
                                value={unidadSeleccionada ?? ''}
                                onChange={(e) =>
                                    recargar({ unidad: e.target.value || null })
                                }
                                className="rounded-md border-input px-2 py-1 text-sm shadow-sm"
                            >
                                <option value="">Todas las visibles</option>
                                {unidadesDisponibles.map((u) => (
                                    <option
                                        key={
                                            u.id_cat_egreso_unidad_administrativa
                                        }
                                        value={
                                            u.id_cat_egreso_unidad_administrativa
                                        }
                                    >
                                        {u.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={!!conMovimiento}
                            onChange={(e) =>
                                recargar({ con_movimiento: e.target.checked })
                            }
                            className="rounded border-input"
                        />
                        Solo partidas con movimiento
                    </label>
                    <input
                        value={buscar}
                        onChange={(e) => setBuscar(e.target.value)}
                        placeholder="Buscar fuente, proyecto o partida..."
                        className="w-72 rounded-md border-input px-2 py-1 text-sm shadow-sm"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Presupuesto por partida ({visibles.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Unidad</TableHead>
                                    <TableHead>Fuente</TableHead>
                                    <TableHead>Proyecto</TableHead>
                                    <TableHead>Partida</TableHead>
                                    {columnas.map((c) => (
                                        <TableHead
                                            key={c.key}
                                            className="text-right"
                                            title={c.ayuda}
                                        >
                                            {c.titulo}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibles.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4 + columnas.length}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay presupuesto importado para
                                            esta unidad administrativa.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {visibles.map((f) => (
                                    <TableRow key={f.clave}>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {f.unidad || '—'}
                                        </TableCell>
                                        <TableCell
                                            className="text-xs"
                                            title={f.nombre_fuente}
                                        >
                                            {f.fuente}{' '}
                                            <span className="text-muted-foreground">
                                                {f.nombre_fuente}
                                            </span>
                                        </TableCell>
                                        <TableCell
                                            className="text-xs"
                                            title={f.nombre_proyecto}
                                        >
                                            {f.proyecto}{' '}
                                            <span className="text-muted-foreground">
                                                {f.nombre_proyecto}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {f.partida}{' '}
                                            <span className="text-muted-foreground">
                                                {f.nombre_partida}
                                            </span>
                                        </TableCell>
                                        {columnas.map((c) => (
                                            <TableCell
                                                key={c.key}
                                                className={`text-right text-xs ${c.key === 'disponible' ? 'font-medium' : ''} ${Number(f[c.key]) < 0 ? 'text-destructive' : ''}`}
                                            >
                                                {money(f[c.key])}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                                {visibles.length > 0 && (
                                    <TableRow className="font-semibold">
                                        <TableCell colSpan={4}>Total</TableCell>
                                        {columnas.map((c) => (
                                            <TableCell
                                                key={c.key}
                                                className={`text-right text-xs ${totales[c.key] < 0 ? 'text-destructive' : ''}`}
                                            >
                                                {money(totales[c.key])}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Gasto.layout = { breadcrumbs };
