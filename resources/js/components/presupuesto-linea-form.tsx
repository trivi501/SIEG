import { Link, useForm } from '@inertiajs/react';
import { Divide } from 'lucide-react';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/presupuesto';
import { cn } from '@/lib/utils';

export const MESES = [
    { key: 'ENERO', label: 'Enero' },
    { key: 'FEBRERO', label: 'Febrero' },
    { key: 'MARZO', label: 'Marzo' },
    { key: 'ABRIL', label: 'Abril' },
    { key: 'MAYO', label: 'Mayo' },
    { key: 'JUNIO', label: 'Junio' },
    { key: 'JULIO', label: 'Julio' },
    { key: 'AGOSTO', label: 'Agosto' },
    { key: 'SEP', label: 'Septiembre' },
    { key: 'OCTUBRE', label: 'Octubre' },
    { key: 'NOV', label: 'Noviembre' },
    { key: 'DIC', label: 'Diciembre' },
] as const;

type Mes = (typeof MESES)[number]['key'];

export type LineaDatos = {
    CLAVE: string;
    CLAVE3: string;
    CLAVE6: string;
    PARTIDA: string;
    IMPORTE_TOTAL: string;
} & Record<Mes, string>;

export interface CatalogosPresupuesto {
    unidades: { clave: string; nombre: string }[];
    fuentes: { clave: string; nombre: string }[];
    proyectos: { clave: string; nombre: string }[];
    partidas: { clave: string; nombre: string; tipo_gasto: number }[];
}

/** Importes en centavos enteros: así se compara igual que en el servidor, sin errores de redondeo. */
const centavos = (v: string | number | null | undefined) =>
    Math.round((parseFloat(String(v ?? '')) || 0) * 100);

export const lineaVacia = (): LineaDatos => ({
    CLAVE: '',
    CLAVE3: '',
    CLAVE6: '',
    PARTIDA: '',
    IMPORTE_TOTAL: '',
    ...(Object.fromEntries(MESES.map((m) => [m.key, ''])) as Record<
        Mes,
        string
    >),
});

/**
 * Alta y edición de una línea del presupuesto. La distribución mensual debe sumar exactamente el
 * importe total: el botón Guardar se habilita solo cuando la diferencia es cero (el servidor lo
 * vuelve a validar).
 */
