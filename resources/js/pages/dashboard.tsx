import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { money } from '@/lib/presupuesto';
import { dashboard } from '@/routes';

interface Resumen {
    totales: Record<'asignado' | 'modificado' | 'vigente' | 'comprometido' | 'en_tramite' | 'disponible', number>;
    lineas: number;
    lineasSinDisponible: number;
    pendientes: {
        pendientes: number;
        en_revision: number;
        recursos_materiales: number;
        sin_orden_compra: number;
        modificaciones: number;
    };
}

const saldos: { key: keyof Resumen['totales']; titulo: string; ayuda: string }[] = [
    { key: 'asignado', titulo: 'Asignado', ayuda: 'Presupuesto importado' },
    { key: 'modificado', titulo: 'Modificado', ayuda: 'Modificaciones autorizadas' },
    { key: 'vigente', titulo: 'Vigente', ayuda: 'Asignado ± modificado' },
    { key: 'comprometido', titulo: 'Comprometido', ayuda: 'Requisiciones con suficiencia' },
    { key: 'en_tramite', titulo: 'En trámite', ayuda: 'Cotizadas sin suficiencia' },
    { key: 'disponible', titulo: 'Disponible', ayuda: 'Vigente − comprometido' },
];

export default function Dashboard({ resumen }: { resumen: Resumen | null }) {
    if (!resumen) {
        return (
            <>
                <Head title="Panel" />
                <div className="p-6 text-sm text-muted-foreground">No tienes acceso a requisiciones ni presupuesto.</div>
            </>
        );
    }

    const p = resumen.pendientes;
    const tarjetas = [
        { titulo: 'Requisiciones por enviar', valor: p.pendientes, href: '/requisiciones?estado=1', ayuda: 'Pendientes o rechazadas' },
        { titulo: 'En revisión', valor: p.en_revision, href: '/requisiciones?estado=2', ayuda: 'Esperan validación' },
        { titulo: 'En Recursos Materiales', valor: p.recursos_materiales, href: '/recursos-materiales', ayuda: 'Por recibir, cotizar o validar suficiencia' },
        { titulo: 'Con suficiencia sin orden de compra', valor: p.sin_orden_compra, href: '/recursos-materiales', ayuda: 'Listas para comprar' },
        { titulo: 'Modificaciones por autorizar', valor: p.modificaciones, href: '/modificaciones-presupuestales?estado=pendiente', ayuda: 'Traspasos, reducciones y ampliaciones' },
    ];

    return (
        <>
            <Head title="Panel" />
            <div className="space-y-6 p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Presupuesto de egresos</CardTitle>
                        <p className="text-xs text-muted-foreground">
                            {resumen.lineas} líneas de presupuesto · {resumen.lineasSinDisponible} sin disponible ·{' '}
                            <Link href="/requisiciones/gasto" className="text-primary hover:underline">ver detalle por partida</Link>
                        </p>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                        {saldos.map((s) => (
                            <div key={s.key} className="rounded-md border p-3" title={s.ayuda}>
                                <p className="text-xs text-muted-foreground">{s.titulo}</p>
                                <p className={`mt-1 text-lg font-semibold ${resumen.totales[s.key] < 0 ? 'text-destructive' : ''}`}>{money(resumen.totales[s.key])}</p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {tarjetas.map((t) => (
                        <Link key={t.titulo} href={t.href} className="rounded-lg border p-4 transition-colors hover:bg-muted/50">
                            <p className="text-sm font-medium">{t.titulo}</p>
                            <p className="mt-2 text-3xl font-semibold">{t.valor}</p>
                            <p className="mt-1 text-xs text-muted-foreground">{t.ayuda}</p>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Panel',
            href: dashboard(),
        },
    ],
};
