import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import PresupuestoLineaForm, {
    lineaVacia,
} from '@/components/presupuesto-linea-form';
import type { CatalogosPresupuesto } from '@/components/presupuesto-linea-form';
import { Button } from '@/components/ui/button';

export default function Create({
    catalogos,
}: {
    catalogos: CatalogosPresupuesto;
}) {
    return (
        <>
            <Head title="Nueva partida" />
            <div className="space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/presupuesto">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Nueva partida"
                        description="Agrega una línea al presupuesto de egresos. La distribución mensual debe sumar exactamente el importe total."
                    />
                </div>
                <PresupuestoLineaForm
                    inicial={lineaVacia()}
                    catalogos={catalogos}
                    metodo="post"
                    url="/presupuesto"
                    textoBoton="Dar de alta"
                />
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs: [
        { title: 'Presupuesto', href: '/presupuesto' },
        { title: 'Nueva partida', href: '/presupuesto/crear' },
    ],
};
