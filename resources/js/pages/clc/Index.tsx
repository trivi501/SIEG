import { Head, Link, router, usePage } from '@inertiajs/react';
import { Banknote, FileText, Inbox, Search, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmarAccion from '@/components/confirmar-accion';
import EstadoSat from '@/components/estado-sat';
import type { EstadoSatFactura } from '@/components/estado-sat';
import Paginacion, { paginar } from '@/components/paginacion';
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
import { fechaLocal, money } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
    { title: 'Trámite de CLC', href: '/clc' },
];

interface Clc extends EstadoSatFactura {
    id: number;
    clc: string;
    clc_fecha: string | null;
    estado: string;
    orden: string;
    importe: number;
    proveedor: string | null;
    requisicion_id: number;
    requisicion: string | null;
    concepto: string | null;
    unidad: string | null;
    relacion: string | null;
    relacion_id: number | null;
    transferencia: string | null;
    pago_fecha: string | null;
    devolucion: string | null;
    seguimiento: boolean;
}

interface Relacion {
    id: number;
    folio: string;
    origen: string;
    destino: string;
    clcs: number;
    usuario: string | null;
    fecha: string | null;
}

interface AccionConfig {
    etiqueta: string;
    permiso: string;
    envio: string | null;
    relacion: boolean;
    recibe: boolean;
    observaciones: boolean;
    pago: boolean;
    a: string;
}

interface Grupo {
    titulo: string;
    estados: string[];
    acciones: string[];
}

interface Bandeja {
    clave: string;
    titulo: string;
    permisos: string[];
    grupos: Grupo[];
}

/** Bandejas por área, en el orden del trámite. */
const BANDEJAS: Bandeja[] = [
    {
        clave: 'area',
        titulo: 'Mi área',
        permisos: ['requisiciones-clc'],
        grupos: [
            {
                titulo: 'CLC generadas — por enviar a Recursos Materiales',
                estados: ['generada'],
                acciones: ['enviar_rm'],
            },
        ],
    },
    {
        clave: 'rm',
        titulo: 'Recursos Materiales',
        permisos: ['clc-recursos-materiales'],
        grupos: [
            {
                titulo: 'Por recibir',
                estados: ['enviada_rm'],
                acciones: ['recibir_rm'],
            },
            {
                titulo: 'En Recursos Materiales — por enviar a Control Presupuestal',
                estados: ['recibida_rm', 'devuelta'],
                acciones: ['enviar_cp'],
            },
        ],
    },
    {
        clave: 'cp',
        titulo: 'Control Presupuestal',
        permisos: ['clc-control-presupuestal'],
        grupos: [
            {
                titulo: 'Por recibir de Recursos Materiales',
                estados: ['enviada_cp'],
                acciones: ['recibir_cp'],
            },
            {
                titulo: 'En revisión — enviar a Sindicatura o devolver',
                estados: ['recibida_cp'],
                acciones: ['enviar_sindicatura', 'devolver'],
            },
            {
                titulo: 'Por recibir de Sindicatura (firmadas)',
                estados: ['enviada_cp_firmada'],
                acciones: ['recibir_cp_firmada'],
            },
            {
                titulo: 'Firmadas — por enviar a Egresos',
                estados: ['recibida_cp_firmada'],
                acciones: ['enviar_egresos'],
            },
        ],
    },
    {
        clave: 'sindicatura',
        titulo: 'Sindicatura',
        permisos: ['clc-sindicatura'],
        grupos: [
            {
                titulo: 'Por recibir de Control Presupuestal',
                estados: ['enviada_sindicatura'],
                acciones: ['recibir_sindicatura'],
            },
            {
                titulo: 'En Sindicatura — por enviar a Control Presupuestal',
                estados: ['recibida_sindicatura'],
                acciones: ['enviar_cp_firmada'],
            },
        ],
    },
    {
        clave: 'egresos',
        titulo: 'Egresos',
        permisos: ['clc-egresos', 'ordenes-compra-pagar'],
        grupos: [
            {
                titulo: 'Por recibir de Control Presupuestal',
                estados: ['enviada_egresos'],
                acciones: ['recibir_egresos'],
            },
            {
                titulo: 'Por pagar',
                estados: ['recibida_egresos'],
                acciones: ['pagar'],
            },
        ],
    },
];

