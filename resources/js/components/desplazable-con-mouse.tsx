import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** Elementos sobre los que el clic conserva su comportamiento (no inicia el arrastre). */
const INTERACTIVOS =
    'a, button, input, select, textarea, label, [role="button"]';

/**
 * Contenedor con desplazamiento horizontal que además se puede arrastrar con el mouse
 * (clic sostenido y mover), útil para tablas anchas. Un arrastre no dispara el clic de lo que
 * esté debajo; en pantallas táctiles se usa el desplazamiento normal.
 */
export default function DesplazableConMouse({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    const contenedor = useRef<HTMLDivElement>(null);
    const arrastre = useRef<{
        x: number;
        scroll: number;
        movido: boolean;
    } | null>(null);
    const [arrastrando, setArrastrando] = useState(false);

    // El <Table> de la interfaz trae su propio contenedor con scroll; si existe, ese es el que se mueve.
    const objetivo = () =>
        contenedor.current?.querySelector<HTMLElement>(
            '[data-slot="table-container"]',
        ) ?? contenedor.current;

    const iniciar = (e: React.PointerEvent<HTMLDivElement>) => {
        if (
            e.pointerType !== 'mouse' ||
            e.button !== 0 ||
            (e.target as HTMLElement).closest(INTERACTIVOS)
        ) {
            return;
        }

        arrastre.current = {
            x: e.clientX,
            scroll: objetivo()?.scrollLeft ?? 0,
            movido: false,
        };
    };

    const mover = (e: React.PointerEvent<HTMLDivElement>) => {
        const actual = arrastre.current;
        const desplazable = objetivo();

        if (!actual || !contenedor.current || !desplazable) {
            return;
        }

        // Se soltó el botón fuera de la tabla antes de empezar a arrastrar.
        if ((e.buttons & 1) === 0) {
            arrastre.current = null;
            setArrastrando(false);

            return;
        }

        const dx = e.clientX - actual.x;

        // Un pequeño umbral para no confundir un clic con un arrastre.
        if (!actual.movido && Math.abs(dx) > 4) {
            actual.movido = true;
            setArrastrando(true);
            contenedor.current.setPointerCapture(e.pointerId);
        }

        if (actual.movido) {
            desplazable.scrollLeft = actual.scroll - dx;
        }
    };

    const terminar = (e: React.PointerEvent<HTMLDivElement>) => {
        if (contenedor.current?.hasPointerCapture(e.pointerId)) {
            contenedor.current.releasePointerCapture(e.pointerId);
        }

        setArrastrando(false);
        // El clic que sigue a un arrastre se descarta en onClickCapture; luego se limpia.
        setTimeout(() => {
            arrastre.current = null;
        }, 0);
    };

    return (
        <div
            ref={contenedor}
            onPointerDown={iniciar}
            onPointerMove={mover}
            onPointerUp={terminar}
            onPointerCancel={terminar}
            onClickCapture={(e) => {
                if (arrastre.current?.movido) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }}
            className={cn(
                'overflow-x-auto',
                arrastrando ? 'cursor-grabbing select-none' : 'cursor-grab',
                className,
            )}
            title="Arrastra con el mouse para desplazar la tabla"
        >
            {children}
        </div>
    );
}
