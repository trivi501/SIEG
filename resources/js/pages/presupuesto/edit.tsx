import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { PresupuestoEgreso } from '@/types';

const MONTH_KEYS = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'] as const;

function sumMonths(data: Record<string, string>): string {
    const total = MONTH_KEYS.reduce((sum, key) => sum + (parseFloat(data[key]) || 0), 0);
    return total.toFixed(2);
}

export default function Edit({ presupuesto }: { presupuesto: PresupuestoEgreso }) {
    const { data, setData, patch, processing, errors } = useForm({
        CLAVE: presupuesto.CLAVE ?? '',
        UNIDAD_ADMINISTRATIVA: presupuesto.UNIDAD_ADMINISTRATIVA ?? '',
        CLAVE3: presupuesto.CLAVE3 ?? '',
        FUENTE_DE_FINANCIAMIENTO: presupuesto.FUENTE_DE_FINANCIAMIENTO ?? '',
        CLAVE6: presupuesto.CLAVE6 ?? '',
        PROYECTO: presupuesto.PROYECTO ?? '',
        PARTIDA: presupuesto.PARTIDA ?? '',
        capitulo: presupuesto.capitulo ?? '',
        TIPO_DE_GASTO: presupuesto.TIPO_DE_GASTO ?? '',
        NOMBRE_PARTIDA: presupuesto.NOMBRE_PARTIDA ?? '',
        IMPORTE_TOTAL: presupuesto.IMPORTE_TOTAL?.toString() ?? '',
        ENERO: presupuesto.ENERO?.toString() ?? '',
        FEBRERO: presupuesto.FEBRERO?.toString() ?? '',
        MARZO: presupuesto.MARZO?.toString() ?? '',
        ABRIL: presupuesto.ABRIL?.toString() ?? '',
        MAYO: presupuesto.MAYO?.toString() ?? '',
        JUNIO: presupuesto.JUNIO?.toString() ?? '',
        JULIO: presupuesto.JULIO?.toString() ?? '',
        AGOSTO: presupuesto.AGOSTO?.toString() ?? '',
        SEP: presupuesto.SEP?.toString() ?? '',
        OCTUBRE: presupuesto.OCTUBRE?.toString() ?? '',
        NOV: presupuesto.NOV?.toString() ?? '',
        DIC: presupuesto.DIC?.toString() ?? '',
    });

    const handleMonthChange = (key: string, value: string) => {
        const newData = { ...data, [key]: value };
        newData.IMPORTE_TOTAL = sumMonths(newData);
        setData(newData as any);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/presupuesto/${presupuesto.id_presupuesto}`);
    };

    const months = [
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

    return (
        <>
            <Head title="Editar Registro" />
            <div className="space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/presupuesto">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading variant="small" title="Editar Registro" description="Modifique los datos del registro presupuestal" />
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Datos Generales</CardTitle></CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="CLAVE">Clave</Label>
                                <Input id="CLAVE" value={data.CLAVE} onChange={(e) => setData('CLAVE', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="CLAVE3">Clave 3</Label>
                                <Input id="CLAVE3" value={data.CLAVE3} onChange={(e) => setData('CLAVE3', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="CLAVE6">Clave 6</Label>
                                <Input id="CLAVE6" value={data.CLAVE6} onChange={(e) => setData('CLAVE6', e.target.value)} />
                            </div>
                            <div className="grid gap-2 md:col-span-3">
                                <Label htmlFor="UNIDAD_ADMINISTRATIVA">Unidad Administrativa</Label>
                                <Input id="UNIDAD_ADMINISTRATIVA" value={data.UNIDAD_ADMINISTRATIVA} onChange={(e) => setData('UNIDAD_ADMINISTRATIVA', e.target.value)} />
                            </div>
                            <div className="grid gap-2 md:col-span-3">
                                <Label htmlFor="FUENTE_DE_FINANCIAMIENTO">Fuente de Financiamiento</Label>
                                <Input id="FUENTE_DE_FINANCIAMIENTO" value={data.FUENTE_DE_FINANCIAMIENTO} onChange={(e) => setData('FUENTE_DE_FINANCIAMIENTO', e.target.value)} />
                            </div>
                            <div className="grid gap-2 md:col-span-3">
                                <Label htmlFor="PROYECTO">Proyecto</Label>
                                <Input id="PROYECTO" value={data.PROYECTO} onChange={(e) => setData('PROYECTO', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="PARTIDA">Partida</Label>
                                <Input id="PARTIDA" value={data.PARTIDA} onChange={(e) => setData('PARTIDA', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="capitulo">Capítulo</Label>
                                <Input id="capitulo" value={data.capitulo} onChange={(e) => setData('capitulo', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="TIPO_DE_GASTO">Tipo de Gasto</Label>
                                <Input id="TIPO_DE_GASTO" value={data.TIPO_DE_GASTO} onChange={(e) => setData('TIPO_DE_GASTO', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="IMPORTE_TOTAL">Importe Total</Label>
                                <Input id="IMPORTE_TOTAL" type="number" step="0.01" value={data.IMPORTE_TOTAL} readOnly className="bg-muted" />
                            </div>
                            <div className="grid gap-2 md:col-span-3">
                                <Label htmlFor="NOMBRE_PARTIDA">Nombre de Partida</Label>
                                <Input id="NOMBRE_PARTIDA" value={data.NOMBRE_PARTIDA} onChange={(e) => setData('NOMBRE_PARTIDA', e.target.value)} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Distribución Mensual</CardTitle></CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            {months.map(({ key, label }) => (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        type="number"
                                        step="0.01"
                                        value={(data as any)[key]}
                                        onChange={(e) => handleMonthChange(key, e.target.value)}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando...' : 'Guardar Cambios'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/presupuesto">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        { title: 'Presupuesto', href: '/presupuesto' },
        { title: 'Editar', href: '#' },
    ],
};