const colorEstado = (estado: string) =>
    estado === 'pagada'
        ? 'bg-green-100 text-green-800'
        : estado === 'devuelta'
          ? 'bg-red-100 text-red-800'
          : estado.startsWith('enviada')
            ? 'bg-amber-100 text-amber-900'
            : 'bg-blue-100 text-blue-900';

function iconoAccion(config: AccionConfig) {
    if (config.pago) {
        return Banknote;
    }

    if (config.observaciones) {
        return Undo2;
    }

    return config.recibe ? Inbox : Send;
}

export default function Index({
    clcs,
    relaciones,
    estados,
    acciones,
    formasPago,
    veTodo,
    seguimientoTodas,
}: {
    veTodo: boolean;
    seguimientoTodas: boolean;
    clcs: Clc[];
    relaciones: Relacion[];
    estados: Record<string, string>;
    acciones: Record<string, AccionConfig>;
    formasPago: Record<string, string>;
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const puede = (p: string) => permisos.includes(p);
    // Control Presupuestal y Admin ven todas las bandejas; cada área, la suya. Actuar requiere el permiso.
    const bandejas = BANDEJAS.filter((b) => veTodo || b.permisos.some(puede));
    const verRelaciones = veTodo || puede('clc-sindicatura');
    const pestañas = [
        ...bandejas.map((b) => ({ clave: b.clave, titulo: b.titulo })),
        { clave: 'seguimiento', titulo: 'Seguimiento' },
        ...(verRelaciones
            ? [{ clave: 'relaciones', titulo: 'Relaciones (Sindicatura)' }]
            : []),
    ];
    const [pestaña, setPestaña] = useState(pestañas[0].clave);
    const [seleccion, setSeleccion] = useState<Record<string, number[]>>({});
    const [pendiente, setPendiente] = useState<{
        grupo: string;
        accion: string;
        ids: number[];
    } | null>(null);
    const [ultimaRelacion, setUltimaRelacion] = useState<Relacion | null>(null);
    const [buscar, setBuscar] = useState('');
    const [filtroPago, setFiltroPago] = useState<
        'todas' | 'tramite' | 'pagadas'
    >('todas');
    // Página de cada tabla (bandeja, seguimiento, relaciones); la búsqueda regresa a la primera.
    const [paginas, setPaginas] = useState<Record<string, number>>({});
    const irA = (grupo: string, pagina: number) =>
        setPaginas((p) => ({ ...p, [grupo]: pagina }));
    const cambiarBusqueda = (texto: string) => {
        setBuscar(texto);
        setPaginas({});
    };
    // Sin distinguir mayúsculas ni acentos ("tesoreria" encuentra "TESORERÍA").
    const normalizar = (texto: string) =>
        texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const terminos = normalizar(buscar.trim()).split(/\s+/).filter(Boolean);
    const coincide = (valores: (string | number | null | undefined)[]) => {
        const texto = normalizar(valores.join(' '));

        return terminos.every((t) => texto.includes(t));
    };
    const coincideClc = (c: Clc) =>
        coincide([
            c.clc,
            c.orden,
            c.requisicion,
            c.unidad,
            c.proveedor,
            c.concepto,
            c.transferencia,
            c.relacion,
            estados[c.estado],
        ]);

    const elegidos = (grupo: string) => seleccion[grupo] ?? [];
    const alternar = (grupo: string, id: number) =>
        setSeleccion((s) => {
            const actual = s[grupo] ?? [];

            return {
                ...s,
                [grupo]: actual.includes(id)
                    ? actual.filter((x) => x !== id)
                    : [...actual, id],
            };
        });
    const fijar = (grupo: string, ids: number[]) =>
        setSeleccion((s) => ({ ...s, [grupo]: ids }));

    const seleccionadas = pendiente
        ? clcs.filter((c) => pendiente.ids.includes(c.id))
        : [];
    const config = pendiente ? acciones[pendiente.accion] : null;

    const ejecutar = (
        observaciones: string,
        op: Parameters<typeof router.post>[2],
        pago?: Record<string, string>,
    ) =>
        router.post(
            '/clc/accion',
            {
                accion: pendiente?.accion ?? '',
                ordenes: pendiente?.ids ?? [],
                observaciones,
                ...(pago ?? {}),
            },
            {
                ...op,
                onSuccess: (p: any) => {
                    if (config?.relacion) {
                        setUltimaRelacion(p.props.relaciones?.[0] ?? null);
                    }

                    fijar(pendiente?.grupo ?? '', []);
                    op?.onSuccess?.(p);
                },
            },
        );

    const tabla = (
        grupo: string,
        filas: Clc[],
        conSeleccion: boolean,
        porRecibir = false,
    ) => {
        const pag = paginar(filas, paginas[grupo] ?? 1);
        const idsPagina = pag.filas.map((f) => f.id);
        const paginaElegida =
            idsPagina.length > 0 &&
            idsPagina.every((id) => elegidos(grupo).includes(id));

        return (
            <>
                <Table>
                    <TableHeader>
                        <TableRow>
                            {conSeleccion && (
                                <TableHead className="w-8">
                                    <input
                                        type="checkbox"
                                        aria-label="Seleccionar las de esta página"
                                        checked={paginaElegida}
                                        onChange={(e) =>
                                            fijar(
                                                grupo,
                                                e.target.checked
                                                    ? [
                                                          ...new Set([
                                                              ...elegidos(
                                                                  grupo,
                                                              ),
                                                              ...idsPagina,
                                                          ]),
                                                      ]
                                                    : elegidos(grupo).filter(
                                                          (id) =>
                                                              !idsPagina.includes(
                                                                  id,
                                                              ),
                                                      ),
                                            )
                                        }
                                    />
                                </TableHead>
                            )}
                            <TableHead>CLC</TableHead>
                            <TableHead>O.C.</TableHead>
                            <TableHead>Requisición</TableHead>
                            <TableHead>Unidad administrativa</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead className="text-right">
                                Importe
                            </TableHead>
                            {porRecibir && <TableHead>Relación</TableHead>}
                            {!conSeleccion && <TableHead>Estado</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filas.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={9}
                                    className="py-6 text-center text-muted-foreground"
                                >
                                    {terminos.length > 0
                                        ? 'Ninguna CLC coincide con la búsqueda.'
                                        : 'No hay CLC.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {pag.filas.map((c) => (
                            <TableRow
                                key={c.id}
                                className={
                                    elegidos(grupo).includes(c.id)
                                        ? 'bg-muted/50'
                                        : ''
                                }
                            >
                                {conSeleccion && (
                                    <TableCell>
                                        <input
                                            type="checkbox"
                                            aria-label={`Seleccionar ${c.clc}`}
                                            checked={elegidos(grupo).includes(
                                                c.id,
                                            )}
                                            onChange={() =>
                                                alternar(grupo, c.id)
                                            }
                                        />
                                    </TableCell>
                                )}
                                <TableCell className="font-medium">
                                    {c.clc}
                                    {c.transferencia && (
                                        <div className="text-xs font-normal text-green-700 dark:text-green-400">
                                            Transferencia {c.transferencia}
                                        </div>
                                    )}
                                    {c.devolucion && (
                                        <div className="mt-1 max-w-xs rounded bg-red-50 px-2 py-1 text-xs whitespace-normal text-red-800">
                                            Devuelta: {c.devolucion}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell>{c.orden}</TableCell>
                                <TableCell>
                                    <Link
                                        href={`/requisiciones/${c.requisicion_id}`}
                                        className="text-primary hover:underline"
                                    >
                                        {c.requisicion}
                                    </Link>
                                </TableCell>
                                <TableCell className="max-w-56 text-xs whitespace-normal">
                                    {c.unidad ?? '—'}
                                </TableCell>
                                <TableCell className="max-w-56 text-xs whitespace-normal">
                                    {c.proveedor ?? '—'}
                                    <div className="mt-1">
                                        <EstadoSat
                                            orden={c}
                                            compacto
                                            puedeVerificar={puede(
                                                'ordenes-compra-verificar-sat',
                                            )}
                                        />
                                    </div>
                                </TableCell>
                                <TableCell className="text-right">
                                    {money(c.importe)}
                                </TableCell>
                                {porRecibir && (
                                    <TableCell>
                                        {c.relacion ? (
                                            <button
                                                type="button"
                                                className="text-xs text-primary hover:underline"
                                                title="Seleccionar todas las CLC de esta relación"
                                                onClick={() =>
                                                    fijar(
                                                        grupo,
                                                        filas
                                                            .filter(
                                                                (f) =>
                                                                    f.relacion_id ===
                                                                    c.relacion_id,
                                                            )
                                                            .map((f) => f.id),
                                                    )
                                                }
                                            >
                                                {c.relacion}
                                            </button>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                )}
                                {!conSeleccion && (
                                    <TableCell>
                                        <Badge
                                            variant="secondary"
                                            className={colorEstado(c.estado)}
                                        >
                                            {estados[c.estado] ?? c.estado}
                                        </Badge>
                                        {c.pago_fecha && (
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Pagada el{' '}
                                                {c.pago_fecha
                                                    .split('-')
                                                    .reverse()
                                                    .join('/')}
                                            </div>
                                        )}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Paginacion {...pag} onCambiar={(n) => irA(grupo, n)} />
            </>
        );
    };

    const bandeja = bandejas.find((b) => b.clave === pestaña);
    const totalPagadas = clcs.filter(
        (c) => c.seguimiento && c.estado === 'pagada',
    ).length;
    const seguimiento = clcs.filter(
        (c) =>
            c.seguimiento &&
            (filtroPago === 'todas' ||
                (filtroPago === 'pagadas') === (c.estado === 'pagada')) &&
            coincideClc(c),
    );
    const relacionesFiltradas = relaciones.filter((r) =>
        coincide([r.folio, r.origen, r.destino, r.usuario]),
    );
    const pagRelaciones = paginar(relacionesFiltradas, paginas.relaciones ?? 1);

    return (
        <>
            <Head title="Trámite de CLC" />
            <div className="space-y-4 p-6">
                <div>
                    <h3 className="text-lg font-medium">Trámite de CLC</h3>
                    <p className="text-sm text-muted-foreground">
                        Área → Recursos Materiales → Control Presupuestal →
                        Sindicatura → Control Presupuestal → Egresos (pago). Los
                        envíos entre Control Presupuestal y Sindicatura llevan
                        relación para imprimir y entregar con las CLC.
                    </p>
                </div>

                {ultimaRelacion && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                        <span>
                            Relación <strong>{ultimaRelacion.folio}</strong>{' '}
                            generada: {ultimaRelacion.clcs} CLC de{' '}
                            {ultimaRelacion.origen} para{' '}
                            {ultimaRelacion.destino}.
                        </span>
                        <Button size="sm" asChild>
                            <a
                                href={`/clc/relaciones/${ultimaRelacion.id}/pdf`}
                                target="_blank"
                            >
                                <FileText className="mr-1 h-4 w-4" /> Imprimir
                                relación
                            </a>
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap items-end gap-1 border-b">
                    {pestañas.map((p) => (
                        <button
                            key={p.clave}
                            type="button"
                            onClick={() => setPestaña(p.clave)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${pestaña === p.clave ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {p.titulo}
                        </button>
                    ))}
                    <div className="relative mb-1.5 ml-auto w-full sm:w-80">
                        <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="search"
                            value={buscar}
                            onChange={(e) => cambiarBusqueda(e.target.value)}
                            placeholder="Buscar CLC, O.C., requisición, proveedor, unidad…"
                            aria-label="Buscar CLC"
                            className="w-full rounded-md border border-input bg-background py-1.5 pr-2 pl-7 text-sm"
                        />
                    </div>
                </div>

                {bandeja?.grupos.map((g, i) => {
                    const clave = `${bandeja.clave}-${i}`;
                    const enPaso = clcs.filter((c) =>
                        g.estados.includes(c.estado),
                    );
                    const filas = enPaso.filter(coincideClc);
                    // Solo cuentan las seleccionadas que siguen visibles con la búsqueda.
                    const ids = elegidos(clave).filter((id) =>
                        filas.some((f) => f.id === id),
                    );
                    const total = filas
                        .filter((f) => ids.includes(f.id))
                        .reduce((s, f) => s + Number(f.importe), 0);
                    const porRecibir = g.acciones.some(
                        (a) => acciones[a]?.recibe,
                    );

                    return (
                        <Card key={clave}>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    {g.titulo}
                                    <Badge variant="secondary">
                                        {terminos.length > 0
                                            ? `${filas.length} de ${enPaso.length}`
                                            : enPaso.length}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {filas.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        {enPaso.length === 0
                                            ? 'No hay CLC en este paso.'
                                            : 'Ninguna CLC de este paso coincide con la búsqueda.'}
                                    </p>
                                ) : (
                                    <>
                                        {tabla(clave, filas, true, porRecibir)}
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                            <span className="text-sm text-muted-foreground">
                                                {ids.length} seleccionada
                                                {ids.length === 1 ? '' : 's'}
                                                {ids.length > 0 &&
                                                    ` · ${money(total)}`}
                                            </span>
                                            <div className="flex flex-wrap gap-2">
                                                {g.acciones
                                                    .filter((a) =>
                                                        puede(
                                                            acciones[a]
                                                                ?.permiso,
                                                        ),
                                                    )
                                                    .map((a) => {
                                                        const Icono =
                                                            iconoAccion(
                                                                acciones[a],
                                                            );

                                                        return (
                                                            <Button
                                                                key={a}
                                                                size="sm"
                                                                variant={
                                                                    acciones[a]
                                                                        .observaciones
                                                                        ? 'outline'
                                                                        : 'default'
                                                                }
                                                                className={
                                                                    acciones[a]
                                                                        .observaciones
                                                                        ? 'border-red-300 text-red-700 hover:bg-red-50'
                                                                        : ''
                                                                }
                                                                disabled={
                                                                    ids.length ===
                                                                    0
                                                                }
                                                                onClick={() =>
                                                                    setPendiente(
                                                                        {
                                                                            grupo: clave,
                                                                            accion: a,
                                                                            ids,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Icono className="mr-1 h-4 w-4" />
                                                                {
                                                                    acciones[a]
                                                                        .etiqueta
                                                                }
                                                            </Button>
                                                        );
                                                    })}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}

                {pestaña === 'seguimiento' && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
                                {seguimientoTodas ? 'Todas las CLC' : 'Mis CLC'}
                                <div className="ml-auto flex rounded-md border text-xs font-normal">
                                    {(
                                        [
                                            ['todas', 'Todas'],
                                            ['tramite', 'En trámite'],
                                            [
                                                'pagadas',
                                                `Pagadas (${totalPagadas})`,
                                            ],
                                        ] as const
                                    ).map(([valor, etiqueta]) => (
                                        <button
                                            key={valor}
                                            type="button"
                                            onClick={() => {
                                                setFiltroPago(valor);
                                                irA('seguimiento', 1);
                                            }}
                                            className={`px-2.5 py-1 first:rounded-l-md last:rounded-r-md ${filtroPago === valor ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                        >
                                            {etiqueta}
                                        </button>
                                    ))}
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tabla('seguimiento', seguimiento, false)}
                        </CardContent>
                    </Card>
                )}

                {pestaña === 'relaciones' && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">
                                Relaciones de envío
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Folio</TableHead>
                                        <TableHead>Entrega → Recibe</TableHead>
                                        <TableHead className="text-right">
                                            CLC
                                        </TableHead>
                                        <TableHead>Envió</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {relacionesFiltradas.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="text-center text-muted-foreground"
                                            >
                                                {relaciones.length === 0
                                                    ? 'Todavía no hay relaciones.'
                                                    : 'Ninguna relación coincide con la búsqueda.'}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {pagRelaciones.filas.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell className="font-medium">
                                                {r.folio}
                                            </TableCell>
                                            <TableCell>
                                                {r.origen} → {r.destino}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {r.clcs}
                                            </TableCell>
                                            <TableCell>
                                                {r.usuario ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {fechaLocal(r.fecha)}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/clc/relaciones/${r.id}/pdf`}
                                                        target="_blank"
                                                    >
                                                        <FileText className="mr-1 h-4 w-4" />
                                                        PDF
                                                    </a>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Paginacion
                                {...pagRelaciones}
                                onCambiar={(n) => irA('relaciones', n)}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>

            {config && !config.pago && (
                <ConfirmarAccion
                    abierto={pendiente !== null}
                    onCerrar={() => setPendiente(null)}
                    titulo={
                        config.etiqueta === 'Recibir'
                            ? 'Recibir CLC'
                            : config.etiqueta
                    }
                    descripcion={
                        config.recibe
                            ? 'Se marcan como recibidas las CLC seleccionadas. Las que no se seleccionaron siguen pendientes de recibir.'
                            : config.observaciones
                              ? 'Regresan a Recursos Materiales con las observaciones para que las corrija y las vuelva a enviar.'
                              : config.relacion
                                ? `Se genera una relación con las CLC seleccionadas para imprimirla y entregarla a ${config.envio}.`
                                : `Se envían las CLC seleccionadas a ${config.envio}.`
                    }
                    tono={
                        config.observaciones
                            ? 'peligro'
                            : config.recibe
                              ? 'exito'
                              : 'info'
                    }
                    encabezado={{
                        titulo: `${seleccionadas.length} CLC`,
                        subtitulo: money(
                            seleccionadas.reduce(
                                (s, c) => s + Number(c.importe),
                                0,
                            ),
                        ),
                    }}
                    datos={[
                        {
                            etiqueta: 'CLC',
                            valor: seleccionadas.map((c) => c.clc).join(', '),
                            ancho: true,
                        },
                    ]}
                    cambio={{
                        de: estados[seleccionadas[0]?.estado] ?? '',
                        a: config.a,
                    }}
                    motivo={
                        config.observaciones
                            ? {
                                  etiqueta: 'Observaciones de la devolución',
                                  requerido: true,
                                  minimo: 5,
                                  placeholder:
                                      'Qué debe corregir Recursos Materiales…',
                              }
                            : config.relacion
                              ? {
                                    etiqueta:
                                        'Observaciones (opcional, salen en la relación)',
                                }
                              : undefined
                    }
                    textoConfirmar={config.etiqueta}
                    onConfirmar={(observaciones, op) =>
                        ejecutar(observaciones, op)
                    }
                />
            )}

            {config?.pago && (
                <PagoDialog
                    abierto={pendiente !== null}
                    onCerrar={() => setPendiente(null)}
                    clcs={seleccionadas}
                    formasPago={formasPago}
                    onGuardar={(pago, op) => ejecutar('', op, pago)}
                />
            )}
        </>
    );
}

/** Egresos marca pagadas las CLC seleccionadas con la fecha, forma y referencia del pago. */
function PagoDialog({
    abierto,
    onCerrar,
    clcs,
    formasPago,
    onGuardar,
}: {
    abierto: boolean;
    onCerrar: () => void;
    clcs: Clc[];
    formasPago: Record<string, string>;
    onGuardar: (
        pago: Record<string, string>,
        op: Parameters<typeof router.post>[2],
    ) => void;
}) {
    const [pago, setPago] = useState({
        pago_fecha: new Date().toISOString().slice(0, 10),
        pago_forma: 'transferencia',
        pago_referencia: '',
    });
    const [errores, setErrores] = useState<string[]>([]);
    const [guardando, setGuardando] = useState(false);
    const total = clcs.reduce((s, c) => s + Number(c.importe), 0);
    const esTransferencia = pago.pago_forma === 'transferencia';

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        setGuardando(true);
        setErrores([]);
        onGuardar(pago, {
            preserveScroll: true,
            onSuccess: () => onCerrar(),
            onError: (er) => setErrores(Object.values(er)),
            onFinish: () => setGuardando(false),
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(a) => !a && !guardando && onCerrar()}
        >
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-700">
                                <Banknote className="h-5 w-5" />
                            </span>
                            Marcar pagadas
                        </DialogTitle>
                        <DialogDescription>
                            El importe pasa de devengado a pagado y las
                            requisiciones quedan Pagadas.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                        <p className="text-lg font-semibold">
                            {clcs.length} CLC · {money(total)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {clcs
                                .map((c) => `${c.clc} (${c.proveedor ?? '—'})`)
                                .join(', ')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="grid gap-1 text-sm">
                            Fecha de pago
                            <input
                                type="date"
                                required
                                value={pago.pago_fecha}
                                onChange={(e) =>
                                    setPago({
                                        ...pago,
                                        pago_fecha: e.target.value,
                                    })
                                }
                                className="rounded-md border border-input bg-transparent px-2 py-1.5"
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            Forma de pago
                            <select
                                value={pago.pago_forma}
                                onChange={(e) =>
                                    setPago({
                                        ...pago,
                                        pago_forma: e.target.value,
                                    })
                                }
                                className="rounded-md border border-input bg-transparent px-2 py-1.5"
                            >
                                {Object.entries(formasPago).map(
                                    ([valor, nombre]) => (
                                        <option key={valor} value={valor}>
                                            {nombre}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                        {esTransferencia && (
                            <p className="rounded-md bg-green-50 px-3 py-2 text-xs text-green-900 sm:col-span-2 dark:bg-green-950 dark:text-green-200">
                                El <strong>número de transferencia</strong> se
                                genera automático al guardar (TRF-
                                {pago.pago_fecha.slice(0, 4)}-0001,
                                consecutivo), uno por CLC; con él se arma la
                                posición bancaria.
                            </p>
                        )}
                        <label className="grid gap-1 text-sm sm:col-span-2">
                            {esTransferencia
                                ? 'Referencia bancaria (opcional: clave de rastreo)'
                                : 'Número de cheque'}
                            <input
                                required={!esTransferencia}
                                maxLength={100}
                                value={pago.pago_referencia}
                                onChange={(e) =>
                                    setPago({
                                        ...pago,
                                        pago_referencia: e.target.value,
                                    })
                                }
                                className="rounded-md border border-input bg-transparent px-2 py-1.5"
                            />
                        </label>
                    </div>
                    {errores.map((e) => (
                        <p
                            key={e}
                            className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        >
                            {e}
                        </p>
                    ))}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando}
                            onClick={onCerrar}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            className="bg-green-700 text-white hover:bg-green-800"
                            disabled={
                                guardando ||
                                (!esTransferencia &&
                                    !pago.pago_referencia.trim())
                            }
                        >
                            <Banknote className="mr-1 h-4 w-4" />
                            {guardando ? 'Guardando...' : 'Marcar pagadas'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

Index.layout = { breadcrumbs };
