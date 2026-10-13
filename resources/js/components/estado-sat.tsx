import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { fechaLocal } from '@/lib/presupuesto';

export type EstadoSatFactura = {
    id: number;
    sat_estado: string | null;
    sat_estatus_cancelacion: string | null;
    sat_efos: boolean | null;
    sat_verificado_at: string | null;
};

const COLORES: Record<string, string> = {
    Vigente:
        'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200',
    Cancelado:
        'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    'No Encontrado':
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
};

/** Estado de la factura en el SAT (Vigente / Cancelado / No encontrado) y botón para volver a verificar. */
export default function EstadoSat({
    orden,
    puedeVerificar,
    compacto = false,
}: {
    orden: EstadoSatFactura;
    puedeVerificar: boolean;
    compacto?: boolean;
}) {
    const [verificando, setVerificando] = useState(false);

    const verificar = () =>
        router.post(
            `/ordenes-compra/${orden.id}/verificar-sat`,
            {},
            {
                preserveScroll: true,
                onStart: () => setVerificando(true),
                onFinish: () => setVerificando(false),
            },
        );

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {orden.sat_estado ? (
                <Badge
                    variant="outline"
                    className={COLORES[orden.sat_estado] ?? ''}
                    title={orden.sat_estatus_cancelacion ?? undefined}
                >
                    SAT: {orden.sat_estado}
                </Badge>
            ) : (
                <Badge variant="outline" className="text-muted-foreground">
                    SAT: sin verificar
                </Badge>
            )}
            {orden.sat_efos && (
                <Badge
                    variant="outline"
                    className={COLORES.Cancelado}
                    title="El emisor aparece en la lista de EFOS del SAT (empresas que facturan operaciones simuladas)"
                >
                    EFOS
                </Badge>
            )}
            {!compacto && orden.sat_verificado_at && (
                <span className="text-xs text-muted-foreground">
                    verificado {fechaLocal(orden.sat_verificado_at)}
                </span>
            )}
            {!compacto && orden.sat_estatus_cancelacion && (
                <span className="text-xs text-muted-foreground">
                    · {orden.sat_estatus_cancelacion}
                </span>
            )}
            {puedeVerificar && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-6 px-1.5 text-xs"
                    disabled={verificando}
                    onClick={verificar}
                    title="Consultar en el SAT si la factura sigue vigente"
                >
                    <RefreshCw
                        className={`h-3 w-3 ${verificando ? 'animate-spin' : ''}`}
                    />
                    {!compacto && ' Verificar en el SAT'}
                </Button>
            )}
        </span>
    );
}
