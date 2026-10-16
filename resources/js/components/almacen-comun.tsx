import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/** Tipos y piezas comunes de las pantallas de almacén. */
export interface OrdenAlmacen {
    id: number;
    orden: string;
    requisicion_id: number;
    requisicion: string | null;
    unidad: string | null;
    proveedor: string | null;
    asignada: string | null;
    pedido: number;
    recibido: number;
    entregado: number;
    por_recibir: number;
    existencia: number;
}

export interface Paginado<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

/** Situación de la orden en el almacén. */
export function situacion(o: {
    recibido: number;
    por_recibir: number;
    existencia: number;
}) {
    if (o.recibido === 0) {
        return { texto: 'Por recibir', clase: 'bg-amber-100 text-amber-900' };
    }

    if (o.por_recibir > 0) {
        return {
            texto: 'Recibida en parte',
            clase: 'bg-blue-100 text-blue-900',
        };
    }

    return o.existencia > 0
        ? { texto: 'En existencia', clase: 'bg-blue-100 text-blue-900' }
        : { texto: 'Entregada', clase: 'bg-green-100 text-green-800' };
}

export function Paginas({ paginado }: { paginado: Paginado<unknown> }) {
    if (paginado.links.length <= 3) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap items-center gap-1">
            <span className="mr-2 text-xs text-muted-foreground">
                {paginado.from}–{paginado.to} de {paginado.total}
            </span>
            {paginado.links.map((l, i) => (
                <Button
                    key={i}
                    size="sm"
                    variant={l.active ? 'default' : 'outline'}
                    disabled={!l.url}
                    onClick={() =>
                        l.url && router.get(l.url, {}, { preserveState: true })
                    }
                >
                    <span dangerouslySetInnerHTML={{ __html: l.label }} />
                </Button>
            ))}
        </div>
    );
}