export default function PresupuestoLineaForm({
    inicial,
    catalogos,
    metodo,
    url,
    textoBoton,
}: {
    inicial: LineaDatos;
    catalogos: CatalogosPresupuesto;
    metodo: 'post' | 'patch';
    url: string;
    textoBoton: string;
}) {
    const form = useForm<LineaDatos>(inicial);
    const errores = form.errors as Partial<Record<string, string>>;

    const total = centavos(form.data.IMPORTE_TOTAL);
    const distribuido = MESES.reduce(
        (suma, m) => suma + centavos(form.data[m.key]),
        0,
    );
    const diferencia = total - distribuido;
    // Se puede dar de alta en 0: basta con capturar el importe y que los meses cuadren.
    const capturado = String(form.data.IMPORTE_TOTAL ?? '').trim() !== '';
    const cuadra = capturado && diferencia === 0;

    const partida = catalogos.partidas.find(
        (p) => p.clave === form.data.PARTIDA,
    );

    // Reparte el total en 12 meses iguales; los centavos que sobran van a los primeros meses.
    const repartir = () => {
        const base = Math.floor(total / 12);
        const resto = total - base * 12;

        form.setData({
            ...form.data,
            ...(Object.fromEntries(
                MESES.map((m, i) => [
                    m.key,
                    ((base + (i < resto ? 1 : 0)) / 100).toFixed(2),
                ]),
            ) as Record<Mes, string>),
        });
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form[metodo](url, { preserveScroll: true });
    };

    const selector = (
        campo: 'CLAVE' | 'CLAVE3' | 'CLAVE6' | 'PARTIDA',
        etiqueta: string,
        opciones: { clave: string; nombre: string }[],
    ) => (
        <div className="grid gap-2">
            <Label>
                {etiqueta} <span className="text-destructive">*</span>
            </Label>
            <SearchableSelect
                value={form.data[campo]}
                onChange={(v) => form.setData(campo, v)}
                options={opciones.map((o) => ({
                    value: o.clave,
                    label: o.nombre,
                }))}
                panelWidth={420}
                placeholder={
                    opciones.length === 0 ? 'Catálogo vacío' : 'Seleccionar...'
                }
            />
            {errores[campo] && (
                <p className="text-sm text-destructive">{errores[campo]}</p>
            )}
        </div>
    );

    return (
        <form onSubmit={guardar} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Línea presupuestal</CardTitle>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {selector(
                        'CLAVE',
                        'Secretaría o dirección',
                        catalogos.unidades,
                    )}
                    {selector(
                        'CLAVE3',
                        'Fuente de financiamiento',
                        catalogos.fuentes,
                    )}
                    {selector('CLAVE6', 'Proyecto', catalogos.proyectos)}
                    {selector(
                        'PARTIDA',
                        'Partida (objeto del gasto)',
                        catalogos.partidas,
                    )}
                    {partida && (
                        <p className="text-xs text-muted-foreground md:col-span-2">
                            Capítulo {partida.clave.charAt(0)}000 · tipo de
                            gasto {partida.tipo_gasto}. Los nombres, el capítulo
                            y el tipo de gasto se toman de los catálogos.
                        </p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Importe y distribución mensual</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="IMPORTE_TOTAL">
                                Importe total anual{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="IMPORTE_TOTAL"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.IMPORTE_TOTAL}
                                onChange={(e) =>
                                    form.setData(
                                        'IMPORTE_TOTAL',
                                        e.target.value,
                                    )
                                }
                                className="w-56"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={total <= 0}
                            onClick={repartir}
                        >
                            <Divide className="mr-2 h-4 w-4" /> Repartir en 12
                            meses iguales
                        </Button>
                    </div>
                    {errores.IMPORTE_TOTAL && (
                        <p className="text-sm text-destructive">
                            {errores.IMPORTE_TOTAL}
                        </p>
                    )}

                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        {MESES.map(({ key, label }) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                                {errores[key] && (
                                    <p className="text-xs text-destructive">
                                        {errores[key]}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>

                    <div
                        className={cn(
                            'flex flex-wrap gap-x-6 gap-y-1 rounded-md border p-3 text-sm',
                            cuadra
                                ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300'
                                : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
                        )}
                    >
                        <span>
                            Importe total: <strong>{money(total / 100)}</strong>
                        </span>
                        <span>
                            Distribuido:{' '}
                            <strong>{money(distribuido / 100)}</strong>
                        </span>
                        <span>
                            {diferencia === 0
                                ? capturado
                                    ? 'La distribución cuadra.'
                                    : 'Captura el importe total.'
                                : diferencia > 0
                                  ? `Faltan ${money(diferencia / 100)} por distribuir.`
                                  : `Sobran ${money(-diferencia / 100)}: la distribución pasa del total.`}
                        </span>
                    </div>
                    {errores.distribucion && (
                        <p className="text-sm text-destructive">
                            {errores.distribucion}
                        </p>
                    )}
                </CardContent>
            </Card>

            <div className="flex items-center gap-4">
                <Button type="submit" disabled={form.processing || !cuadra}>
                    {form.processing ? 'Guardando...' : textoBoton}
                </Button>
                <Button variant="outline" asChild>
                    <Link href="/presupuesto">Cancelar</Link>
                </Button>
                {!cuadra && capturado && (
                    <span className="text-sm text-muted-foreground">
                        Para guardar, los meses deben sumar exactamente el
                        importe total.
                    </span>
                )}
            </div>
        </form>
    );
}
