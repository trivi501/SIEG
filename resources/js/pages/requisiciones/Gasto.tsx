import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    FileSpreadsheet,
} from 'lucide-react';
import { useState } from 'react';
import DesplazableConMouse from '@/components/desplazable-con-mouse';
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
import { fechaLocal, money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const TITULO = 'Ejercicio y control del gasto';

const POR_PAGINA = 20;

const breadcrumbs: BreadcrumbItem[] = [
    { title: TITULO, href: '/requisiciones/gasto' },
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
    | 'devengado'
    | 'pagado'
    | 'disponible'
    | 'saldo_corrido'
    | 'disponible_mes';

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
        ayuda: 'Requisiciones con suficiencia que aún no tienen factura',
    },
    {
        key: 'en_tramite',
        titulo: 'En trámite',
        ayuda: 'Requisiciones cotizadas que aún no tienen suficiencia',
    },
    {
        key: 'devengado',
        titulo: 'Devengado',
        ayuda: 'Órdenes de compra con factura (XML) cargada, aún sin pago',
    },
    {
        key: 'pagado',
        titulo: 'Pagado',
        ayuda: 'Órdenes de compra con pago registrado',
    },
    {
        key: 'disponible',
        titulo: 'Disponible',
        ayuda: 'Vigente − comprometido − devengado − pagado',
    },
    {
        key: 'saldo_corrido',
        titulo: 'Saldo corrido',
        ayuda: 'Lo que quedó disponible en los meses anteriores y se sumó a este mes',
    },
    {
        key: 'disponible_mes',
        titulo: 'Disponible del mes',
        ayuda: 'Saldo corrido + asignado del mes ± modificaciones del mes − comprometido en el mes',
    },
];

interface Saldos {
    mes: string;
    mesAnterior: string | null;
    corrida: {
        fecha: string;
        origen: 'automatica' | 'manual';
        usuario: string | null;
        total: number;
    } | null;
    puedeCorrer: boolean;
}

