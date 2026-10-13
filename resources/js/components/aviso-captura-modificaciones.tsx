import { CalendarClock } from 'lucide-react';

/** Periodo de captura de modificaciones presupuestales del usuario (ModificacionPresupuestalController::captura). */
export interface CapturaModificaciones {
    abierta: boolean;
    /** Control Presupuestal / Admin: capturan todo el mes. */
    siempre: boolean;
    ultimoDia: string;
    diasCierre: number;
}

const fecha = (ymd: string) => ymd.split('-').reverse().join('/');

/**
 * Aviso del cierre de la captura: las áreas capturan hasta {diasCierre} días antes del fin de mes;
 * Control Presupuestal siempre puede.
 */
export default function AvisoCapturaModificaciones({
    captura,
}: {
    captura: CapturaModificaciones;
}) {
    if (captura.siempre) {
        return null;
    }

    return (
        <div
            className={`flex items-start gap-2 rounded-md border p-3 text-sm ${
                captura.abierta
                    ? 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200'
                    : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
            }`}
        >
            <CalendarClock className="mt-0.5 h-4 w-4 shrink-0" />
            {captura.abierta ? (
                <span>
                    La captura de modificaciones de este mes está abierta hasta
                    el <strong>{fecha(captura.ultimoDia)}</strong> (se cierra{' '}
                    {captura.diasCierre} días antes del fin de mes).
                </span>
            ) : (
                <span>
                    La captura de modificaciones de este mes{' '}
                    <strong>está cerrada</strong>: el último día fue el{' '}
                    {fecha(captura.ultimoDia)} ({captura.diasCierre} días antes
                    del fin de mes). Se abre de nuevo el día 1; mientras tanto,
                    solo Control Presupuestal puede registrar modificaciones.
                </span>
            )}
        </div>
    );
}
