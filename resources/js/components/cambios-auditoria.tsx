import { Badge } from '@/components/ui/badge';

type Valores = Record<string, unknown> | null;

const ACCIONES: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    alta: 'default',
    modificación: 'secondary',
    baja: 'destructive',
    reactivación: 'outline',
    eliminación: 'destructive',
};

export function AccionBadge({ accion }: { accion: string }) {
    return (
        <Badge variant={ACCIONES[accion] ?? 'outline'} className="capitalize">
            {accion}
        </Badge>
    );
}

const texto = (v: unknown) => {
    if (v === null || v === undefined || v === '') {
        return '—';
    }

    if (typeof v === 'boolean') {
        return v ? 'Sí' : 'No';
    }

    return String(v);
};

/** Campos que cambiaron: valor anterior → valor nuevo, con la etiqueta del catálogo cuando se conoce. */
export default function CambiosAuditoria({
    antes,
    despues,
    etiquetas = {},
    opciones = {},
}: {
    antes: Valores;
    despues: Valores;
    etiquetas?: Record<string, string>;
    /** campo → (valor guardado → texto), para mostrar nombres en lugar de ids o claves. */
    opciones?: Record<string, Record<string, string>>;
}) {
    // Con etiquetas se muestran solo los campos del catálogo (no columnas internas de tablas anteriores).
    const conEtiquetas = Object.keys(etiquetas).length > 0;
    const campos = Array.from(
        new Set([...Object.keys(antes ?? {}), ...Object.keys(despues ?? {})]),
    ).filter((c) => !conEtiquetas || c in etiquetas);
    const mostrar = (campo: string, v: unknown) =>
        v !== null && v !== undefined && opciones[campo]?.[String(v)]
            ? opciones[campo][String(v)]
            : texto(v);

    if (campos.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <dl className="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-0.5 text-xs">
            {campos.map((campo) => (
                <div key={campo} className="contents">
                    <dt className="text-muted-foreground">
                        {etiquetas[campo] ?? campo}
                    </dt>
                    <dd className="break-words">
                        {antes && (
                            <>
                                <span className="text-muted-foreground line-through">
                                    {mostrar(campo, antes[campo])}
                                </span>
                                {despues && ' → '}
                            </>
                        )}
                        {despues && (
                            <span className="font-medium">
                                {mostrar(campo, despues[campo])}
                            </span>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
