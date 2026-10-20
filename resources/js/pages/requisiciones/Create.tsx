import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import LineaPresupuestoCeldas from '@/components/linea-presupuesto-celdas';
import type { SeleccionLinea } from '@/components/linea-presupuesto-celdas';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
    { title: 'Crear', href: '/requisiciones/crear' },
];

interface Catalogs {
    unidades: {
        id_cat_egreso_unidad_administrativa: number;
        clave: string;
        nombre: string;
    }[];
    lineas: LineaPresupuesto[];
}

interface Fila {
    fuente: string;
    proyecto: string;
    partida: string;
    cantidad: string;
    descripcion: string;
}

export default function Create({
    catalogs,
    unidadDefault,
    periodoCaptura,
    mantenimiento,
}: {
    catalogs: Catalogs;
    unidadDefault?: number | null;
    /** Requisición de un mantenimiento vehicular: va a nombre de la secretaría de resguardo. */
    mantenimiento?: {
        id: number;
        folio: string;
        vehiculo: string;
        area: string | null;
        tipo: string;
        descripcion: string;
    } | null;
    periodoCaptura: { inicio: string; fin: string; abierto: boolean };
}) {
    const conceptoRef = useRef<HTMLTextAreaElement>(null);
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            id_cat_egreso_unidad_administrativa: String(unidadDefault ?? ''),
            concepto: mantenimiento
                ? `MANTENIMIENTO ${mantenimiento.tipo.toUpperCase()} DEL VEHÍCULO ${mantenimiento.vehiculo}${mantenimiento.area ? ` (${mantenimiento.area})` : ''}: ${mantenimiento.descripcion.toUpperCase()}`
                : '',
            mantenimiento_id: mantenimiento?.id ?? null,
            fecha_tramite: new Date().toISOString().slice(0, 10),
            incluye_iva: false,
            filas: [
                {
                    fuente: '',
                    proyecto: '',
                    partida: '',
                    cantidad: '1',
                    descripcion: '',
                },
            ] as Fila[],
        });

    const unidadClave = catalogs.unidades.find(
        (u) =>
            u.id_cat_egreso_unidad_administrativa ===
            Number(data.id_cat_egreso_unidad_administrativa),
    )?.clave;
    const lineasUnidad = catalogs.lineas.filter(
        (l) => l.unidad_clave === unidadClave,
    );

    const cambiarUnidad = (id: string) => {
        // Las líneas de presupuesto son de cada unidad: al cambiarla se limpian las ya elegidas.
        setData((prev) => ({
            ...prev,
            id_cat_egreso_unidad_administrativa: id,
            filas: prev.filas.map((f) => ({
                ...f,
                fuente: '',
                proyecto: '',
                partida: '',
            })),
        }));
    };

    const elegirLinea = (i: number, seleccion: SeleccionLinea) => {
        const filas = [...data.filas];
        filas[i] = { ...filas[i], ...seleccion };
        setData('filas', filas);
    };

    const agregarFila = () => {
        setData('filas', [
            ...data.filas,
            {
                fuente: '',
                proyecto: '',
                partida: '',
                cantidad: '1',
                descripcion: '',
            },
        ]);
    };

    const eliminarFila = (i: number) => {
        setData(
            'filas',
            data.filas.filter((_, idx) => idx !== i),
        );
    };

    const actualizarFila = (i: number, campo: keyof Fila, valor: string) => {
        const filas = [...data.filas];
        filas[i] = { ...filas[i], [campo]: valor };
        setData('filas', filas);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.concepto.trim()) {
            setError(
                'concepto',
                'El concepto es obligatorio: no se puede guardar vacío.',
            );
            conceptoRef.current?.focus();

            return;
        }

        post('/requisiciones');
    };

    return (
        <>
            <Head title="Nueva Requisición" />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/requisiciones">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">Nueva Requisición</h3>
                </div>

                {mantenimiento && (
                    <p className="mb-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                        <strong>Mantenimiento {mantenimiento.folio}:</strong>{' '}
                        {mantenimiento.vehiculo}. La requisición va a nombre de
                        la secretaría de resguardo del vehículo y sigue el
                        trámite normal.
                    </p>
                )}
                {periodoCaptura.abierto ? (
                    <p className="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900">
                        Periodo de captura abierto: del {periodoCaptura.inicio}{' '}
                        al {periodoCaptura.fin}.
                    </p>
                ) : (
                    <p
                        role="alert"
                        className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    >
                        <strong>Fuera del periodo de captura</strong> (del{' '}
                        {periodoCaptura.inicio} al {periodoCaptura.fin}). La
                        requisición quedará como <strong>extemporánea</strong> y
                        pasará a revisión en cuanto la autorice la Subdirección
                        Administrativa (Adquisiciones).
                    </p>
                )}
                <form onSubmit={submit} className="w-full space-y-4">
                    {/* Fecha */}
                    <div className="flex items-center gap-8">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold">
                                Fecha de trámite:
                            </span>
                            <input
                                type="date"
                                value={data.fecha_tramite}
                                onChange={(e) =>
                                    setData('fecha_tramite', e.target.value)
                                }
                                className="rounded-md border-input px-2 py-1 text-sm shadow-sm focus:border-ring focus:ring-ring"
                                required
                            />
                        </div>
                        {errors.fecha_tramite && (
                            <p className="text-sm text-destructive">
                                {errors.fecha_tramite}
                            </p>
                        )}
                    </div>

                    {/* Unidad Administrativa */}
                    <div className="flex items-center gap-4">
                        <span className="text-sm font-semibold whitespace-nowrap">
                            Dirección o Unidad administrativa:
                        </span>
                        <div className="w-full max-w-xl">
                            <SearchableSelect
                                value={String(
                                    data.id_cat_egreso_unidad_administrativa ??
                                        '',
                                )}
                                onChange={cambiarUnidad}
                                options={catalogs.unidades.map((u) => ({
                                    value: String(
                                        u.id_cat_egreso_unidad_administrativa,
                                    ),
                                    label: u.clave
                                        ? `${u.clave} - ${u.nombre}`
                                        : u.nombre,
                                }))}
                                placeholder="Escribe o elige la dirección o unidad…"
                                showValue={false}
                                panelWidth={520}
                            />
                        </div>
                    </div>

                    {/* Tabla de partidas */}
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50">
                                    <th
                                        className="p-2 text-left font-semibold"
                                        style={{ minWidth: 150 }}
                                    >
                                        Partida
                                    </th>
                                    <th
                                        className="p-2 text-left font-semibold"
                                        style={{ minWidth: 150 }}
                                    >
                                        Proyecto
                                    </th>
                                    <th
                                        className="p-2 text-left font-semibold"
                                        style={{ minWidth: 150 }}
                                    >
                                        Fuente
                                    </th>
                                    <th
                                        className="p-2 text-center font-semibold"
                                        style={{ width: 70 }}
                                    >
                                        Cant
                                    </th>
                                    <th
                                        className="p-2 text-left font-semibold"
                                        style={{ minWidth: 260 }}
                                    >
                                        Artículo / material / Servicio
                                    </th>
                                    <th
                                        className="p-2 text-center"
                                        style={{ width: 40 }}
                                    ></th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.filas.map((fila, i) => (
                                    <tr
                                        key={i}
                                        className="border-b align-top hover:bg-muted/20"
                                    >
                                        <LineaPresupuestoCeldas
                                            lineas={lineasUnidad}
                                            seleccion={fila}
                                            onChange={(sel) =>
                                                elegirLinea(i, sel)
                                            }
                                            error={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[`filas.${i}.partida`]
                                            }
                                            disabled={!unidadClave}
                                        />
                                        <td className="p-1">
                                            <input
                                                type="text"
                                                value={fila.cantidad}
                                                onChange={(e) =>
                                                    actualizarFila(
                                                        i,
                                                        'cantidad',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded border-input px-2 py-1.5 text-center text-xs shadow-sm focus:border-ring focus:ring-ring"
                                            />
                                        </td>
                                        <td className="p-1">
                                            <textarea
                                                value={fila.descripcion}
                                                onChange={(e) =>
                                                    actualizarFila(
                                                        i,
                                                        'descripcion',
                                                        e.target.value,
                                                    )
                                                }
                                                className="min-h-[40px] w-full rounded border-input px-2 py-1.5 text-xs shadow-sm focus:border-ring focus:ring-ring"
                                                rows={2}
                                            />
                                        </td>
                                        <td className="p-1 text-center">
                                            {data.filas.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        eliminarFila(i)
                                                    }
                                                    className="text-destructive hover:text-destructive/80"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="border-t p-2">
                            <button
                                type="button"
                                onClick={agregarFila}
                                className="inline-flex items-center gap-1 text-xs text-primary hover:text-primary/80"
                            >
                                <Plus className="h-3 w-3" /> Agregar fila
                            </button>
                        </div>
                    </div>

                    {/* Concepto */}
                    <div>
                        <span className="text-sm font-semibold">
                            CONCEPTO:{' '}
                            <span className="text-destructive">*</span>
                        </span>
                        <textarea
                            ref={conceptoRef}
                            value={data.concepto}
                            onChange={(e) => {
                                setData('concepto', e.target.value);

                                if (e.target.value.trim()) {
                                    clearErrors('concepto');
                                }
                            }}
                            aria-invalid={!!errors.concepto}
                            className={`mt-1 block min-h-[60px] w-full rounded-md border-input px-3 py-2 text-sm shadow-sm focus:border-ring focus:ring-ring ${errors.concepto ? 'border-2 border-destructive' : ''}`}
                            placeholder="Descripción del concepto..."
                        />
                        {errors.concepto && (
                            <p
                                role="alert"
                                className="mt-1 rounded-md bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive"
                            >
                                ⚠ {errors.concepto}
                            </p>
                        )}
                    </div>

                    {/* Botones */}
                    <div className="flex items-center gap-4 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando...' : 'Crear Requisición'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/requisiciones">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Create.layout = { breadcrumbs };
