/** Línea del presupuesto de egresos (unidad + fuente + proyecto + partida) con sus saldos. */
export interface LineaPresupuesto {
    clave: string;
    unidad_clave: string;
    unidad?: string;
    fuente: string;
    nombre_fuente: string;
    proyecto: string;
    nombre_proyecto: string;
    partida: string;
    nombre_partida: string;
    asignado?: number;
    modificado?: number;
    vigente?: number;
    comprometido?: number;
    en_tramite?: number;
    devengado?: number;
    pagado?: number;
    disponible: number;
    /** Lo que quedó disponible en los meses anteriores y se corrió al mes actual. */
    saldo_corrido?: number;
    /** Saldo corrido + asignado, modificado y comprometido del mes actual. */
    disponible_mes?: number;
}

/**
 * Formato de números de todo el sistema: coma para miles y millones, punto para los centavos
 * (1,234,567.89). Se fija el formato en lugar de usar el idioma del navegador, que en algunos
 * equipos pone puntos en los miles.
 */
const formatoNumero = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatoPrecio = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

/** Precio unitario de la cotización: hasta 4 decimales ($1,234.5678; $10.50). */
export const precio = (n: number | string | null | undefined) =>
    '$' + formatoPrecio.format(Number(n ?? 0));

export const numero = (n: number | string | null | undefined) =>
    formatoNumero.format(Number(n ?? 0));

export const money = (n: number | string | null | undefined) => {
    const valor = Number(n ?? 0);

    return (valor < 0 ? '-$' : '$') + formatoNumero.format(Math.abs(valor));
};

/**
 * Fechas en formato es-MX en todo el sistema: dd/mm/aaaa (y hh:mm a. m./p. m.).
 *
 * fechaLocal: fecha/hora de Laravel (ISO en UTC), en la hora local del navegador.
 */
export const fechaLocal = (iso: string | null | undefined, conHora = true) => {
    if (!iso) {
        return '—';
    }

    // Una fecha sola ("2026-10-13") no lleva zona: se muestra tal cual, sin recorrerla un día.
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return fecha(iso);
    }

    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? iso
        : d.toLocaleString('es-MX', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              ...(conHora ? { hour: '2-digit', minute: '2-digit' } : {}),
          });
};

/**
 * Fecha sin zona: columnas DATE o DATETIME que MySQL guarda en hora local ("2026-10-13" o
 * "2026-10-13 08:20:00"). Se reacomoda el texto (dd/mm/aaaa [hh:mm]) sin convertir de zona.
 */
export const fecha = (
    valor: string | null | undefined,
    conHora = false,
): string => {
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(
        String(valor ?? ''),
    );

    if (!m) {
        return valor ? String(valor) : '—';
    }

    const [, año, mes, dia, hora, minuto] = m;

    return `${dia}/${mes}/${año}${conHora && hora ? ` ${hora}:${minuto}` : ''}`;
};

/** La partida va primero: es lo que más se busca y lo que no debe cortarse en pantallas angostas. */
export const etiquetaLinea = (l: LineaPresupuesto) =>
    `${l.partida} ${l.nombre_partida} · Proy ${l.proyecto} ${l.nombre_proyecto} · FF ${l.fuente} ${l.nombre_fuente}`.replace(
        /\s+/g,
        ' ',
    );
