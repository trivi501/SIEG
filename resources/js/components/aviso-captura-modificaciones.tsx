import { CalendarClock } from 'lucide-react';

/** Periodo de captura de modificaciones presupuestales del usuario (ModificacionPresupuestalController::captura). */
export interface CapturaModificaciones {
    abierta: boolean;
    /** Control Presupuestal / Admin: capturan todo el mes. */
    siempre: boolean;
    inicio: string;
    ultimoDia: string;
    /** true = periodo del Calendario de captura de modificaciones; false = regla por omisión. */
    calendario: boolean;
    proximaApertura: string;
    diasCierre: number;
}

const fecha = (ymd: string) => ymd.split('-').reverse().join('/');

/**
 * Aviso del periodo de captura de las áreas: el del calendario que define el Admin o, si el mes no está
 * registrado, del día 1 hasta {diasCierre} días antes del fin de mes. Control Presupuestal siempre puede.
 */
export default function AvisoCapturaModificaciones({
    captura,
}: {
    captura: CapturaModificaciones;
}) {
    if (captura.siempre) {
        return null;
    }

    const periodo = (
        <>
            del <strong>{fecha(captura.inicio)}</strong> al{' '}
            <strong>{fecha(captura.ultimoDia)}</strong>
            {!captura.calendario &&
                ` (se cierra ${captura.diasCierre} días antes del fin de mes)`}
        </>
    );

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
                    La captura de modificaciones está abierta: {periodo}.
                </span>
            ) : (
                <span>
                    La captura de modificaciones <strong>está cerrada</strong>{' '}
                    (periodo {periodo}). Se abre el{' '}
                    <strong>{fecha(captura.proximaApertura)}</strong>; mientras
                    tanto, solo Control Presupuestal puede registrar
                    modificaciones.
                </span>
            )}
        </div>
    );
}
