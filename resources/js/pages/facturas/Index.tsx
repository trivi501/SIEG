import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Eye, Search, X } from 'lucide-react';
import { useState } from 'react';
import DesplazableConMouse from '@/components/desplazable-con-mouse';
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
import { fechaLocal, money } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

interface Factura {
    id: number;
    uuid: string;
    cancelada: boolean;
    emisor: string | null;
    rfc: string | null;
    folio: string | null;
    fecha: string | null;
    cargada: string | null;
    total: number;
    isr: number;
    sat_estado: string | null;
    sat_estatus_cancelacion: string | null;
    sat_efos: boolean | null;
    sat_verificado_at: string | null;
    requisicion_id: number;
    requisicion: string | null;
    requisicion_estado: string | null;
    unidad: string | null;
    orden: string;
    orden_cancelada: boolean;
    clc: string | null;
    clc_estado: string | null;
    clc_clave: string | null;
}

interface Filtros {
    buscar?: string;
    sat?: string;
    vigencia?: string;
    desde?: string;
    hasta?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Facturas', href: '/facturas' },
];

const COLOR_SAT: Record<string, string> = {
    Vigente: 'bg-green-100 text-green-800',
    Cancelado: 'bg-red-100 text-red-800',
    'No Encontrado': 'bg-amber-100 text-amber-900',
};

