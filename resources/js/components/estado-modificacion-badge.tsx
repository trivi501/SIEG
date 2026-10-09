import { Badge } from '@/components/ui/badge';

const estilos: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-800',
    autorizada: 'bg-green-100 text-green-800',
    rechazada: 'bg-red-100 text-red-800',
};

const nombres: Record<string, string> = {
    pendiente: 'Pendiente',
    autorizada: 'Autorizada (procede)',
    rechazada: 'Rechazada (no procede)',
};

export default function EstadoModificacionBadge({
    estado,
}: {
    estado: string;
}) {
    return (
        <Badge className={estilos[estado] ?? ''} variant="secondary">
            {nombres[estado] ?? estado}
        </Badge>
    );
}
