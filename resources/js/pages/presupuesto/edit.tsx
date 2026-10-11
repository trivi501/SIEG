import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import PresupuestoLineaForm, {
    MESES,
} from '@/components/presupuesto-linea-form';
import type {
    CatalogosPresupuesto,
    LineaDatos,
} from '@/components/presupuesto-linea-form';
import { Button } from '@/components/ui/button';
import type { PresupuestoEgreso } from '@/types';

export default function Edit({
    presupuesto,
    catalogos,
}: {
    presupuesto: PresupuestoEgreso;
    catalogos: CatalogosPresupuesto;
}) {
    const inicial = {
        CLAVE: presupuesto.CLAVE?.trim() ?? '',
        CLAVE3: presupuesto.CLAVE3?.trim() ?? '',
        CLAVE6: presupuesto.CLAVE6?.trim() ?? '',
        PARTIDA: presupuesto.PARTIDA?.trim() ?? '',
        IMPORTE_TOTAL: presupuesto.IMPORTE_TOTAL?.toString() ?? '',
        ...Object.fromEntries(
            MESES.map((m) => [m.key, presupuesto[m.key]?.toString() ?? '']),
        ),
    } as LineaDatos;

    return (
        <>
            <Head title="Editar partida" />
            <div className="space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/presupuesto">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Editar partida"
                        description="La distribución mensual debe sumar exactamente el importe total."
                    />
                </div>
                <PresupuestoLineaForm
                    inicial={inicial}
                    catalogos={catalogos}
                    metodo="patch"
                    url={`/presupuesto/${presupuesto.id_presupuesto}`}
                    textoBoton="Guardar cambios"
                />
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        { title: 'Presupuesto', href: '/presupuesto' },
        { title: 'Editar partida', href: '#' },
    ],
};
