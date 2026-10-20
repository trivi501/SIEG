import { Head, Link, router } from '@inertiajs/react';
import { CarFront, FilePlus2, KeyRound, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Paginas } from '@/components/almacen-comun';
import type { Paginado } from '@/components/almacen-comun';
import SearchableSelect from '@/components/searchable-select';
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
import { fecha, numero } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

interface Mantenimiento {
    id: number;
    folio: string;
    vehiculo: string;
    secretaria: string | null;
    area: string | null;
    fecha_recepcion: string | null;
    kilometraje: number | null;
    tipo: string;
    descripcion: string;
    entrega: string | null;
    recibio: string | null;
    estado: 'en_taller' | 'entregado';
    fecha_entrega: string | null;
    recibe_entrega: string | null;
    requisicion_id: number | null;
    requisicion: string | null;
    requisicion_estado: string | null;
}

interface VehiculoOpcion {
    id: number;
    texto: string;
    secretaria: string | null;
    area: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vehículos', href: '/catalogos/vehiculos' },
    { title: 'Mantenimiento', href: '/vehiculos/mantenimientos' },
];

const hoy = () => new Date().toISOString().slice(0, 10);

export default function Mantenimientos({
    mantenimientos,
    vehiculos,
    filtros,
    tipos,
    puedeRequisicion,
}: {
    mantenimientos: Paginado<Mantenimiento>;
    vehiculos: VehiculoOpcion[];
    filtros: { buscar?: string; estado?: string };
    tipos: Record<string, string>;
    puedeRequisicion: boolean;
}) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [recibir, setRecibir] = useState(false);
    const [entregar, setEntregar] = useState<Mantenimiento | null>(null);
    const [form, setForm] = useState({
        vehiculo_id: '',
        fecha_recepcion: hoy(),
        kilometraje: '',
        tipo: 'correctivo',
        descripcion: '',
        entrega: '',
    });
    const [salida, setSalida] = useState({
        fecha_entrega: hoy(),
        recibe_entrega: '',
        observaciones_entrega: '',
    });
    const [errores, setErrores] = useState<string[]>([]);
    const [guardando, setGuardando] = useState(false);
    const elegido = vehiculos.find((v) => String(v.id) === form.vehiculo_id);

    const ir = (cambios: Record<string, string>) =>
        router.get(
            '/vehiculos/mantenimientos',
            Object.fromEntries(
                Object.entries({
                    buscar,
                    estado: filtros.estado ?? '',
                    ...cambios,
                }).filter(([, v]) => v),
            ),
            { preserveState: true, preserveScroll: true },
        );

    const enviar = (url: string, datos: object, alTerminar: () => void) => {
        setGuardando(true);
        setErrores([]);
        router.post(url, datos as Record<string, string>, {
            preserveScroll: true,
            onSuccess: alTerminar,
            onError: (e) => setErrores(Object.values(e)),
            onFinish: () => setGuardando(false),
        });
    };

    return (
        <>
            <Head title="Mantenimiento vehicular" />
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-medium">
                            Mantenimiento vehicular
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Se recibe el vehículo en el taller y desde ahí se
                            hace la requisición a nombre de su secretaría de
                            resguardo (sigue el trámite normal). Al terminar se
                            entrega y vuelve a estar en servicio.
                        </p>
                    </div>
                    <Button
                        onClick={() => {
                            setErrores([]);
                            setRecibir(true);
                        }}
                    >
                        <CarFront className="mr-1 h-4 w-4" /> Recibir vehículo
                    </Button>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            <div className="flex rounded-md border text-xs font-normal">
                                {(
                                    [
                                        ['', 'Todos'],
                                        ['en_taller', 'En taller'],
                                        ['entregado', 'Entregados'],
                                    ] as const
                                ).map(([valor, texto]) => (
                                    <button
                                        key={valor}
                                        type="button"
                                        onClick={() => ir({ estado: valor })}
                                        className={`px-2.5 py-1 first:rounded-l-md last:rounded-r-md ${(filtros.estado ?? '') === valor ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
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
                                        placeholder="Folio, económico, placas, secretaría…"
                                        className="w-72 rounded-md border border-input bg-background py-1.5 pr-2 pl-7 text-sm font-normal"
                                    />
                                </div>
                                {(filtros.buscar || filtros.estado) && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setBuscar('');
                                            router.get(
                                                '/vehiculos/mantenimientos',
                                            );
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
                                    <TableHead>Folio</TableHead>
                                    <TableHead>Vehículo</TableHead>
                                    <TableHead>Secretaría / área</TableHead>
                                    <TableHead>Falla o servicio</TableHead>
                                    <TableHead>Requisición</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {mantenimientos.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            No hay vehículos con estos filtros.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {mantenimientos.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="font-medium whitespace-nowrap">
                                            {m.folio}
                                            <div className="text-xs font-normal text-muted-foreground">
                                                Recibido{' '}
                                                {fecha(m.fecha_recepcion)}
                                                {m.kilometraje !== null &&
                                                    ` · ${numero(m.kilometraje).replace('.00', '')} km`}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-56 text-xs whitespace-normal">
                                            {m.vehiculo}
                                            {m.entrega && (
                                                <div className="text-muted-foreground">
                                                    Lo trajo: {m.entrega}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-56 text-xs whitespace-normal">
                                            {m.secretaria ?? '—'}
                                            {m.area && (
                                                <div className="text-muted-foreground">
                                                    {m.area}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-64 text-xs whitespace-normal">
                                            <span className="font-medium">
                                                {m.tipo}:
                                            </span>{' '}
                                            {m.descripcion}
                                        </TableCell>
                                        <TableCell className="text-xs whitespace-nowrap">
                                            {m.requisicion ? (
                                                <>
                                                    <Link
                                                        href={`/requisiciones/${m.requisicion_id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {m.requisicion}
                                                    </Link>
                                                    <div className="text-muted-foreground">
                                                        {m.requisicion_estado}
                                                    </div>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Sin requisición
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <Badge
                                                variant="secondary"
                                                className={
                                                    m.estado === 'en_taller'
                                                        ? 'bg-amber-100 text-amber-900'
                                                        : 'bg-green-100 text-green-800'
                                                }
                                            >
                                                {m.estado === 'en_taller'
                                                    ? 'En taller'
                                                    : 'Entregado'}
                                            </Badge>
                                            {m.fecha_entrega && (
                                                <div className="mt-0.5 text-muted-foreground">
                                                    {fecha(m.fecha_entrega)} ·{' '}
                                                    {m.recibe_entrega}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {m.estado === 'en_taller' &&
                                                    !m.requisicion &&
                                                    puedeRequisicion && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/requisiciones/crear?mantenimiento=${m.id}`}
                                                                title="Hacer requisición"
                                                            >
                                                                <FilePlus2 className="mr-1 h-4 w-4" />
                                                                Requisición
                                                            </Link>
                                                        </Button>
                                                    )}
                                                {m.estado === 'en_taller' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        title="Entregar vehículo"
                                                        onClick={() => {
                                                            setErrores([]);
                                                            setSalida({
                                                                fecha_entrega:
                                                                    hoy(),
                                                                recibe_entrega:
                                                                    '',
                                                                observaciones_entrega:
                                                                    '',
                                                            });
                                                            setEntregar(m);
                                                        }}
                                                    >
                                                        <KeyRound className="mr-1 h-4 w-4" />
                                                        Entregar
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Paginas paginado={mantenimientos} />
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={recibir}
                onOpenChange={(a) => !a && !guardando && setRecibir(false)}
            >
                <DialogContent className="sm:max-w-xl">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            enviar('/vehiculos/mantenimientos', form, () => {
                                setRecibir(false);
                                setForm({
                                    vehiculo_id: '',
                                    fecha_recepcion: hoy(),
                                    kilometraje: '',
                                    tipo: 'correctivo',
                                    descripcion: '',
                                    entrega: '',
                                });
                            });
                        }}
                        className="space-y-3"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Recibir vehículo en el taller
                            </DialogTitle>
                            <DialogDescription>
                                Queda "En taller". Su requisición se hace a
                                nombre de la secretaría de resguardo.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-1 text-sm">
                            Vehículo
                            <SearchableSelect
                                value={form.vehiculo_id}
                                onChange={(v) =>
                                    setForm({ ...form, vehiculo_id: v })
                                }
                                options={vehiculos.map((v) => ({
                                    value: String(v.id),
                                    label: v.texto,
                                }))}
                                placeholder="Escribe el número económico o las placas…"
                                showValue={false}
                                inline
                            />
                            {elegido && (
                                <span className="text-xs text-muted-foreground">
                                    Resguardo: {elegido.secretaria ?? '—'}
                                    {elegido.area && ` · Área: ${elegido.area}`}
                                </span>
                            )}
                            {vehiculos.length === 0 && (
                                <span className="text-xs text-muted-foreground">
                                    No hay vehículos disponibles: da de alta el
                                    padrón vehicular (con su secretaría de
                                    resguardo).
                                </span>
                            )}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <label className="grid gap-1 text-sm">
                                Fecha
                                <input
                                    type="date"
                                    value={form.fecha_recepcion}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            fecha_recepcion: e.target.value,
                                        })
                                    }
                                    className="rounded-md border border-input px-2 py-1.5"
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Kilometraje
                                <input
                                    type="number"
                                    min={0}
                                    value={form.kilometraje}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            kilometraje: e.target.value,
                                        })
                                    }
                                    className="rounded-md border border-input px-2 py-1.5"
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Tipo
                                <select
                                    value={form.tipo}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            tipo: e.target.value,
                                        })
                                    }
                                    className="rounded-md border border-input px-2 py-1.5"
                                >
                                    {Object.entries(tipos).map(([v, t]) => (
                                        <option key={v} value={v}>
                                            {t}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        <label className="grid gap-1 text-sm">
                            Falla reportada o servicio solicitado
                            <textarea
                                value={form.descripcion}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        descripcion: e.target.value,
                                    })
                                }
                                rows={3}
                                maxLength={2000}
                                className="rounded-md border border-input px-2 py-1.5"
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            Quién lo trae
                            <input
                                value={form.entrega}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        entrega: e.target.value,
                                    })
                                }
                                maxLength={200}
                                className="rounded-md border border-input px-2 py-1.5"
                            />
                        </label>
                        {errores.map((er) => (
                            <p key={er} className="text-sm text-destructive">
                                {er}
                            </p>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRecibir(false)}
                                disabled={guardando}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardando || !form.vehiculo_id}
                            >
                                {guardando ? 'Guardando...' : 'Recibir'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={entregar !== null}
                onOpenChange={(a) => !a && !guardando && setEntregar(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            enviar(
                                `/vehiculos/mantenimientos/${entregar?.id}/entregar`,
                                salida,
                                () => setEntregar(null),
                            );
                        }}
                        className="space-y-3"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Entregar vehículo ({entregar?.folio})
                            </DialogTitle>
                            <DialogDescription>
                                {entregar?.vehiculo}. Vuelve a estar en
                                servicio.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-sm">
                                Fecha de entrega
                                <input
                                    type="date"
                                    value={salida.fecha_entrega}
                                    onChange={(e) =>
                                        setSalida({
                                            ...salida,
                                            fecha_entrega: e.target.value,
                                        })
                                    }
                                    className="rounded-md border border-input px-2 py-1.5"
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Quién lo recibe
                                <input
                                    value={salida.recibe_entrega}
                                    onChange={(e) =>
                                        setSalida({
                                            ...salida,
                                            recibe_entrega: e.target.value,
                                        })
                                    }
                                    maxLength={200}
                                    className="rounded-md border border-input px-2 py-1.5"
                                />
                            </label>
                        </div>
                        <label className="grid gap-1 text-sm">
                            Observaciones (trabajo realizado)
                            <textarea
                                value={salida.observaciones_entrega}
                                onChange={(e) =>
                                    setSalida({
                                        ...salida,
                                        observaciones_entrega: e.target.value,
                                    })
                                }
                                rows={3}
                                maxLength={2000}
                                className="rounded-md border border-input px-2 py-1.5"
                            />
                        </label>
                        {errores.map((er) => (
                            <p key={er} className="text-sm text-destructive">
                                {er}
                            </p>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEntregar(null)}
                                disabled={guardando}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={guardando}>
                                {guardando ? 'Guardando...' : 'Entregar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Mantenimientos.layout = { breadcrumbs };
