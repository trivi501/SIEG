import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
}: {
    linea?: Required<LineaPresupuesto>;
    signo: 1 | -1;
    importe: number;
}) {
    if (!linea) {
        return null;
    }

    const despues = linea.disponible + signo * importe;

    return (
        <div className="mt-2 grid grid-cols-3 gap-2 rounded-md bg-muted/40 p-2 text-xs">
            <div>
                <span className="text-muted-foreground">Vigente:</span>{' '}
                {money(linea.vigente)}
            </div>
            <div>
                <span className="text-muted-foreground">Disponible:</span>{' '}
                {money(linea.disponible)}
            </div>
            <div>
                <span className="text-muted-foreground">
                    Disponible después:
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
        </div>
    );
}

export default function Create({
    lineas,
    tipos,
    requisicion,
    destinoSugerido,
    importeSugerido,
}: {
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
        destino: destinoSugerido ?? '',
        importe: importeSugerido ? String(importeSugerido) : '',
        justificacion: requisicion
            ? `Para dar suficiencia a la requisición ${requisicion.folio_completo}.`
            : '',
        id_tb_egreso_requisicion: requisicion?.id_tb_egreso_requisicion ?? null,
    });

    const usaOrigen = data.tipo === 'traspaso' || data.tipo === 'reduccion';
    const usaDestino = data.tipo === 'traspaso' || data.tipo === 'ampliacion';
    const multiUnidad = new Set(lineas.map((l) => l.unidad_clave)).size > 1;

    const opcion = (l: Required<LineaPresupuesto>) => ({
        value: l.clave,
        label: `${multiUnidad ? `${l.unidad} · ` : ''}${etiquetaLinea(l)}`,
        hint: `Disp. ${money(l.disponible)}`,
    });
    const opcionesOrigen = lineas
        .filter((l) => l.disponible > 0 && l.clave !== data.destino)
        .map(opcion);
    const opcionesDestino = lineas
        .filter((l) => l.clave !== data.origen)
        .map(opcion);

    const origen = lineas.find((l) => l.clave === data.origen);
    const destino = lineas.find((l) => l.clave === data.destino);
    const importe = parseFloat(data.importe) || 0;

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
                    {requisicion && (
                        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            Relacionada con la requisición{' '}
                            <strong>{requisicion.folio_completo}</strong>, que
                            no tuvo suficiencia.
                            {destinoSugerido &&
                                ' Se propone como destino la partida que no alcanzó y como importe lo que le falta.'}
                        </div>
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
                            <CardTitle>Partidas</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {usaOrigen && (
                                <div>
                                    <label className="text-sm font-medium">
                                        Origen (de donde sale el recurso)
                                    </label>
                                    <SearchableSelect
                                        value={data.origen}
                                        onChange={(v) => setData('origen', v)}
                                        options={opcionesOrigen}
                                        showValue={false}
                                        panelWidth={760}
                                    />
                                    {errors.origen && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {errors.origen}
                                        </p>
                                    )}
                                    <Saldo
                                        linea={origen}
                                        signo={-1}
                                        importe={importe}
                                    />
                                </div>
                            )}
                            {usaDestino && (
                                <div>
                                    <label className="text-sm font-medium">
                                        Destino (la que recibe el recurso)
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
                                    <Saldo
                                        linea={destino}
                                        signo={1}
                                        importe={importe}
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
                        <Button type="submit" disabled={processing}>
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
