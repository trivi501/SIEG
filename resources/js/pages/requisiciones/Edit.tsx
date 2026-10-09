import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import LineaPresupuestoCeldas from '@/components/linea-presupuesto-celdas';
import type { SeleccionLinea } from '@/components/linea-presupuesto-celdas';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
    { title: 'Editar', href: '#' },
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
    precio_unitario: string;
    descripcion: string;
}

export default function Edit({
    requisicion,
    catalogs,
}: {
    requisicion: any;
    catalogs: Catalogs;
}) {
    const esCotizacion = requisicion.id_cat_egreso_requisicion_estado === 6;

    const detalles = requisicion.tb_egreso_requisicion_detalles ?? [];
    const filasIniciales: Fila[] =
        detalles.length > 0
            ? detalles.map((d: any) => ({
                  fuente: d.fuente ?? '',
                  proyecto: d.proyecto ?? '',
                  partida: d.partida ?? '',
                  cantidad: parseFloat(d.cantidad || 0).toString(),
                  precio_unitario: d.precio_unitario
                      ? parseFloat(d.precio_unitario).toString()
                      : '',
                  descripcion: d.descripcion ?? '',
              }))
            : [
                  {
                      fuente: '',
                      proyecto: '',
                      partida: '',
                      cantidad: '1',
                      precio_unitario: '',
                      descripcion: '',
                  },
              ];

    const incluyeIvaInicial = detalles.some(
        (d: any) => parseFloat(d.iva_factor || 0) > 0,
    );

    const { data, setData, put, processing, errors } = useForm({
        id_cat_egreso_unidad_administrativa: String(
            requisicion.id_cat_egreso_unidad_administrativa ?? '',
        ),
        concepto: requisicion.observaciones ?? '',
        fecha_tramite: requisicion.solicitado
            ? String(requisicion.solicitado).slice(0, 10)
            : new Date().toISOString().slice(0, 10),
        incluye_iva: incluyeIvaInicial,
        filas: filasIniciales,
    });

    const agregarFila = () => {
        setData('filas', [
            ...data.filas,
            {
                fuente: '',
                proyecto: '',
                partida: '',
                cantidad: '1',
                precio_unitario: '',
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
    const elegirLinea = (i: number, seleccion: SeleccionLinea) => {
        const filas = [...data.filas];
        filas[i] = { ...filas[i], ...seleccion };
        setData('filas', filas);
    };
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

    const unidad = catalogs.unidades.find(
        (u) =>
            u.id_cat_egreso_unidad_administrativa ===
            Number(data.id_cat_egreso_unidad_administrativa),
    );
    const lineasUnidad = catalogs.lineas.filter(
        (l) => l.unidad_clave === unidad?.clave,
    );

    const errores = errors as Record<string, string>;
    const totalCotizado = data.filas.reduce((acc, f) => {
        const sub =
            (parseFloat(f.precio_unitario) || 0) *
            (parseFloat(f.cantidad) || 0);

        return acc + sub * (data.incluye_iva ? 1.16 : 1);
    }, 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/requisiciones/${requisicion.id_tb_egreso_requisicion}`);
    };

    return (
        <>
            <Head title={`Editar ${requisicion.folio_completo}`} />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={`/requisiciones/${requisicion.id_tb_egreso_requisicion}`}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">
                        Editar {requisicion.folio_completo}
                    </h3>
                </div>

                {esCotizacion && (
                    <div className="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                        Modo cotización — Solo puedes editar el{' '}
                        <strong>Precio Unitario</strong> y si lleva IVA. Al
                        guardar, la suficiencia presupuestal se tiene que volver
                        a validar.
                    </div>
                )}

                <form onSubmit={submit} className="w-full space-y-4">
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
                                className="rounded-md border-input px-2 py-1 text-sm shadow-sm"
                                required
                            />
                        </div>
                        {errors.fecha_tramite && (
                            <p className="text-sm text-destructive">
                                {errors.fecha_tramite}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-4">
                        <span className="text-sm font-semibold whitespace-nowrap">
                            Dirección o Unidad administrativa:
                        </span>
                        {esCotizacion ? (
                            <span className="text-sm text-muted-foreground">
                                {unidad?.nombre ?? '—'}
                            </span>
                        ) : (
                            <select
                                value={data.id_cat_egreso_unidad_administrativa}
                                onChange={(e) => cambiarUnidad(e.target.value)}
                                className="block w-full max-w-xl rounded-md border-input px-3 py-2 text-sm shadow-sm"
                            >
                                <option value="">Seleccionar...</option>
                                {catalogs.unidades.map((u) => (
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
                        )}
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50">
                                    {['Partida', 'Proyecto', 'Fuente'].map(
                                        (titulo) => (
                                            <th
                                                key={titulo}
                                                className="p-2 text-left font-semibold"
                                                style={{ minWidth: 150 }}
                                            >
                                                {titulo}
                                            </th>
                                        ),
                                    )}
                                    <th
                                        className="p-2 text-center font-semibold"
                                        style={{ width: 70 }}
                                    >
                                        Cant
                                    </th>
                                    <th
                                        className="p-2 text-center font-semibold"
                                        style={{ width: 100 }}
                                    >
                                        Precio Unit.
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
                                                errores[`filas.${i}.partida`]
                                            }
                                            disabled={!unidad}
                                            soloLectura={esCotizacion}
                                        />
                                        <td className="p-1">
                                            {esCotizacion ? (
                                                <span className="block px-2 py-1.5 text-center text-xs text-muted-foreground">
                                                    {fila.cantidad}
                                                </span>
                                            ) : (
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
                                                    className="w-full rounded border-input px-2 py-1.5 text-center text-xs shadow-sm"
                                                />
                                            )}
                                        </td>
                                        <td className="p-1">
                                            {esCotizacion ? (
                                                <input
                                                    type="text"
                                                    value={fila.precio_unitario}
                                                    onChange={(e) =>
                                                        actualizarFila(
                                                            i,
                                                            'precio_unitario',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded border-input px-2 py-1.5 text-center text-xs shadow-sm"
                                                    placeholder="0.00"
                                                />
                                            ) : (
                                                <span className="block px-2 py-1.5 text-center text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-1">
                                            {esCotizacion ? (
                                                <span className="block px-2 py-1.5 text-xs text-muted-foreground">
                                                    {fila.descripcion}
                                                </span>
                                            ) : (
                                                <textarea
                                                    value={fila.descripcion}
                                                    onChange={(e) =>
                                                        actualizarFila(
                                                            i,
                                                            'descripcion',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="min-h-[40px] w-full rounded border-input px-2 py-1.5 text-xs shadow-sm"
                                                    rows={2}
                                                />
                                            )}
                                        </td>
                                        <td className="p-1 text-center">
                                            {!esCotizacion &&
                                                data.filas.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            eliminarFila(i)
                                                        }
                                                        className="text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!esCotizacion && (
                            <div className="border-t p-2">
                                <button
                                    type="button"
                                    onClick={agregarFila}
                                    className="inline-flex items-center gap-1 text-xs text-primary"
                                >
                                    <Plus className="h-3 w-3" /> Agregar fila
                                </button>
                            </div>
                        )}
                    </div>

                    <div>
                        <span className="text-sm font-semibold">CONCEPTO:</span>
                        {esCotizacion ? (
                            <p className="mt-1 text-sm whitespace-pre-wrap text-muted-foreground">
                                {data.concepto || '—'}
                            </p>
                        ) : (
                            <textarea
                                value={data.concepto}
                                onChange={(e) =>
                                    setData('concepto', e.target.value)
                                }
                                className="mt-1 block min-h-[60px] w-full rounded-md border-input px-3 py-2 text-sm shadow-sm"
                            />
                        )}
                    </div>

                    {esCotizacion && (
                        <div className="flex items-center gap-8">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.incluye_iva}
                                    onChange={(e) =>
                                        setData('incluye_iva', e.target.checked)
                                    }
                                    className="rounded border-input"
                                />
                                Lleva IVA
                            </label>
                            <span className="text-sm">
                                <span className="font-semibold">
                                    Monto total del trámite:
                                </span>{' '}
                                {money(totalCotizado)}
                            </span>
                        </div>
                    )}

                    <div className="flex items-center gap-4 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando...' : 'Actualizar'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={`/requisiciones/${requisicion.id_tb_egreso_requisicion}`}
                            >
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Edit.layout = { breadcrumbs };
