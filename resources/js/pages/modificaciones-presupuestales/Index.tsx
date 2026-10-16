import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    Check,
    Eye,
    FileText,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import AvisoCapturaModificaciones from '@/components/aviso-captura-modificaciones';
import type { CapturaModificaciones } from '@/components/aviso-captura-modificaciones';
import EstadoModificacionBadge from '@/components/estado-modificacion-badge';
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
    origen_mes: number | null;
    origen_disponible: number | null;
    origen_disponible_mes: number | null;
    destino_disponible: number | null;
    destino_disponible_mes: number | null;
    destino_descripcion: string | null;
    destino_mes: number | null;
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

type Accion = 'autorizar' | 'rechazar';

/**
 * Lo que le falta al emisor para cubrir el importe en el periodo elegido (0 = tiene suficiencia).
 * Sin periodo (modificaciones anteriores) se compara contra el disponible anual. El servidor
 * vuelve a revisar ambos al aceptar.
 */
function faltanteEmisor(m: Modificacion): number {
    if (!m.origen_descripcion) {
        return 0;
    }

    const importe = Number(m.importe);
    const disponibles = [
        m.origen_disponible,
        m.origen_mes ? m.origen_disponible_mes : null,
    ].filter((d): d is number => d !== null);
    const minimo = disponibles.length ? Math.min(...disponibles) : 0;

    return Math.max(0, Math.round((importe - minimo) * 100) / 100);
}

/** Una partida del movimiento con su disponible actual y cómo queda si se acepta. */
function LadoMovimiento({
    titulo,
    signo,
    descripcion,
    mes,
    disponible,
    disponibleMes,
    importe,
    meses,
    mostrarDespues,
}: {
    titulo: string;
    signo: 1 | -1;
    descripcion: string;
    mes: number | null;
    disponible: number | null;
    disponibleMes: number | null;
    importe: number;
    meses: Record<string, string>;
    mostrarDespues: boolean;
}) {
    const color =
        signo < 0
            ? 'border-red-200 bg-red-50/60 dark:border-red-900 dark:bg-red-950/30'
            : 'border-green-200 bg-green-50/60 dark:border-green-900 dark:bg-green-950/30';
    const fila = (etiqueta: string, antes: number) => {
        const despues = antes + signo * importe;

        return (
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-muted-foreground">{etiqueta}</span>
                <span className="font-mono">
                    {money(antes)}
                    {mostrarDespues && (
                        <>
                            <span className="mx-1 text-muted-foreground">
                                →
                            </span>
                            <span
                                className={`font-semibold ${despues < 0 ? 'text-destructive' : ''}`}
                            >
                                {money(despues)}
                            </span>
                        </>
                    )}
                </span>
            </div>
        );
    };

    return (
        <div className={`rounded-lg border p-3 text-sm ${color}`}>
            <div className="mb-1 flex items-center justify-between gap-2">
                <span className="text-xs font-semibold tracking-wide uppercase">
                    {titulo}
                </span>
                {mes && (
                    <span className="rounded bg-background px-1.5 py-0.5 text-[10px] font-medium uppercase">
                        {meses[mes]}
                    </span>
                )}
            </div>
            <p className="mb-2 text-xs">{descripcion}</p>
            <div className="space-y-1 text-xs">
                {mes && disponibleMes !== null
                    ? fila(`Disponible de ${meses[mes]}`, disponibleMes)
                    : disponible !== null && fila('Disponible', disponible)}
            </div>
        </div>
    );
}

