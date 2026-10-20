import { useEffect, useRef, useState } from 'react';

export interface SearchableOption {
    value: string;
    label: string;
    /** Texto secundario a la derecha (p. ej. el disponible de una partida). */
    hint?: string;
}

export default function SearchableSelect({
    value,
    onChange,
    options,
    placeholder = 'Seleccionar...',
    disabled,
    showValue = true,
    panelWidth = 260,
    inline = false,
}: {
    value: string;
    onChange: (value: string) => void;
    options: SearchableOption[];
    placeholder?: string;
    disabled?: boolean;
    /** false = solo se muestra el label (cuando el value es una clave interna). */
    showValue?: boolean;
    panelWidth?: number;
    /** Panel posicionado respecto al botón (necesario dentro de un Dialog, cuyo transform rompe position:fixed). */
    inline?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    // Opción resaltada para elegir con el teclado (flechas + Enter).
    const [activo, setActivo] = useState(0);
    const [pos, setPos] = useState({ left: 0, top: 0, width: 240 });
    // Inline (dentro de un Dialog con scroll): se abre hacia arriba si abajo no cabe la lista.
    const [haciaArriba, setHaciaArriba] = useState(false);
    const btnRef = useRef<HTMLButtonElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleClick = (e: MouseEvent) => {
            if (
                panelRef.current?.contains(e.target as Node) ||
                btnRef.current?.contains(e.target as Node)
            ) {
                return;
            }

            setOpen(false);
        };
        // Se cierra si se desplaza la página (el panel fijo quedaría flotando fuera de lugar), pero no
        // al desplazarse dentro de su propia lista de opciones. El panel inline (dentro de un Dialog) se
        // mueve con su contenedor, así que no se cierra al desplazarlo.
        const handleScroll = (e: Event) => {
            if (inline || panelRef.current?.contains(e.target as Node)) {
                return;
            }

            setOpen(false);
        };
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
                btnRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', handleClick);
        document.addEventListener('scroll', handleScroll, true);
        document.addEventListener('keydown', handleKey);

        return () => {
            document.removeEventListener('mousedown', handleClick);
            document.removeEventListener('scroll', handleScroll, true);
            document.removeEventListener('keydown', handleKey);
        };
    }, [open, inline]);

    const toggle = () => {
        if (disabled) {
            return;
        }

        if (!open && btnRef.current) {
            const r = btnRef.current.getBoundingClientRect();
            const width = Math.min(
                Math.max(r.width, panelWidth),
                window.innerWidth - 16,
            );
            const left = Math.min(r.left, window.innerWidth - width - 8);
            setPos({ left: Math.max(left, 8), top: r.bottom + 4, width });

            // Contenedor con scroll más cercano (el Dialog) o la ventana.
            let limite = window.innerHeight;
            let topeArriba = 0;
            let padre = btnRef.current.parentElement;

            while (padre) {
                const estilo = getComputedStyle(padre).overflowY;

                if (estilo === 'auto' || estilo === 'scroll') {
                    const c = padre.getBoundingClientRect();
                    limite = Math.min(limite, c.bottom);
                    topeArriba = Math.max(topeArriba, c.top);
                    break;
                }

                padre = padre.parentElement;
            }

            const alto = 270; // buscador + lista (max-h-56)
            setHaciaArriba(
                limite - r.bottom < alto &&
                    r.top - topeArriba > limite - r.bottom,
            );
        }

        setQuery('');
        setActivo(0);
        setOpen((v) => !v);
    };

    const select = (v: string) => {
        onChange(v);
        setOpen(false);
        btnRef.current?.focus();
    };

    // Con el botón enfocado: flechas o cualquier letra abren la búsqueda (la letra ya se escribe en ella).
    const teclaEnBoton = (e: React.KeyboardEvent) => {
        if (open || disabled) {
            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            toggle();
        } else if (
            e.key.length === 1 &&
            e.key !== ' ' &&
            !e.ctrlKey &&
            !e.metaKey &&
            !e.altKey
        ) {
            e.preventDefault();
            toggle();
            setQuery(e.key);
        }
    };

    const selected = options.find((o) => o.value === value);
    const texto = (o: SearchableOption) =>
        showValue ? `${o.value}${o.label ? ` - ${o.label}` : ''}` : o.label;
    // Sin distinguir mayúsculas ni acentos ("tesoreria" encuentra "TESORERÍA").
    const normalizar = (s: string) =>
        s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const q = normalizar(query.trim());
    const filtered = q
        ? options.filter((o) =>
              q
                  .split(/\s+/)
                  .every((t) =>
                      normalizar(`${o.value} ${o.label}`).includes(t),
                  ),
          )
        : options;

    return (
        <div className={inline ? 'relative' : 'contents'}>
            <button
                type="button"
                ref={btnRef}
                disabled={disabled}
                onClick={toggle}
                onKeyDown={teclaEnBoton}
                className="w-full truncate rounded border border-input bg-background px-2 py-1.5 text-left text-xs shadow-sm disabled:opacity-50"
            >
                {selected ? (
                    texto(selected)
                ) : (
                    <span className="text-muted-foreground">{placeholder}</span>
                )}
            </button>
            {open && (
                <div
                    ref={panelRef}
                    className={`${inline ? `absolute left-0 max-w-full ${haciaArriba ? 'bottom-full mb-1' : 'top-full mt-1'}` : 'fixed'} z-50 rounded-md border border-input bg-background shadow-lg`}
                    style={
                        inline
                            ? { width: '100%' }
                            : { left: pos.left, top: pos.top, width: pos.width }
                    }
                >
                    <input
                        autoFocus
                        // El cursor al final: la letra que abrió la búsqueda ya está escrita.
                        onFocus={(e) =>
                            e.target.setSelectionRange(
                                e.target.value.length,
                                e.target.value.length,
                            )
                        }
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setActivo(0);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'ArrowDown') {
                                e.preventDefault();
                                setActivo((a) =>
                                    Math.min(a + 1, filtered.length - 1),
                                );
                            } else if (e.key === 'ArrowUp') {
                                e.preventDefault();
                                setActivo((a) => Math.max(a - 1, 0));
                            } else if (e.key === 'Enter') {
                                // Enter elige la opción resaltada (no envía el formulario).
                                e.preventDefault();

                                if (filtered[activo]) {
                                    select(filtered[activo].value);
                                }
                            } else if (e.key === 'Tab') {
                                setOpen(false);
                            }
                        }}
                        aria-label="Buscar"
                        placeholder="Buscar..."
                        className="w-full border-b border-input bg-transparent px-2 py-1.5 text-xs focus:outline-none"
                    />
                    {/* overscroll-contain: al llegar al final, el scroll no pasa al modal ni a la página. */}
                    <div className="max-h-56 overflow-y-auto overscroll-contain">
                        <button
                            type="button"
                            onClick={() => select('')}
                            className="block w-full px-2 py-1.5 text-left text-xs text-muted-foreground hover:bg-muted"
                        >
                            ---
                        </button>
                        {filtered.map((o, i) => (
                            <button
                                key={o.value}
                                ref={
                                    i === activo
                                        ? (el) =>
                                              el?.scrollIntoView({
                                                  block: 'nearest',
                                              })
                                        : undefined
                                }
                                type="button"
                                tabIndex={-1}
                                onClick={() => select(o.value)}
                                onMouseEnter={() => setActivo(i)}
                                title={texto(o)}
                                className={`flex w-full items-start gap-2 border-b border-border/40 px-2 py-1.5 text-left text-xs hover:bg-muted ${i === activo ? 'bg-muted' : ''}`}
                            >
                                <span className="min-w-0 flex-1 break-words">
                                    {texto(o)}
                                </span>
                                {o.hint && (
                                    <span className="shrink-0 text-muted-foreground">
                                        {o.hint}
                                    </span>
                                )}
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <p className="px-2 py-2 text-xs text-muted-foreground">
                                Sin resultados.
                            </p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
