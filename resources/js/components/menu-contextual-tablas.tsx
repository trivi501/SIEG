import { useEffect, useRef, useState } from 'react';

/**
 * Clic derecho sobre un renglón de cualquier tabla: muestra las acciones de su última columna (los
 * botones y enlaces de la derecha) y al elegir una hace lo mismo que su botón. Funciona en todas las
 * tablas sin cambiar cada pantalla. Ctrl + clic derecho, o sobre un campo de texto o texto seleccionado,
 * deja el menú normal del navegador.
 */

type Opcion = {
    etiqueta: string;
    icono: SVGElement | null;
    deshabilitada: boolean;
    elemento: HTMLElement;
};

type Menu = { x: number; y: number; opciones: Opcion[] };

// Botones que solo traen ícono (lucide): su nombre por el ícono.
const POR_ICONO: [RegExp, string][] = [
    [/lucide-eye\b/, 'Ver'],
    [/lucide-(pencil|square-pen|pen)\b/, 'Editar'],
    [/lucide-trash/, 'Eliminar'],
    [/lucide-history\b/, 'Historial de cambios'],
    [/lucide-download\b/, 'Descargar'],
    [/lucide-(file-text|printer)\b/, 'Imprimir'],
    [/lucide-send\b/, 'Enviar'],
    [/lucide-(check|circle-check|check-circle)/, 'Aprobar'],
    [/lucide-(x|circle-x|x-circle)\b/, 'Cancelar'],
    [/lucide-rotate-ccw\b/, 'Reactivar'],
    [/lucide-power\b/, 'Activar / desactivar'],
    [/lucide-(shield|key)/, 'Permisos'],
    [/lucide-upload\b/, 'Subir'],
    [/lucide-banknote\b/, 'Pagar'],
    [/lucide-refresh-cw\b/, 'Actualizar'],
];

function etiquetaDe(el: HTMLElement): string {
    const texto =
        el.getAttribute('aria-label') ||
        el.getAttribute('title') ||
        el.textContent?.replace(/\s+/g, ' ').trim();

    if (texto) {
        return texto;
    }

    const clases = el.querySelector('svg')?.getAttribute('class') ?? '';

    return POR_ICONO.find(([patron]) => patron.test(clases))?.[1] ?? 'Acción';
}

function opcionesDelRenglon(renglon: HTMLTableRowElement): Opcion[] {
    const celdas =
        renglon.querySelectorAll<HTMLTableCellElement>(':scope > td');
    const ultima = celdas[celdas.length - 1];

    if (!ultima || celdas.length < 2) {
        return [];
    }

    return Array.from(ultima.querySelectorAll<HTMLElement>('a[href], button'))
        .filter(
            (el) =>
                el.getClientRects().length > 0 &&
                !el.closest('[role="menu"]') &&
                el.getAttribute('aria-hidden') !== 'true',
        )
        .map((el) => ({
            etiqueta: etiquetaDe(el),
            icono: el.querySelector('svg'),
            deshabilitada:
                el.hasAttribute('disabled') ||
                el.getAttribute('aria-disabled') === 'true',
            elemento: el,
        }));
}

function Icono({ svg }: { svg: SVGElement | null }) {
    const ref = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        const contenedor = ref.current;

        if (!contenedor || !svg) {
            return;
        }

        const copia = svg.cloneNode(true) as SVGElement;
        copia.setAttribute('class', 'h-4 w-4');
        contenedor.replaceChildren(copia);
    }, [svg]);

    return (
        <span
            ref={ref}
            className="flex h-4 w-4 shrink-0 items-center justify-center text-muted-foreground"
        />
    );
}

export default function MenuContextualTablas() {
    const [menu, setMenu] = useState<Menu | null>(null);
    const caja = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const abrir = (e: MouseEvent) => {
            const objetivo = e.target as Element | null;
            const renglon = objetivo?.closest<HTMLTableRowElement>('tbody tr');

            if (
                !renglon ||
                e.ctrlKey ||
                e.metaKey ||
                objetivo?.closest(
                    'input, textarea, select, [contenteditable]',
                ) ||
                (window.getSelection()?.toString() ?? '') !== ''
            ) {
                return;
            }

            const opciones = opcionesDelRenglon(renglon);

            if (opciones.length === 0) {
                return;
            }

            e.preventDefault();
            setMenu({ x: e.clientX, y: e.clientY, opciones });
        };

        document.addEventListener('contextmenu', abrir);

        return () => document.removeEventListener('contextmenu', abrir);
    }, []);

    useEffect(() => {
        if (!menu) {
            return;
        }

        // Dentro de la pantalla y con el foco en la primera opción (para usarlo con el teclado).
        const el = caja.current;

        if (el) {
            const { width, height } = el.getBoundingClientRect();
            el.style.left = `${Math.max(4, Math.min(menu.x, window.innerWidth - width - 4))}px`;
            el.style.top = `${Math.max(4, Math.min(menu.y, window.innerHeight - height - 4))}px`;
            el.querySelector<HTMLButtonElement>(
                'button:not(:disabled)',
            )?.focus();
        }

        const cerrar = () => setMenu(null);
        const fuera = (e: MouseEvent) => {
            if (!caja.current?.contains(e.target as Node)) {
                cerrar();
            }
        };
        const teclas = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                cerrar();
            }

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const botones = Array.from(
                    caja.current?.querySelectorAll<HTMLButtonElement>(
                        'button:not(:disabled)',
                    ) ?? [],
                );
                const actual = botones.indexOf(
                    document.activeElement as HTMLButtonElement,
                );
                const paso = e.key === 'ArrowDown' ? 1 : -1;
                botones[
                    (actual + paso + botones.length) % botones.length
                ]?.focus();
            }
        };

        document.addEventListener('mousedown', fuera);
        document.addEventListener('keydown', teclas);
        window.addEventListener('scroll', cerrar, true);
        window.addEventListener('resize', cerrar);

        return () => {
            document.removeEventListener('mousedown', fuera);
            document.removeEventListener('keydown', teclas);
            window.removeEventListener('scroll', cerrar, true);
            window.removeEventListener('resize', cerrar);
        };
    }, [menu]);

    if (!menu) {
        return null;
    }

    return (
        <div
            ref={caja}
            role="menu"
            aria-label="Acciones del renglón"
            style={{ left: menu.x, top: menu.y }}
            className="fixed z-50 min-w-48 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
            onContextMenu={(e) => e.preventDefault()}
        >
            {menu.opciones.map((o, i) => (
                <button
                    key={i}
                    type="button"
                    role="menuitem"
                    disabled={o.deshabilitada}
                    onClick={() => {
                        setMenu(null);
                        o.elemento.click();
                    }}
                    className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                >
                    <Icono svg={o.icono} />
                    {o.etiqueta}
                </button>
            ))}
        </div>
    );
}
