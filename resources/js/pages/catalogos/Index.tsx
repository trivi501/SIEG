import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catálogos', href: '/catalogos' },
];

interface CatalogoResumen {
    slug: string;
    titulo: string;
    grupo: string;
    descripcion: string;
    total: number;
    activos: number | null;
}

function Tarjeta({
    href,
    titulo,
    descripcion,
    detalle,
}: {
    href: string;
    titulo: string;
    descripcion: string;
    detalle?: string;
}) {
    return (
        <Link href={href} prefetch className="block">
            <Card className="h-full transition-colors hover:bg-muted/50">
                <CardHeader className="pb-2">
                    <CardTitle className="text-base">{titulo}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-1 text-sm text-muted-foreground">
                    {descripcion && <p>{descripcion}</p>}
                    {detalle && (
                        <p className="font-medium text-foreground">{detalle}</p>
                    )}
                </CardContent>
            </Card>
        </Link>
    );
}

export default function Index({
    grupos,
    catalogos,
    secretarias,
}: {
    grupos: string[];
    catalogos: CatalogoResumen[];
    secretarias: boolean;
}) {
    return (
        <>
            <Head title="Catálogos" />
            <div className="space-y-6 p-4 sm:p-6">
                <div>
                    <h3 className="text-lg font-medium">Catálogos generales</h3>
                    <p className="text-sm text-muted-foreground">
                        Altas, bajas y modificaciones, importación desde Excel e
                        historial de cambios de cada registro.
                    </p>
                </div>

                {catalogos.length === 0 && !secretarias && (
                    <p className="text-sm text-muted-foreground">
                        No tienes acceso a ningún catálogo.
                    </p>
                )}

                {grupos.map((grupo) => {
                    const delGrupo = catalogos.filter((c) => c.grupo === grupo);
                    const conSecretarias =
                        grupo === 'Estructura orgánica' && secretarias;

                    if (delGrupo.length === 0 && !conSecretarias) {
                        return null;
                    }

                    return (
                        <section key={grupo} className="space-y-2">
                            <h4 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                {grupo}
                            </h4>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {conSecretarias && (
                                    <Tarjeta
                                        href="/secretarias"
                                        titulo="Secretarías (dependencias)"
                                        descripcion="Dependencias y las direcciones que tiene asignadas cada una."
                                    />
                                )}
                                {delGrupo.map((c) => (
                                    <Tarjeta
                                        key={c.slug}
                                        href={`/catalogos/${c.slug}`}
                                        titulo={c.titulo}
                                        descripcion={c.descripcion}
                                        detalle={
                                            c.activos === null
                                                ? `${c.total} registros`
                                                : `${c.activos} activos de ${c.total}`
                                        }
                                    />
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </>
    );
}

Index.layout = { breadcrumbs };
