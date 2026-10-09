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
    const [pos, setPos] = useState({ left: 0, top: 0, width: 240 });
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
        // Se cierra si se desplaza la página (el panel quedaría flotando fuera de lugar),
        // pero no al desplazarse dentro de su propia lista de opciones.
        const handleScroll = (e: Event) => {
            if (panelRef.current?.contains(e.target as Node)) {
                return;
            }

            setOpen(false);
        };
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
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
    }, [open]);

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
        }

        setQuery('');
        setOpen((v) => !v);
    };

    const select = (v: string) => {
        onChange(v);
        setOpen(false);
    };

    const selected = options.find((o) => o.value === value);
    const texto = (o: SearchableOption) =>
        showValue ? `${o.value}${o.label ? ` - ${o.label}` : ''}` : o.label;
    const q = query.trim().toLowerCase();
    const filtered = q
        ? options.filter((o) =>
              q
                  .split(/\s+/)
                  .every((t) =>
                      `${o.value} ${o.label}`.toLowerCase().includes(t),
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
                    className={`${inline ? 'absolute top-full left-0 mt-1 max-w-full' : 'fixed'} z-50 rounded-md border border-input bg-background shadow-lg`}
                    style={
                        inline
                            ? { width: '100%' }
                            : { left: pos.left, top: pos.top, width: pos.width }
                    }
                >
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Buscar..."
                        className="w-full border-b border-input bg-transparent px-2 py-1.5 text-xs focus:outline-none"
                    />
                    <div className="max-h-56 overflow-y-auto">
                        <button
                            type="button"
                            onClick={() => select('')}
                            className="block w-full px-2 py-1.5 text-left text-xs text-muted-foreground hover:bg-muted"
                        >
                            ---
                        </button>
                        {filtered.map((o) => (
                            <button
                                key={o.value}
                                type="button"
                                onClick={() => select(o.value)}
                                title={texto(o)}
                                className="flex w-full items-start gap-2 border-b border-border/40 px-2 py-1.5 text-left text-xs hover:bg-muted"
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
