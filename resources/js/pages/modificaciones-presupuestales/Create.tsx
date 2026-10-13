import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AvisoCapturaModificaciones from '@/components/aviso-captura-modificaciones';
import type { CapturaModificaciones } from '@/components/aviso-captura-modificaciones';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { etiquetaLinea, money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Modificaciones Presupuestales',
        href: '/modificaciones-presupuestales',
    },
    { title: 'Nueva', href: '#' },
];

const descripcionTipo: Record<string, string> = {
    traspaso: 'Se le quita a una partida y se le da a otra.',
    reduccion:
        'Solo disminuye una partida (p. ej. no se alcanzó la meta de la Ley de Ingresos).',
    ampliacion: 'Solo aumenta una partida (ingreso adicional).',
};

function Saldo({
    linea,
    signo,
    importe,
    disponibleMes,
    nombreMes,
}: {
    linea?: Required<LineaPresupuesto>;
    signo: 1 | -1;
    importe: number;
    disponibleMes?: number;
    nombreMes?: string;
}) {
    if (!linea) {
        return null;
    }

    const actual = disponibleMes ?? 0;
    const despues = actual + signo * importe;
    const sinSuficiencia = signo === -1 && despues < 0;

    return (
        <div
            className={`mt-2 grid grid-cols-2 gap-2 rounded-md p-2 text-xs sm:grid-cols-4 ${sinSuficiencia ? 'border border-destructive/40 bg-destructive/5' : 'bg-muted/40'}`}
        >
            <div>
                <span className="text-muted-foreground">Fuente:</span>{' '}
                {linea.fuente}{' '}
                <span className="text-muted-foreground">
                    {linea.nombre_fuente}
                </span>
            </div>
            <div>
                <span className="text-muted-foreground">Periodo:</span>{' '}
                <span className="capitalize">{nombreMes}</span>
            </div>
            <div>
                <span className="text-muted-foreground">
                    Disponible del periodo:
                </span>{' '}
                <span className={actual < 0 ? 'text-destructive' : ''}>
                    {money(actual)}
                </span>
            </div>
            <div>
                <span className="text-muted-foreground">
                    {signo === -1 ? 'Le queda:' : 'Queda con:'}
                </span>{' '}
                <span
                    className={
                        despues < 0
                            ? 'font-semibold text-destructive'
                            : 'font-semibold'
                    }
                >
                    {money(despues)}
                </span>
            </div>
            {sinSuficiencia && (
                <div className="col-span-full font-medium text-destructive">
                    Sin suficiencia: el emisor solo tiene {money(actual)}{' '}
                    disponibles en{' '}
                    <span className="capitalize">{nombreMes}</span>; faltan{' '}
                    {money(-despues)}.
                </div>
            )}
        </div>
    );
}
export default function Create({
    lineas,
    tipos,
    requisicion,
    destinoSugerido,
    importeSugerido,
    meses,
    mesActual,
    disponiblesMes,
    captura,
}: {
    captura: CapturaModificaciones;
    meses: Record<string, string>;
    mesActual: number;
    disponiblesMes: Record<string, Record<string, number>>;
    lineas: Required<LineaPresupuesto>[];
    tipos: Record<string, string>;
    requisicion: {
        id_tb_egreso_requisicion: number;
        folio_completo: string;
    } | null;
    destinoSugerido: string | null;
    importeSugerido: number | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        tipo: 'traspaso',
        origen: '',
        origen_mes: String(mesActual),
        destino: destinoSugerido ?? '',
        destino_mes: String(mesActual),
        importe: importeSugerido ? String(importeSugerido) : '',
        justificacion: requisicion
            ? `Para dar suficiencia a la requisición ${requisicion.folio_completo}.`
            : '',
        id_tb_egreso_requisicion: requisicion?.id_tb_egreso_requisicion ?? null,
    });

    const usaOrigen = data.tipo === 'traspaso' || data.tipo === 'reduccion';
    const usaDestino = data.tipo === 'traspaso' || data.tipo === 'ampliacion';
    const multiUnidad = new Set(lineas.map((l) => l.unidad_clave)).size > 1;

    const disponibleEn = (clave: string, mes: string) =>
        disponiblesMes[clave]?.[mes] ?? 0;
    const opcion = (l: Required<LineaPresupuesto>, mes: string) => ({
        value: l.clave,
        label: `${multiUnidad ? `${l.unidad} · ` : ''}${etiquetaLinea(l)}`,
        hint: `Disp. ${meses[mes] ?? ''} ${money(disponibleEn(l.clave, mes))}`,
    });

    const origen = lineas.find((l) => l.clave === data.origen);
    const destino = lineas.find((l) => l.clave === data.destino);
    const importe = parseFloat(data.importe) || 0;

    // Traspaso: emisor y receptor de la misma fuente de financiamiento.
    const esTraspaso = data.tipo === 'traspaso';
    const opcionesOrigen = lineas
        .filter(
            (l) =>
                disponibleEn(l.clave, data.origen_mes) > 0 &&
                l.clave !== data.destino &&
                (!esTraspaso ||
                    !requisicion ||
                    !destino ||
                    l.fuente === destino.fuente),
        )
        .map((l) => opcion(l, data.origen_mes));
    const opcionesDestino = lineas
        .filter(
            (l) =>
                l.clave !== data.origen &&
                (!esTraspaso || !origen || l.fuente === origen.fuente),
        )
        .map((l) => opcion(l, data.destino_mes));

    const cambiarOrigen = (clave: string) => {
        const nuevo = lineas.find((l) => l.clave === clave);
        setData((d) => ({
            ...d,
            origen: clave,
            destino:
                esTraspaso &&
                nuevo &&
                destino &&
                destino.fuente !== nuevo.fuente
                    ? ''
                    : d.destino,
        }));
    };

    const sinSuficiencia =
        usaOrigen &&
        !!origen &&
        importe > disponibleEn(origen.clave, data.origen_mes);

    const selectorMes = (
        campo: 'origen_mes' | 'destino_mes',
        etiqueta: string,
    ) => (
        <div className="mt-2 flex items-center gap-2">
            <label className="text-sm font-medium" htmlFor={campo}>
                {etiqueta}
            </label>
            <select
                id={campo}
                value={data[campo]}
                onChange={(e) => setData(campo, e.target.value)}
                className="rounded-md border border-input bg-transparent px-2 py-1 text-sm capitalize"
            >
                {Object.entries(meses).map(([numero, nombre]) => (
                    <option key={numero} value={numero} className="capitalize">
                        {nombre}
                    </option>
                ))}
            </select>
            {errors[campo] && (
                <p className="text-sm text-destructive">{errors[campo]}</p>
            )}
        </div>
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/modificaciones-presupuestales');
    };

    return (
        <>
            <Head title="Nueva modificación presupuestal" />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/modificaciones-presupuestales">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">
                        Nueva modificación presupuestal
                    </h3>
                </div>

                <form onSubmit={submit} className="max-w-4xl space-y-6">
                    <AvisoCapturaModificaciones captura={captura} />
                    {requisicion && (
                        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            Relacionada con la requisición{' '}
                            <strong>{requisicion.folio_completo}</strong>, que
                            no tuvo suficiencia.
                            {destinoSugerido &&
                                ' Se propone como destino la partida que no alcanzó y como importe lo que le falta.'}
                        </div>
                    )}

                    {errors.tipo && (
                        <p className="text-sm text-destructive">
                            {errors.tipo}
                        </p>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Tipo de modificación</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-3">
                            {Object.entries(tipos).map(([valor, nombre]) => (
                                <label
                                    key={valor}
                                    className={`cursor-pointer rounded-md border p-3 text-sm ${data.tipo === valor ? 'border-primary bg-primary/5' : ''}`}
                                >
                                    <div className="flex items-center gap-2 font-medium">
                                        <input
                                            type="radio"
                                            name="tipo"
                                            value={valor}
                                            checked={data.tipo === valor}
                                            onChange={() =>
                                                setData('tipo', valor)
                                            }
                                        />
                                        {nombre}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {descripcionTipo[valor]}
                                    </p>
                                </label>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Emisor y receptor</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {usaOrigen && (
                                <div>
                                    <label className="text-sm font-medium">
                                        Emisor / origen (de donde sale el
                                        recurso)
                                    </label>
                                    <SearchableSelect
                                        value={data.origen}
                                        onChange={cambiarOrigen}
                                        options={opcionesOrigen}
                                        showValue={false}
                                        panelWidth={760}
                                    />
                                    {errors.origen && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {errors.origen}
                                        </p>
                                    )}
                                    {selectorMes(
                                        'origen_mes',
                                        'Periodo del que sale:',
                                    )}
                                    <Saldo
                                        linea={origen}
                                        signo={-1}
                                        importe={importe}
                                        disponibleMes={
                                            origen
                                                ? disponiblesMes[
                                                      origen.clave
                                                  ]?.[data.origen_mes]
                                                : undefined
                                        }
                                        nombreMes={meses[data.origen_mes]}
                                    />
                                </div>
                            )}
                            {usaDestino && (
                                <div>
                                    <label className="text-sm font-medium">
                                        Receptor / destino (la que recibe el
                                        recurso)
                                    </label>
                                    <SearchableSelect
                                        value={data.destino}
                                        onChange={(v) => setData('destino', v)}
                                        options={opcionesDestino}
                                        showValue={false}
                                        panelWidth={760}
                                    />
                                    {errors.destino && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {errors.destino}
                                        </p>
                                    )}
                                    {esTraspaso && origen && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Solo partidas de la fuente{' '}
                                            {origen.fuente}{' '}
                                            {origen.nombre_fuente}.
                                        </p>
                                    )}
                                    {selectorMes(
                                        'destino_mes',
                                        'Periodo al que entra:',
                                    )}
                                    <Saldo
                                        linea={destino}
                                        signo={1}
                                        importe={importe}
                                        disponibleMes={
                                            destino
                                                ? disponiblesMes[
                                                      destino.clave
                                                  ]?.[data.destino_mes]
                                                : undefined
                                        }
                                        nombreMes={meses[data.destino_mes]}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Importe y justificación</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1">
                                <label className="text-sm font-medium">
                                    Importe
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={data.importe}
                                    onChange={(e) =>
                                        setData('importe', e.target.value)
                                    }
                                    className="block w-48 rounded-md border-input px-3 py-2 text-sm shadow-sm"
                                    required
                                />
                                {errors.importe && (
                                    <p className="text-sm text-destructive">
                                        {errors.importe}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <label className="text-sm font-medium">
                                    Justificación
                                </label>
                                <textarea
                                    value={data.justificacion}
                                    onChange={(e) =>
                                        setData('justificacion', e.target.value)
                                    }
                                    className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm"
                                    rows={4}
                                    required
                                />
                                {errors.justificacion && (
                                    <p className="text-sm text-destructive">
                                        {errors.justificacion}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={
                                processing || sinSuficiencia || !captura.abierta
                            }
                            title={
                                !captura.abierta
                                    ? 'La captura de este mes está cerrada'
                                    : sinSuficiencia
                                      ? 'El emisor no tiene suficiencia en el periodo'
                                      : undefined
                            }
                        >
                            {processing
                                ? 'Guardando...'
                                : 'Registrar solicitud'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/modificaciones-presupuestales">
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Create.layout = { breadcrumbs };
