import { Badge } from '@/components/ui/badge';

/** Resultado de validar la requisición contra el presupuesto (null = aún no se valida). */
export default function SuficienciaBadge({
    suficiencia,
}: {
    suficiencia: boolean | null | undefined;
}) {
    if (suficiencia === true) {
        return (
            <Badge className="bg-green-100 text-green-800" variant="secondary">
                Con suficiencia
            </Badge>
        );
    }

    if (suficiencia === false) {
        return (
            <Badge className="bg-red-100 text-red-800" variant="secondary">
                Sin suficiencia
            </Badge>
        );
    }

    return null;
}
