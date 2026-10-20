export interface PresupuestoEgreso {
    id_presupuesto: number;
    CLAVE: string | null;
    UNIDAD_ADMINISTRATIVA: string | null;
    CLAVE3: string | null;
    FUENTE_DE_FINANCIAMIENTO: string | null;
    CLAVE6: string | null;
    PROYECTO: string | null;
    PARTIDA: string | null;
    capitulo: string | null;
    TIPO_DE_GASTO: string | null;
    NOMBRE_PARTIDA: string | null;
    IMPORTE_TOTAL: number | null;
    ENERO: number | null;
    FEBRERO: number | null;
    MARZO: number | null;
    ABRIL: number | null;
    MAYO: number | null;
    JUNIO: number | null;
    JULIO: number | null;
    AGOSTO: number | null;
    SEP: number | null;
    OCTUBRE: number | null;
    NOV: number | null;
    DIC: number | null;
    /** Importe total menos la suma de los meses (0 = la distribución cuadra). */
    diferencia?: number;
    /** Momentos del gasto de la línea (lista del presupuesto). */
    modificado?: number;
    vigente?: number;
    /** Vigente al mes de la consulta: aprobado de enero a ese mes ± modificaciones hasta ese mes. */
    vigente_mes?: number;
    comprometido?: number;
    devengado?: number;
    pagado?: number;
}