function ResolverModificacion({
    modificacion: m,
    accion,
    tipos,
    meses,
    onClose,
}: {
    modificacion: Modificacion | null;
    accion: Accion;
    tipos: Record<string, string>;
    meses: Record<string, string>;
    onClose: () => void;
}) {
    const form = useForm({ observaciones: '' });
    const aceptar = accion === 'autorizar';
    const importe = Number(m?.importe ?? 0);
    const faltante = m ? faltanteEmisor(m) : 0;
    const emisorNoAlcanza = aceptar && faltante > 0;

    const cerrar = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/modificaciones-presupuestales/${m?.id}/${accion}`, {
            preserveScroll: true,
            onSuccess: () => cerrar(),
        });
    };

    return (
        <Dialog
            open={m !== null}
            onOpenChange={(abierto) => !abierto && cerrar()}
        >
            <DialogContent className="sm:max-w-2xl">
                {m && (
                    <form onSubmit={enviar} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <span
                                    className={`flex h-8 w-8 items-center justify-center rounded-full ${aceptar ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}
                                >
                                    {aceptar ? (
                                        <Check className="h-4 w-4" />
                                    ) : (
                                        <X className="h-4 w-4" />
                                    )}
                                </span>
                                {aceptar ? 'Aceptar' : 'Rechazar'} modificación{' '}
                                {m.folio_completo}
                            </DialogTitle>
                            <DialogDescription>
                                {aceptar
                                    ? 'Al aceptarla, el presupuesto vigente de las partidas cambia de inmediato.'
                                    : 'La modificación no procede; el presupuesto no cambia.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex items-center justify-between rounded-lg bg-muted/60 px-4 py-3">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    {tipos[m.tipo] ?? m.tipo}
                                    {m.solicitante?.name &&
                                        ` · solicitó ${m.solicitante.name}`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {fechaLocal(m.created_at, false)}
                                </p>
                            </div>
                            <p className="font-mono text-2xl font-semibold">
                                {money(importe)}
                            </p>
                        </div>

                        <div className="space-y-2">
                            {m.origen_descripcion && (
                                <LadoMovimiento
                                    titulo="Emisor (disminuye)"
                                    signo={-1}
                                    descripcion={m.origen_descripcion}
                                    mes={m.origen_mes}
                                    disponible={m.origen_disponible}
                                    disponibleMes={m.origen_disponible_mes}
                                    importe={importe}
                                    meses={meses}
                                    mostrarDespues={aceptar}
                                />
                            )}
                            {m.origen_descripcion && m.destino_descripcion && (
                                <div className="flex justify-center text-muted-foreground">
                                    <ArrowDown className="h-4 w-4" />
                                </div>
                            )}
                            {m.destino_descripcion && (
                                <LadoMovimiento
                                    titulo="Receptor (aumenta)"
                                    signo={1}
                                    descripcion={m.destino_descripcion}
                                    mes={m.destino_mes}
                                    disponible={m.destino_disponible}
                                    disponibleMes={m.destino_disponible_mes}
                                    importe={importe}
                                    meses={meses}
                                    mostrarDespues={aceptar}
                                />
                            )}
                        </div>

                        {emisorNoAlcanza && (
                            <p className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                Sin suficiencia: al emisor le faltan{' '}
                                {money(faltante)}
                                {m.origen_mes
                                    ? ` en ${meses[m.origen_mes]}`
                                    : ''}
                                . No se puede aceptar; recházala o espera a que
                                tenga disponible.
                            </p>
                        )}

                        <div className="space-y-1">
                            <label
                                htmlFor="observaciones"
                                className="text-sm font-medium"
                            >
                                {aceptar
                                    ? 'Observaciones (opcional)'
                                    : 'Motivo por el que no procede'}
                                {!aceptar && (
                                    <span className="text-destructive"> *</span>
                                )}
                            </label>
                            <textarea
                                id="observaciones"
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                                required={!aceptar}
                                className="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            {form.errors.observaciones && (
                                <p className="text-sm text-destructive">
                                    {form.errors.observaciones}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={cerrar}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant={aceptar ? 'default' : 'destructive'}
                                className={
                                    aceptar
                                        ? 'bg-green-700 hover:bg-green-800'
                                        : ''
                                }
                                disabled={
                                    form.processing ||
                                    emisorNoAlcanza ||
                                    (!aceptar &&
                                        !form.data.observaciones.trim())
                                }
                            >
                                {aceptar ? (
                                    <Check className="mr-1 h-4 w-4" />
                                ) : (
                                    <X className="mr-1 h-4 w-4" />
                                )}
                                {form.processing
                                    ? 'Guardando...'
                                    : aceptar
                                      ? 'Aceptar modificación'
                                      : 'Rechazar modificación'}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function Index({
    modificaciones,
    filters,
    tipos,
    meses,
    captura,
}: {
    captura: CapturaModificaciones;
    modificaciones: Paginado<Modificacion>;
    filters: Record<string, string>;
    tipos: Record<string, string>;
    meses: Record<string, string>;
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const puedeAutorizar = permisos.includes('modificaciones-autorizar');
    const [resolver, setResolver] = useState<{
        modificacion: Modificacion | null;
        accion: Accion;
    }>({ modificacion: null, accion: 'autorizar' });
    // Disponible actual de la partida (anual y del periodo elegido).
    const saldo = (
        disponible: number | null,
        disponibleMes: number | null,
        mes: number | null,
    ) => {
        const valor =
            mes && disponibleMes !== null ? disponibleMes : disponible;

        return valor === null ? null : (
            <div className="pl-3 text-[11px] text-muted-foreground">
                Disponible
                {mes && disponibleMes !== null && (
                    <>
                        {' de '}
                        <span className="capitalize">{meses[mes]}</span>
                    </>
                )}
                :{' '}
                <span
                    className={`font-mono ${valor < 0 ? 'text-destructive' : 'text-foreground'}`}
                >
                    {money(valor)}
                </span>
            </div>
        );
    };

    const periodo = (mes: number | null) =>
        mes ? (
            <span className="ml-1 rounded bg-muted px-1 text-[10px] font-medium uppercase">
                {meses[mes]}
            </span>
        ) : null;
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

    const lineaModificacion = (
        m: (typeof modificaciones.data)[number],
        lado: 'origen' | 'destino',
    ) => {
        const descripcion =
            lado === 'origen' ? m.origen_descripcion : m.destino_descripcion;

        if (!descripcion) {
            return <span className="text-muted-foreground">—</span>;
        }

        return (
            <>
                <div className="truncate" title={descripcion}>
                    {lado === 'origen' ? (
                        <span className="text-destructive">−</span>
                    ) : (
                        <span className="text-green-700">+</span>
                    )}{' '}
                    {descripcion}
                    {periodo(lado === 'origen' ? m.origen_mes : m.destino_mes)}
                </div>
                {lado === 'origen'
                    ? saldo(
                          m.origen_disponible,
                          m.origen_disponible_mes,
                          m.origen_mes,
                      )
                    : saldo(
                          m.destino_disponible,
                          m.destino_disponible_mes,
                          m.destino_mes,
                      )}
            </>
        );
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
                    {permisos.includes('modificaciones-create') &&
                        captura.abierta && (
                            <Button asChild>
                                <Link href="/modificaciones-presupuestales/crear">
                                    <Plus className="mr-2 h-4 w-4" /> Nueva
                                    modificación
                                </Link>
                            </Button>
                        )}
                </div>

                {permisos.includes('modificaciones-create') && (
                    <div className="mb-4">
                        <AvisoCapturaModificaciones captura={captura} />
                    </div>
                )}

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
                                        Origen (de dónde viene)
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
                                    <TableHead className="p-2 align-top">
                                        Destino (a dónde va)
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
                                            colSpan={8}
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
                                        {/* Origen: de dónde viene (traspaso y reducción). La ampliación tiene una sola partida y va aquí. */}
                                        <TableCell className="max-w-xs text-xs">
                                            {m.tipo === 'ampliacion'
                                                ? lineaModificacion(
                                                      m,
                                                      'destino',
                                                  )
                                                : lineaModificacion(
                                                      m,
                                                      'origen',
                                                  )}
                                        </TableCell>
                                        {/* Destino: a dónde va (solo traspaso). */}
                                        <TableCell className="max-w-xs text-xs">
                                            {m.tipo === 'traspaso' ? (
                                                lineaModificacion(m, 'destino')
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {money(m.importe)}
                                            {m.estado === 'pendiente' &&
                                                faltanteEmisor(m) > 0 && (
                                                    <div className="text-[11px] font-medium text-destructive">
                                                        Sin suficiencia: faltan{' '}
                                                        {money(
                                                            faltanteEmisor(m),
                                                        )}
                                                    </div>
                                                )}
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
                                            <div className="flex items-center gap-1">
                                                {puedeAutorizar &&
                                                    m.estado ===
                                                        'pendiente' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                className="bg-green-700 hover:bg-green-800"
                                                                disabled={
                                                                    faltanteEmisor(
                                                                        m,
                                                                    ) > 0
                                                                }
                                                                title={
                                                                    faltanteEmisor(
                                                                        m,
                                                                    ) > 0
                                                                        ? `Sin suficiencia: al emisor le faltan ${money(faltanteEmisor(m))}`
                                                                        : ''
                                                                }
                                                                onClick={() =>
                                                                    setResolver(
                                                                        {
                                                                            modificacion:
                                                                                m,
                                                                            accion: 'autorizar',
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Check className="mr-1 h-4 w-4" />
                                                                Aceptar
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    setResolver(
                                                                        {
                                                                            modificacion:
                                                                                m,
                                                                            accion: 'rechazar',
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <X className="mr-1 h-4 w-4" />
                                                                Rechazar
                                                            </Button>
                                                        </>
                                                    )}
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

            <ResolverModificacion
                modificacion={resolver.modificacion}
                accion={resolver.accion}
                tipos={tipos}
                meses={meses}
                onClose={() =>
                    setResolver((r) => ({ ...r, modificacion: null }))
                }
            />
        </>
    );
}

Index.layout = { breadcrumbs };
