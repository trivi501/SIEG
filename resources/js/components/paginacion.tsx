import { Button } from '@/components/ui/button';

export const POR_PAGINA = 25;

/** Filas de la página (1 = primera); si la página quedó fuera de rango (búsqueda), regresa a la última. */
export function paginar<T>(filas: T[], pagina: number, porPagina = POR_PAGINA) {
    const paginas = Math.max(1, Math.ceil(filas.length / porPagina));
    const actual = Math.min(Math.max(1, pagina), paginas);

    return {
        filas: filas.slice((actual - 1) * porPagina, actual * porPagina),
        pagina: actual,
        paginas,
        desde: filas.length === 0 ? 0 : (actual - 1) * porPagina + 1,
        hasta: Math.min(actual * porPagina, filas.length),
        total: filas.length,
    };
}

/** Paginación en pantalla (los datos ya están cargados), con el mismo estilo de los listados del servidor. */
export default function Paginacion({
    pagina,
    paginas,
    desde,
    hasta,
    total,
    onCambiar,
}: {
    pagina: number;
    paginas: number;
    desde: number;
    hasta: number;
    total: number;
    onCambiar: (pagina: number) => void;
}) {
    if (total === 0) {
        return null;
    }

    // Primera, última y dos alrededor de la actual; los saltos se marcan con "…".
    const numeros = Array.from({ length: paginas }, (_, i) => i + 1).filter(
        (n) => n === 1 || n === paginas || Math.abs(n - pagina) <= 2,
    );

    return (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
            <span className="text-xs text-muted-foreground">
                {desde}–{hasta} de {total}
            </span>
            {paginas > 1 && (
                <div className="flex flex-wrap gap-1">
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={pagina === 1}
                        onClick={() => onCambiar(pagina - 1)}
                    >
                        « Anterior
                    </Button>
                    {numeros.map((n, i) => (
                        <span key={n} className="flex gap-1">
                            {i > 0 && n - numeros[i - 1] > 1 && (
                                <span className="px-1 py-1 text-sm text-muted-foreground">
                                    …
                                </span>
                            )}
                            <Button
                                size="sm"
                                variant={n === pagina ? 'default' : 'outline'}
                                onClick={() => onCambiar(n)}
                            >
                                {n}
                            </Button>
                        </span>
                    ))}
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={pagina === paginas}
                        onClick={() => onCambiar(pagina + 1)}
                    >
                        Siguiente »
                    </Button>
                </div>
            )}
        </div>
    );
}
