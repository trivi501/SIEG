import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { money } from '@/lib/presupuesto';
import { dashboard } from '@/routes';

interface Resumen {
    /** Montos del presupuesto: solo Admin y Control Presupuestal (permiso gasto-index). */
    verPresupuesto: boolean;
    totales: Record<
        | 'asignado'
        | 'modificado'
        | 'vigente'
        | 'comprometido'
        | 'en_tramite'
        | 'devengado'
        | 'pagado'
        | 'disponible',
        number
    >;
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

const saldos: {
    key: keyof Resumen['totales'];
    titulo: string;
    ayuda: string;
}[] = [
    {
        key: 'asignado',
        titulo: 'Aprobado',
        ayuda: 'Presupuesto aprobado (importado)',
    },
    {
        key: 'modificado',
        titulo: 'Modificado',
        ayuda: 'Modificaciones autorizadas',
    },
    { key: 'vigente', titulo: 'Vigente', ayuda: 'Aprobado ± modificado' },
    {
        key: 'comprometido',
        titulo: 'Comprometido',
        ayuda: 'Con suficiencia, sin factura',
    },
    {
        key: 'en_tramite',
        titulo: 'En trámite',
        ayuda: 'Cotizadas sin suficiencia',
    },
    {
        key: 'devengado',
        titulo: 'Devengado',
        ayuda: 'Con factura (XML), sin pago',
    },
    { key: 'pagado', titulo: 'Pagado', ayuda: 'Con pago registrado' },
    {
        key: 'disponible',
        titulo: 'Disponible',
        ayuda: 'Vigente − comprometido − devengado − pagado',
    },
];

export default function Dashboard({ resumen }: { resumen: Resumen | null }) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];

    if (!resumen) {
        return (
            <>
                <Head title="Panel" />
                <div className="p-6 text-sm text-muted-foreground">
                    No tienes acceso a requisiciones ni presupuesto.
                </div>
            </>
        );
    }

    const p = resumen.pendientes;
    // Solo las tarjetas de las pantallas a las que el usuario tiene acceso.
    const tarjetas: {
        titulo: string;
        valor: number;
        href: string;
        ayuda: string;
        permiso?: string;
    }[] = [
        {
            titulo: 'Requisiciones por enviar',
            valor: p.pendientes,
            href: '/requisiciones?estado=1',
            ayuda: 'Pendientes o rechazadas',
        },
        {
            titulo: 'En revisión',
            valor: p.en_revision,
            href: '/requisiciones?estado=2',
            ayuda: 'Esperan validación',
        },
        {
            titulo: 'En Recursos Materiales',
            valor: p.recursos_materiales,
            href: '/recursos-materiales',
            permiso: 'recursos-materiales-index',
            ayuda: 'Por recibir, cotizar o validar suficiencia',
        },
        {
            titulo: 'Con suficiencia sin orden de compra',
            valor: p.sin_orden_compra,
            href: '/recursos-materiales',
            permiso: 'recursos-materiales-index',
            ayuda: 'Listas para comprar',
        },
        {
            titulo: 'Modificaciones por autorizar',
            valor: p.modificaciones,
            href: '/modificaciones-presupuestales?estado=pendiente',
            permiso: 'modificaciones-index',
            ayuda: 'Traspasos, reducciones y ampliaciones',
        },
    ];

    return (
        <>
            <Head title="Panel" />
            <div className="space-y-6 p-6">
                {resumen.verPresupuesto && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Presupuesto de egresos</CardTitle>
                            <p className="text-xs text-muted-foreground">
                                {resumen.lineas} líneas de presupuesto ·{' '}
                                {resumen.lineasSinDisponible} sin disponible ·{' '}
                                <Link
                                    href="/requisiciones/gasto"
                                    className="text-primary hover:underline"
                                >
                                    ver detalle por partida
                                </Link>
                            </p>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-8">
                            {saldos.map((s) => (
                                <div
                                    key={s.key}
                                    className="rounded-md border p-3"
                                    title={s.ayuda}
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {s.titulo}
                                    </p>
                                    <p
                                        className={`mt-1 text-lg font-semibold ${resumen.totales[s.key] < 0 ? 'text-destructive' : ''}`}
                                    >
                                        {money(resumen.totales[s.key])}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {tarjetas
                        .filter(
                            (t) => !t.permiso || permisos.includes(t.permiso),
                        )
                        .map((t) => (
                            <Link
                                key={t.titulo}
                                href={t.href}
                                className="rounded-lg border p-4 transition-colors hover:bg-muted/50"
                            >
                                <p className="text-sm font-medium">
                                    {t.titulo}
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {t.valor}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t.ayuda}
                                </p>
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
