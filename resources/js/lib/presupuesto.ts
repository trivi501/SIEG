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
    disponible: number;
}

export const money = (n: number | string | null | undefined) => {
    const valor = Number(n ?? 0);

    return (
        (valor < 0 ? '-$' : '$') +
        Math.abs(valor).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
};

/** Fecha/hora de Laravel (ISO en UTC) en la hora local del navegador. */
export const fechaLocal = (iso: string | null | undefined, conHora = true) =>
    iso
        ? new Date(iso).toLocaleString(
              'es-MX',
              conHora
                  ? { dateStyle: 'short', timeStyle: 'short' }
                  : { dateStyle: 'short' },
          )
        : '—';

/** La partida va primero: es lo que más se busca y lo que no debe cortarse en pantallas angostas. */
export const etiquetaLinea = (l: LineaPresupuesto) =>
    `${l.partida} ${l.nombre_partida} · Proy ${l.proyecto} ${l.nombre_proyecto} · FF ${l.fuente} ${l.nombre_fuente}`.replace(
        /\s+/g,
        ' ',
    );

