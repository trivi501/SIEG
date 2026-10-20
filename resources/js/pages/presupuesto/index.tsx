import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, Search, X } from 'lucide-react';
import { useEffect, useState, useCallback } from 'react';
import DesplazableConMouse from '@/components/desplazable-con-mouse';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { money } from '@/lib/presupuesto';
import { presupuesto } from '@/routes';
import { destroy, importMethod } from '@/routes/presupuesto';
import type { PresupuestoEgreso } from '@/types';

type Paginated = {
    data: PresupuestoEgreso[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const monthLabels: Record<string, string> = {
    ENERO: 'Enero', FEBRERO: 'Febrero', MARZO: 'Marzo', ABRIL: 'Abril',
    MAYO: 'Mayo', JUNIO: 'Junio', JULIO: 'Julio', AGOSTO: 'Agosto',
    SEP: 'Septiembre', OCTUBRE: 'Octubre', NOV: 'Noviembre', DIC: 'Diciembre',
};

function DetailDialog({ p }: { p: PresupuestoEgreso }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Eye className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent className="max-w-6xl sm:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>Detalle del Registro</DialogTitle>
                </DialogHeader>
                <div className="grid grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    <div><span className="font-semibold">Clave:</span> {p.CLAVE ?? '—'}</div>
                    <div><span className="font-semibold">Clave 3:</span> {p.CLAVE3 ?? '—'}</div>
                    <div><span className="font-semibold">Clave 6:</span> {p.CLAVE6 ?? '—'}</div>
                    <div className="col-span-3"><span className="font-semibold">Unidad Administrativa:</span> {p.UNIDAD_ADMINISTRATIVA ?? '—'}</div>
                    <div className="col-span-3"><span className="font-semibold">Fuente de Financiamiento:</span> {p.FUENTE_DE_FINANCIAMIENTO ?? '—'}</div>
                    <div className="col-span-3"><span className="font-semibold">Proyecto:</span> {p.PROYECTO ?? '—'}</div>
                    <div><span className="font-semibold">Partida:</span> {p.PARTIDA ?? '—'}</div>
                    <div><span className="font-semibold">Capítulo:</span> {p.capitulo ?? '—'}</div>
                    <div><span className="font-semibold">Tipo de Gasto:</span> {p.TIPO_DE_GASTO ?? '—'}</div>
                    <div className="col-span-3"><span className="font-semibold">Nombre Partida:</span> {p.NOMBRE_PARTIDA ?? '—'}</div>
                    <div className="col-span-3 rounded-md border bg-muted/50 p-3 text-base"><span className="font-semibold">Importe Total:</span> {p.IMPORTE_TOTAL == null ? '—' : money(p.IMPORTE_TOTAL)}</div>
                </div>
                <div className="mt-2 border-t pt-3">
                    <h4 className="mb-2 text-sm font-semibold">Distribución Mensual</h4>
                    {!!p.diferencia && (
                        <p className="mb-2 rounded-md border border-amber-300 bg-amber-50 p-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            La distribución no cuadra con el importe total:{' '}
                            {p.diferencia > 0 ? `faltan ${money(p.diferencia)}` : `sobran ${money(-p.diferencia)}`}. Edita la línea para corregirla.
                        </p>
                    )}
                    <div className="grid grid-cols-3 gap-2 text-sm">
                        {Object.entries(monthLabels).map(([key, label]) => (
                            <div key={key}>
                                <span className="font-medium">{label}:</span>{' '}
                                {((p as any)[key] as number | null) == null ? '—' : money((p as any)[key])}
                            </div>
                        ))}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

const searchableColumns = [
    { key: 'CLAVE', label: 'Clave' },
    { key: 'UNIDAD_ADMINISTRATIVA', label: 'Unidad Admin.' },
    { key: 'CLAVE6', label: 'Clave proy.' },
    { key: 'PROYECTO', label: 'Proyecto' },
    { key: 'PARTIDA', label: 'Partida' },
    { key: 'capitulo', label: 'Capítulo' },
    { key: 'NOMBRE_PARTIDA', label: 'Nombre Partida' },
    { key: 'TIPO_DE_GASTO', label: 'Tipo Gasto' },
];

export default function Presupuesto({
    presupuestos,
    filters,
    totalDescuadradas,
    mesActual,
}: {
    presupuestos: Paginated;
    filters: Record<string, string>;
    totalDescuadradas: number;
    mesActual: string;
}) {
    const { props } = usePage<{ flash?: { success?: string; error?: string }; userPermissions?: string[] }>();
    const flash = props.flash;
    // Las áreas solo consultan: sin permisos de alta, edición, importación ni borrado no ven esos botones.
    const permisos = props.userPermissions ?? [];
    const puedeCrear = permisos.includes('presupuesto-create');
    const puedeEditar = permisos.includes('presupuesto-edit');
    const puedeImportar = permisos.includes('presupuesto-import');
    const puedeBorrar = permisos.includes('presupuesto-delete');
    const [search, setSearch] = useState<Record<string, string>>(filters ?? {});

    const submitSearch = useCallback(() => {
        const params = new URLSearchParams();
        params.set('page', '1');

        for (const [k, v] of Object.entries(search)) {
            if (v) {
params.set(k, v);
}
        }

        router.get(presupuesto.url(), Object.fromEntries(params), { preserveScroll: true, preserveState: true });
    }, [search]);

    useEffect(() => {
        const timer = setTimeout(submitSearch, 400);

        return () => clearTimeout(timer);
    }, [submitSearch]);

    const clearSearch = () => {
        const empty: Record<string, string> = {};

        for (const k of Object.keys(search)) {
empty[k] = '';
}

        setSearch(empty);
        router.get(presupuesto.url(), { page: '1' }, { preserveScroll: true });
    };

    const hasFilters = Object.values(search).some((v) => v);

    return ( 
        <>
            <Head title="Presupuesto" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {flash?.success && (
                    <div className="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400">
                        {flash.error}
                    </div>
                )}
                {puedeImportar && (
                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-4 text-xl font-semibold">Importar Presupuesto de Egresos {new Date().getFullYear()}</h2>
                    <Form action={importMethod()} className="flex items-end gap-4" encType="multipart/form-data">
                        {({ processing, errors }) => (
                            <>
                                <div className="flex-1">
                                    <Input type="file" name="file" accept=".xlsx,.xls,.csv" />
                                    {errors.file && <p className="mt-1 text-sm text-red-500">{errors.file}</p>}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Importando...' : 'Importar'}
                                </Button>
                                {processing && (
                                    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/60">
                                        <div className="rounded-xl bg-white p-8 text-center shadow-lg dark:bg-gray-900">
                                            <div className="mx-auto mb-4 h-10 w-10 animate-spin rounded-full border-4 border-gray-300 border-t-blue-600" />
                                            <p className="text-lg font-semibold">Importando presupuesto...</p>
                                            <p className="mt-1 text-sm text-muted-foreground">Esto puede tomar unos segundos. No cierres la página.</p>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </Form>
                </div>
                )}

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto p-4">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold">Registros ({presupuestos.total})</h3>
                            <div className="flex items-center gap-2">
                                {puedeEditar && (totalDescuadradas > 0 || search.descuadradas) && (
                                    <label className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        <input
                                            type="checkbox"
                                            checked={!!search.descuadradas}
                                            onChange={(e) => setSearch((s) => ({ ...s, descuadradas: e.target.checked ? '1' : '' }))}
                                        />
                                        Solo distribución que no cuadra ({totalDescuadradas})
                                    </label>
                                )}
                                {puedeCrear && (
                                    <Button size="sm" asChild>
                                        <Link href="/presupuesto/crear">
                                            <Plus className="mr-1 h-4 w-4" /> Nueva partida
                                        </Link>
                                    </Button>
                                )}
                                {hasFilters && (
                                    <Button variant="ghost" size="sm" onClick={clearSearch}>
                                        <X className="mr-1 h-4 w-4" /> Limpiar
                                    </Button>
                                )}
                                {puedeBorrar && presupuestos.total > 0 && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button variant="destructive" size="sm">Limpiar todo</Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>¿Eliminar todo el presupuesto?</DialogTitle>
                                            </DialogHeader>
                                            <p className="text-sm text-red-600 dark:text-red-400">
                                                <strong>Peligro:</strong> esto eliminará permanentemente todos los registros del presupuesto. No podrás deshacer esta acción.
                                            </p>
                                            <div className="flex justify-end gap-2">
                                                <DialogTrigger asChild>
                                                    <Button variant="outline">Cancelar</Button>
                                                </DialogTrigger>
                                                <Form action={destroy()} className="inline">
                                                    {({ processing }) => (
                                                        <Button variant="destructive" type="submit" disabled={processing}>
                                                            {processing ? 'Eliminando...' : 'Sí, eliminar todo'}
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </DialogContent>
                                    </Dialog>
                                )}
                            </div>
                        </div>
                        <DesplazableConMouse>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Clave</TableHead>
                                    <TableHead>Unidad Administrativa</TableHead>
                                    <TableHead>Clave proyecto</TableHead>
                                    <TableHead>Proyecto</TableHead>
                                    <TableHead>Partida</TableHead>
                                    <TableHead>Capítulo</TableHead>
                                    <TableHead>Nombre Partida</TableHead>
                                    <TableHead>Tipo Gasto</TableHead>
                                    <TableHead className="text-right">Aprobado</TableHead>
                                    <TableHead className="text-right">Modificado</TableHead>
                                    <TableHead className="text-right">Vigente anual</TableHead>
                                    <TableHead className="text-right">Vigente a {mesActual}</TableHead>
                                    <TableHead className="text-right">Comprometido</TableHead>
                                    <TableHead className="text-right">Devengado</TableHead>
                                    <TableHead className="text-right">Pagado</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                                <TableRow>
                                    {searchableColumns.map((col) => (
                                        <TableHead key={col.key}>
                                            <div className="relative">
                                                <Search className="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    placeholder={col.label}
                                                    value={search[col.key] ?? ''}
                                                    onChange={(e) => setSearch((s) => ({ ...s, [col.key]: e.target.value }))}
                                                    className="h-8 pl-7 text-xs"
                                                />
                                            </div>
                                        </TableHead>
                                    ))}
                                    <TableHead colSpan={8} />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {presupuestos.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={16} className="py-8 text-center text-muted-foreground">
                                            {hasFilters ? 'Sin resultados para los filtros aplicados.' : 'No hay registros. Importe un archivo Excel.'}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    presupuestos.data.map((p: PresupuestoEgreso) => (
                                        <TableRow key={p.id_presupuesto}>
                                            <TableCell className="font-mono text-xs">{p.CLAVE}</TableCell>
                                            <TableCell className="max-w-[200px] truncate">{p.UNIDAD_ADMINISTRATIVA}</TableCell>
                                            <TableCell className="font-mono text-xs">{p.CLAVE6}</TableCell>
                                            <TableCell className="max-w-[200px] truncate">{p.PROYECTO}</TableCell>
                                            <TableCell>{p.PARTIDA}</TableCell>
                                            <TableCell>{p.capitulo}</TableCell>
                                            <TableCell className="max-w-[250px] truncate">{p.NOMBRE_PARTIDA}</TableCell>
                                            <TableCell>{p.TIPO_DE_GASTO}</TableCell>
                                            <TableCell className="text-right font-mono">
                                                {money(p.IMPORTE_TOTAL)}
                                                {!!p.diferencia && (
                                                    <div
                                                        className="font-sans text-xs text-amber-700 dark:text-amber-400"
                                                        title="La distribución mensual no suma el importe total"
                                                    >
                                                        {p.diferencia > 0 ? 'faltan' : 'sobran'} {money(Math.abs(p.diferencia))}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-mono ${p.modificado ? (p.modificado < 0 ? 'text-destructive' : 'text-green-700') : 'text-muted-foreground'}`}
                                            >
                                                {money(p.modificado ?? 0)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono">{money(p.vigente ?? p.IMPORTE_TOTAL)}</TableCell>
                                            <TableCell className="text-right font-mono font-semibold" title={`Aprobado de enero a ${mesActual} ± modificaciones hasta ${mesActual}`}>
                                                {money(p.vigente_mes ?? 0)}
                                            </TableCell>
                                            {(['comprometido', 'devengado', 'pagado'] as const).map((momento) => (
                                                <TableCell
                                                    key={momento}
                                                    className={`text-right font-mono ${p[momento] ? '' : 'text-muted-foreground'}`}
                                                >
                                                    {money(p[momento])}
                                                </TableCell>
                                            ))}
                                            <TableCell className="flex gap-1">
                                                <DetailDialog p={p} />
                                                {puedeEditar && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/presupuesto/${p.id_presupuesto}/edit`} title="Editar"><Pencil className="h-4 w-4" /></Link>
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                        </DesplazableConMouse>

                        {presupuestos.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Mostrando {presupuestos.from}–{presupuestos.to} de {presupuestos.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    <Button variant="outline" size="sm" disabled={presupuestos.current_page === 1} asChild>
                                        <Link href={presupuestos.links[0]?.url ?? '#'} preserveScroll>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    {presupuestos.links.slice(1, -1).map((link) =>
                                        link.url ? (
                                            <Button key={link.label} variant={link.active ? 'default' : 'outline'} size="sm" asChild>
                                                <Link href={link.url} preserveScroll>
                                                    {link.label}
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button key={link.label} variant="outline" size="sm" disabled>
                                                {link.label}
                                            </Button>
                                        )
                                    )}
                                    <Button variant="outline" size="sm" disabled={presupuestos.current_page === presupuestos.last_page} asChild>
                                        <Link href={presupuestos.links[presupuestos.links.length - 1]?.url ?? '#'} preserveScroll>
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Presupuesto.layout = {
    breadcrumbs: [
        {
            title: 'Presupuesto',
            href: presupuesto(),
        },
    ],
};