export default function Index({
    facturas,
    filtros,
    totales,
}: {
    facturas: {
        data: Factura[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filtros: Filtros;
    totales: { facturas: number; total: number };
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const veRequisiciones = permisos.includes('requisiciones-index');
    const [f, setF] = useState<Filtros>({
        buscar: filtros.buscar ?? '',
        sat: filtros.sat ?? '',
        vigencia: filtros.vigencia ?? '',
        desde: filtros.desde ?? '',
        hasta: filtros.hasta ?? '',
    });

    const aplicar = (cambios: Filtros = {}) => {
        const datos = Object.fromEntries(
            Object.entries({ ...f, ...cambios }).filter(([, v]) => v),
        );
        router.get('/facturas', datos, {
            preserveState: true,
            preserveScroll: true,
        });
    };
    const elegir = (campo: keyof Filtros, valor: string) => {
        setF({ ...f, [campo]: valor });
        aplicar({ [campo]: valor });
    };
    const limpiar = () => {
        const vacio = {
            buscar: '',
            sat: '',
            vigencia: '',
            desde: '',
            hasta: '',
        };
        setF(vacio);
        router.get('/facturas', {}, { preserveScroll: true });
    };

    const claseFiltro =
        'rounded-md border border-input bg-background px-2 py-1.5 text-sm';

    return (
        <>
            <Head title="Facturas" />
            <div className="space-y-4 p-6">
                <div>
                    <h3 className="text-lg font-medium">Facturas</h3>
                    <p className="text-sm text-muted-foreground">
                        Facturas (XML) cargadas a las órdenes de compra: emisor,
                        monto, folio, fecha, su validación en el SAT y en qué va
                        el trámite (requisición, orden de compra y CLC).
                    </p>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            {totales.facturas} factura
                            {totales.facturas === 1 ? '' : 's'} ·{' '}
                            {money(totales.total)}
                        </CardTitle>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                aplicar();
                            }}
                            className="flex flex-wrap items-end gap-2 pt-2"
                        >
                            <label className="grid gap-1 text-xs">
                                Buscar
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        type="search"
                                        value={f.buscar}
                                        onChange={(e) =>
                                            setF({
                                                ...f,
                                                buscar: e.target.value,
                                            })
                                        }
                                        placeholder="Emisor, RFC, folio, UUID, requisición, O.C., CLC…"
                                        className={`${claseFiltro} w-80 pl-7`}
                                    />
                                </div>
                            </label>
                            <label className="grid gap-1 text-xs">
                                SAT
                                <select
                                    value={f.sat}
                                    onChange={(e) =>
                                        elegir('sat', e.target.value)
                                    }
                                    className={claseFiltro}
                                >
                                    <option value="">Todas</option>
                                    <option value="Vigente">Vigente</option>
                                    <option value="Cancelado">Cancelada</option>
                                    <option value="No Encontrado">
                                        No encontrada
                                    </option>
                                    <option value="sin_verificar">
                                        Sin verificar
                                    </option>
                                </select>
                            </label>
                            <label className="grid gap-1 text-xs">
                                Factura
                                <select
                                    value={f.vigencia}
                                    onChange={(e) =>
                                        elegir('vigencia', e.target.value)
                                    }
                                    className={claseFiltro}
                                >
                                    <option value="">Todas</option>
                                    <option value="vigentes">Aceptadas</option>
                                    <option value="canceladas">
                                        Canceladas (con su O.C.)
                                    </option>
                                </select>
                            </label>
                            <label className="grid gap-1 text-xs">
                                Fecha desde
                                <input
                                    type="date"
                                    value={f.desde}
                                    onChange={(e) =>
                                        elegir('desde', e.target.value)
                                    }
                                    className={claseFiltro}
                                />
                            </label>
                            <label className="grid gap-1 text-xs">
                                hasta
                                <input
                                    type="date"
                                    value={f.hasta}
                                    onChange={(e) =>
                                        elegir('hasta', e.target.value)
                                    }
                                    className={claseFiltro}
                                />
                            </label>
                            <Button type="submit" size="sm">
                                <Search className="mr-1 h-4 w-4" /> Buscar
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={limpiar}
                            >
                                <X className="mr-1 h-4 w-4" /> Limpiar
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent>
                        <DesplazableConMouse>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Emisor</TableHead>
                                        <TableHead>Folio / UUID</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead className="text-right">
                                            Monto
                                        </TableHead>
                                        <TableHead>SAT</TableHead>
                                        <TableHead>Requisición (FUR)</TableHead>
                                        <TableHead>Orden de compra</TableHead>
                                        <TableHead>CLC</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {facturas.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={9}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                No hay facturas con estos
                                                filtros.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {facturas.data.map((x) => (
                                        <TableRow
                                            key={x.id}
                                            className={
                                                x.cancelada ? 'opacity-70' : ''
                                            }
                                        >
                                            <TableCell className="max-w-64 text-xs whitespace-normal">
                                                <div className="font-medium">
                                                    {x.emisor ?? '—'}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {x.rfc}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <div className="font-medium">
                                                    {x.folio ?? 'Sin folio'}
                                                    {x.cancelada && (
                                                        <Badge className="ml-1 bg-red-100 text-red-800">
                                                            Cancelada
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="font-mono text-[11px] text-muted-foreground">
                                                    {x.uuid}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap">
                                                {fechaLocal(x.fecha, false)}
                                                <div className="text-muted-foreground">
                                                    Cargada{' '}
                                                    {fechaLocal(
                                                        x.cargada,
                                                        false,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {money(x.total)}
                                                {x.isr > 0 && (
                                                    <div className="text-xs text-muted-foreground">
                                                        ISR ret. {money(x.isr)}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {x.sat_estado ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className={
                                                            COLOR_SAT[
                                                                x.sat_estado
                                                            ] ?? ''
                                                        }
                                                        title={
                                                            x.sat_estatus_cancelacion ??
                                                            undefined
                                                        }
                                                    >
                                                        {x.sat_estado ===
                                                        'Vigente'
                                                            ? 'Validada'
                                                            : x.sat_estado}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Sin validar
                                                    </Badge>
                                                )}
                                                {x.sat_efos && (
                                                    <Badge className="ml-1 bg-red-100 text-red-800">
                                                        EFOS
                                                    </Badge>
                                                )}
                                                {x.sat_verificado_at && (
                                                    <div className="mt-0.5 text-muted-foreground">
                                                        {fechaLocal(
                                                            x.sat_verificado_at,
                                                            false,
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <div className="font-medium">
                                                    {x.requisicion ?? '—'}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {x.requisicion_estado}
                                                </div>
                                                {x.unidad && (
                                                    <div className="max-w-48 truncate text-muted-foreground">
                                                        {x.unidad}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <div className="font-medium">
                                                    {x.orden}
                                                </div>
                                                <div
                                                    className={
                                                        x.orden_cancelada
                                                            ? 'text-red-700'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    {x.orden_cancelada
                                                        ? 'Cancelada'
                                                        : 'Vigente'}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {x.clc ? (
                                                    <>
                                                        <div className="font-medium">
                                                            {x.clc}
                                                        </div>
                                                        <div
                                                            className={
                                                                x.clc_clave ===
                                                                'cancelada'
                                                                    ? 'text-red-700'
                                                                    : x.clc_clave ===
                                                                        'pagada'
                                                                      ? 'text-green-700'
                                                                      : 'text-muted-foreground'
                                                            }
                                                        >
                                                            {x.clc_estado}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Sin CLC
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    {veRequisiciones && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/requisiciones/${x.requisicion_id}`}
                                                                    title="Ver requisición"
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
                                                                    href={`/ordenes-compra/${x.id}/xml`}
                                                                    title="Descargar XML"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </DesplazableConMouse>
                        {facturas.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap items-center gap-1">
                                <span className="mr-2 text-xs text-muted-foreground">
                                    {facturas.from}–{facturas.to} de{' '}
                                    {facturas.total}
                                </span>
                                {facturas.links.map((l, i) => (
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