export default function Gasto({
    filas,
    unidadesDisponibles,
    unidadSeleccionada,
    conMovimiento,
    saldos,
}: {
    filas: Required<LineaPresupuesto>[];
    unidadesDisponibles: UnidadAdministrativa[];
    unidadSeleccionada?: string | null;
    conMovimiento?: boolean;
    saldos: Saldos;
}) {
    const [buscar, setBuscar] = useState('');
    const [confirmarCorrida, setConfirmarCorrida] = useState(false);
    const [corriendo, setCorriendo] = useState(false);

    const correrSaldos = () =>
        router.post(
            '/presupuesto/correr-saldos',
            {},
            {
                preserveScroll: true,
                onStart: () => setCorriendo(true),
                onFinish: () => {
                    setCorriendo(false);
                    setConfirmarCorrida(false);
                },
            },
        );

    const terminos = buscar.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const visibles = terminos.length
        ? filas.filter((f) => {
              const texto =
                  `${f.unidad_clave} ${f.unidad} ${f.fuente} ${f.nombre_fuente} ${f.proyecto} ${f.nombre_proyecto} ${f.partida} ${f.nombre_partida}`.toLowerCase();

              return terminos.every((t) => texto.includes(t));
          })
        : filas;

    // 20 renglones por página; la búsqueda y los totales siguen considerando todas las líneas.
    const [pagina, setPagina] = useState(1);
    const totalPaginas = Math.max(1, Math.ceil(visibles.length / POR_PAGINA));
    const paginaActual = Math.min(pagina, totalPaginas);
    const enPagina = visibles.slice(
        (paginaActual - 1) * POR_PAGINA,
        paginaActual * POR_PAGINA,
    );

    const totales = columnas.reduce(
        (acc, c) => ({
            ...acc,
            [c.key]: visibles.reduce((s, f) => s + Number(f[c.key] ?? 0), 0),
        }),
        {} as Record<Columna, number>,
    );

    // El Excel sale con los mismos filtros que la pantalla (unidad, solo con movimiento y búsqueda).
    const urlExcel = `/requisiciones/gasto/exportar?${new URLSearchParams({
        ...(unidadSeleccionada ? { unidad: unidadSeleccionada } : {}),
        ...(conMovimiento ? { con_movimiento: '1' } : {}),
        ...(buscar.trim() ? { buscar: buscar.trim() } : {}),
    }).toString()}`;

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
        setPagina(1);
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
            <Head title={TITULO} />
            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/requisiciones">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">{TITULO}</h3>
                    {visibles.length > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="ml-auto"
                            asChild
                        >
                            <a href={urlExcel}>
                                <FileSpreadsheet className="mr-2 h-4 w-4" />{' '}
                                Exportar a Excel
                            </a>
                        </Button>
                    )}
                </div>

                <div
                    className={`mb-4 flex flex-wrap items-center gap-3 rounded-md border p-3 text-sm ${
                        saldos.corrida
                            ? 'border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-200'
                            : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200'
                    }`}
                >
                    <CalendarClock className="h-4 w-4 shrink-0" />
                    {saldos.corrida ? (
                        <span>
                            Saldos de <strong>{saldos.mes}</strong> corridos el{' '}
                            {fechaLocal(saldos.corrida.fecha)} (
                            {saldos.corrida.origen === 'automatica'
                                ? 'automático'
                                : `manual, ${saldos.corrida.usuario ?? 'sistema'}`}
                            ): se sumaron {money(saldos.corrida.total)}{' '}
                            {saldos.mesAnterior
                                ? `disponibles de ${saldos.mesAnterior}`
                                : 'al inicio del ejercicio'}
                            .
                        </span>
                    ) : (
                        <span>
                            Los saldos de <strong>{saldos.mes}</strong> aún no
                            se han corrido. Se corren solos el día 1 de cada
                            mes; mientras tanto, el saldo corrido se calcula con
                            los movimientos.
                        </span>
                    )}
                    {saldos.puedeCorrer && (
                        <Button
                            size="sm"
                            className="ml-auto"
                            onClick={() => setConfirmarCorrida(true)}
                        >
                            Correr saldos
                        </Button>
                    )}
                </div>

                <Dialog
                    open={confirmarCorrida}
                    onOpenChange={setConfirmarCorrida}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                ¿Correr los saldos de {saldos.mes}?
                            </DialogTitle>
                            <DialogDescription>
                                Lo que quedó disponible en{' '}
                                {saldos.mesAnterior ?? 'los meses anteriores'}{' '}
                                en cada línea se sumará a {saldos.mes} y quedará
                                fijo. Solo se corre una vez por mes y no
                                modifica el calendario autorizado.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setConfirmarCorrida(false)}
                            >
                                Cancelar
                            </Button>
                            <Button disabled={corriendo} onClick={correrSaldos}>
                                {corriendo ? 'Corriendo...' : 'Correr saldos'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

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
                        onChange={(e) => {
                            setBuscar(e.target.value);
                            setPagina(1);
                        }}
                        placeholder="Buscar clave, unidad, fuente, proyecto o partida..."
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
                        <DesplazableConMouse>
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
                                                No hay presupuesto importado
                                                para esta unidad administrativa.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {enPagina.map((f) => (
                                        <TableRow key={f.clave}>
                                            <TableCell
                                                className="text-xs"
                                                title={f.unidad}
                                            >
                                                {f.unidad_clave}{' '}
                                                <span className="text-muted-foreground">
                                                    {f.unidad || '—'}
                                                </span>
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
                                                    className={`text-right text-xs whitespace-nowrap ${c.key === 'disponible' || c.key === 'disponible_mes' ? 'font-medium' : ''} ${Number(f[c.key]) < 0 ? 'text-destructive' : ''}`}
                                                >
                                                    {money(f[c.key])}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))}
                                    {visibles.length > 0 && (
                                        <TableRow className="font-semibold">
                                            <TableCell colSpan={4}>
                                                Total
                                                {totalPaginas > 1 && (
                                                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                        (todas las páginas)
                                                    </span>
                                                )}
                                            </TableCell>
                                            {columnas.map((c) => (
                                                <TableCell
                                                    key={c.key}
                                                    className={`text-right text-xs whitespace-nowrap ${totales[c.key] < 0 ? 'text-destructive' : ''}`}
                                                >
                                                    {money(totales[c.key])}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </DesplazableConMouse>
                        {totalPaginas > 1 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    Mostrando{' '}
                                    {(paginaActual - 1) * POR_PAGINA + 1}–
                                    {Math.min(
                                        paginaActual * POR_PAGINA,
                                        visibles.length,
                                    )}{' '}
                                    de {visibles.length.toLocaleString('en-US')}{' '}
                                    partidas
                                </span>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={paginaActual === 1}
                                        onClick={() => setPagina(1)}
                                        title="Primera página"
                                    >
                                        <ChevronsLeft className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={paginaActual === 1}
                                        onClick={() =>
                                            setPagina(paginaActual - 1)
                                        }
                                        title="Anterior"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span className="px-2">
                                        Página {paginaActual} de {totalPaginas}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={paginaActual === totalPaginas}
                                        onClick={() =>
                                            setPagina(paginaActual + 1)
                                        }
                                        title="Siguiente"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={paginaActual === totalPaginas}
                                        onClick={() => setPagina(totalPaginas)}
                                        title="Última página"
                                    >
                                        <ChevronsRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Gasto.layout = { breadcrumbs };
