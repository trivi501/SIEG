import SearchableSelect from '@/components/searchable-select';
import type { SearchableOption } from '@/components/searchable-select';
import { money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';

type Campo = 'partida' | 'proyecto' | 'fuente';

export interface SeleccionLinea {
    partida: string;
    proyecto: string;
    fuente: string;
}

const CAMPOS: Campo[] = ['partida', 'proyecto', 'fuente'];

const NOMBRE: Record<
    Campo,
    'nombre_partida' | 'nombre_proyecto' | 'nombre_fuente'
> = {
    partida: 'nombre_partida',
    proyecto: 'nombre_proyecto',
    fuente: 'nombre_fuente',
};

const coincide = (l: LineaPresupuesto, sel: SeleccionLinea, excepto?: Campo) =>
    CAMPOS.every((c) => c === excepto || !sel[c] || l[c] === sel[c]);

/**
 * Tres celdas (Partida / Proyecto / Fuente) con buscador, encadenadas: cada columna solo ofrece
 * lo compatible con lo ya elegido en las otras y que tenga disponible. Si la combinación queda
 * determinada, se completan solas.
 *
 * `lineas` debe venir ya acotado a la unidad administrativa de la requisición.
 */
export default function LineaPresupuestoCeldas({
    lineas,
    seleccion,
    onChange,
    error,
    disabled,
    soloLectura,
}: {
    lineas: LineaPresupuesto[];
    seleccion: SeleccionLinea;
    onChange: (seleccion: SeleccionLinea) => void;
    error?: string;
    disabled?: boolean;
    soloLectura?: boolean;
}) {
    // Las líneas sin dinero no se ofrecen, salvo la que ya esté elegida (al editar).
    const candidatas = lineas.filter(
        (l) => l.disponible > 0 || CAMPOS.every((c) => l[c] === seleccion[c]),
    );
    const lineaElegida = CAMPOS.every((c) => seleccion[c])
        ? lineas.find((l) => coincide(l, seleccion))
        : undefined;

    const opciones = (campo: Campo): SearchableOption[] => {
        const porValor = new Map<
            string,
            { label: string; disponible: number }
        >();

        for (const l of candidatas.filter((l) =>
            coincide(l, seleccion, campo),
        )) {
            const actual = porValor.get(l[campo]);
            porValor.set(l[campo], {
                label: l[NOMBRE[campo]],
                disponible: (actual?.disponible ?? 0) + l.disponible,
            });
        }

        return [...porValor.entries()]
            .sort(([a], [b]) => a.localeCompare(b, 'es', { numeric: true }))
            .map(([value, { label, disponible }]) => ({
                value,
                label,
                hint: `Disp. ${money(disponible)}`,
            }));
    };

    const elegir = (campo: Campo, valor: string) => {
        let nueva: SeleccionLinea = { ...seleccion, [campo]: valor };

        if (valor) {
            // Si lo nuevo no combina con lo que ya estaba en las otras columnas, se limpian.
            if (!candidatas.some((l) => coincide(l, nueva))) {
                nueva = {
                    partida: '',
                    proyecto: '',
                    fuente: '',
                    [campo]: valor,
                };
            }

            const posibles = candidatas.filter((l) => coincide(l, nueva));

            if (posibles.length === 1) {
                nueva = {
                    partida: posibles[0].partida,
                    proyecto: posibles[0].proyecto,
                    fuente: posibles[0].fuente,
                };
            }
        }

        onChange(nueva);
    };

    const texto = (campo: Campo) => {
        const valor = seleccion[campo];
        const nombre = lineas.find((l) => l[campo] === valor)?.[NOMBRE[campo]];

        return valor ? `${valor}${nombre ? ` - ${nombre}` : ''}` : '—';
    };

    if (soloLectura) {
        return (
            <>
                {CAMPOS.map((campo) => (
                    <td key={campo} className="p-1">
                        <span className="block px-2 py-1.5 text-xs text-muted-foreground">
                            {texto(campo)}
                        </span>
                    </td>
                ))}
            </>
        );
    }

    return (
        <>
            {CAMPOS.map((campo) => (
                <td key={campo} className="p-1">
                    <SearchableSelect
                        value={seleccion[campo]}
                        onChange={(v) => elegir(campo, v)}
                        options={opciones(campo)}
                        panelWidth={420}
                        disabled={disabled}
                        placeholder={
                            disabled ? 'Elige la unidad' : 'Seleccionar...'
                        }
                    />
                    {campo === 'partida' && lineaElegida && (
                        <p
                            className={`mt-1 px-1 text-xs ${lineaElegida.disponible > 0 ? 'text-muted-foreground' : 'text-destructive'}`}
                        >
                            Disponible: {money(lineaElegida.disponible)}
                        </p>
                    )}
                    {campo === 'partida' && error && (
                        <p className="mt-1 px-1 text-xs text-destructive">
                            {error}
                        </p>
                    )}
                </td>
            ))}
        </>
    );
}
