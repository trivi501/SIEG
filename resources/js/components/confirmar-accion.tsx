import type { VisitOptions } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Info,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type TonoAccion = 'exito' | 'peligro' | 'aviso' | 'info';

const TONOS: Record<
    TonoAccion,
    { icono: LucideIcon; circulo: string; boton: string }
> = {
    exito: {
        icono: CheckCircle2,
        circulo: 'bg-green-100 text-green-700',
        boton: 'bg-green-700 text-white hover:bg-green-800',
    },
    peligro: {
        icono: XCircle,
        circulo: 'bg-red-100 text-red-700',
        boton: 'bg-destructive text-white hover:bg-destructive/90',
    },
    aviso: {
        icono: AlertTriangle,
        circulo: 'bg-amber-100 text-amber-700',
        boton: 'bg-amber-600 text-white hover:bg-amber-700',
    },
    info: {
        icono: Info,
        circulo: 'bg-blue-100 text-blue-700',
        boton: '',
    },
};

/** Opciones de Inertia que el modal le pasa a quien confirma: cierra al terminar y muestra errores. */
export type OpcionesAccion = Pick<
    VisitOptions,
    'preserveScroll' | 'onSuccess' | 'onError' | 'onFinish'
>;

export interface DatoResumen {
    etiqueta: string;
    valor: ReactNode;
    ancho?: boolean;
}

/**
 * Modal de confirmación para los botones de acción (aprobar, enviar, rechazar, borrar…): título con
 * ícono por tono, resumen del documento, el cambio de estado que provoca y, si se pide, un motivo.
 */
export default function ConfirmarAccion({
    abierto,
    onCerrar,
    titulo,
    descripcion,
    tono = 'info',
    icono,
    encabezado,
    etiqueta,
    datos = [],
    cambio,
    motivo,
    textoConfirmar,
    textoProcesando = 'Guardando...',
    textoCerrar = 'Cancelar',
    onConfirmar,
}: {
    abierto: boolean;
    onCerrar: () => void;
    titulo: string;
    descripcion?: ReactNode;
    tono?: TonoAccion;
    icono?: LucideIcon;
    /** Título del resumen (p. ej. el folio) y una línea debajo. */
    encabezado?: { titulo: ReactNode; subtitulo?: ReactNode };
    etiqueta?: ReactNode;
    datos?: DatoResumen[];
    /** Estado actual → estado nuevo. */
    cambio?: { de: string; a: string };
    motivo?: {
        etiqueta: string;
        requerido?: boolean;
        minimo?: number;
        placeholder?: string;
    };
    textoConfirmar: string;
    textoProcesando?: string;
    /** Texto del botón que cierra sin hacer nada. */
    textoCerrar?: string;
    onConfirmar: (motivo: string, opciones: OpcionesAccion) => void;
}) {
    const [texto, setTexto] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [errores, setErrores] = useState<string[]>([]);
    const estilo = TONOS[tono];
    const Icono = icono ?? estilo.icono;
    const minimo = motivo?.requerido ? (motivo.minimo ?? 1) : 0;
    const motivoValido = !motivo || texto.trim().length >= minimo;

    const cerrar = () => {
        if (procesando) {
            return;
        }

        setTexto('');
        setErrores([]);
        onCerrar();
    };

    const confirmar = () => {
        setProcesando(true);
        setErrores([]);
        onConfirmar(texto.trim(), {
            preserveScroll: true,
            onSuccess: () => {
                setTexto('');
                onCerrar();
            },
            onError: (e) => setErrores(Object.values(e)),
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={(a) => !a && cerrar()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${estilo.circulo}`}
                        >
                            <Icono className="h-5 w-5" />
                        </span>
                        {titulo}
                    </DialogTitle>
                    {descripcion && (
                        <DialogDescription>{descripcion}</DialogDescription>
                    )}
                </DialogHeader>

                {(encabezado || datos.length > 0) && (
                    <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                        {encabezado && (
                            <div className="mb-3 flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-lg font-semibold">
                                        {encabezado.titulo}
                                    </p>
                                    {encabezado.subtitulo && (
                                        <p className="text-xs text-muted-foreground">
                                            {encabezado.subtitulo}
                                        </p>
                                    )}
                                </div>
                                {etiqueta}
                            </div>
                        )}
                        {datos.length > 0 && (
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                {datos.map((d) => (
                                    <div
                                        key={d.etiqueta}
                                        className={d.ancho ? 'col-span-2' : ''}
                                    >
                                        <dt className="text-muted-foreground">
                                            {d.etiqueta}
                                        </dt>
                                        <dd className="line-clamp-3 font-medium whitespace-pre-wrap">
                                            {d.valor ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>
                )}

                {cambio && (
                    <div className="flex items-center justify-center gap-3 text-xs">
                        <span className="rounded-full bg-muted px-3 py-1 font-medium">
                            {cambio.de}
                        </span>
                        <ArrowRight className="h-4 w-4 text-muted-foreground" />
                        <span
                            className={`rounded-full px-3 py-1 font-medium ${estilo.circulo}`}
                        >
                            {cambio.a}
                        </span>
                    </div>
                )}

                {motivo && (
                    <div className="space-y-1">
                        <label
                            htmlFor="motivo-accion"
                            className="text-sm font-medium"
                        >
                            {motivo.etiqueta}
                            {motivo.requerido && (
                                <span className="text-destructive"> *</span>
                            )}
                        </label>
                        <textarea
                            id="motivo-accion"
                            value={texto}
                            onChange={(e) => setTexto(e.target.value)}
                            placeholder={motivo.placeholder}
                            rows={3}
                            maxLength={1000}
                            className="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        {motivo.requerido && minimo > 1 && !motivoValido && (
                            <p className="text-xs text-muted-foreground">
                                Mínimo {minimo} caracteres.
                            </p>
                        )}
                    </div>
                )}

                {errores.map((e) => (
                    <p
                        key={e}
                        className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {e}
                    </p>
                ))}

                <DialogFooter>
                    <Button
                        variant="outline"
                        disabled={procesando}
                        onClick={cerrar}
                    >
                        {textoCerrar}
                    </Button>
                    <Button
                        className={estilo.boton}
                        disabled={procesando || !motivoValido}
                        onClick={confirmar}
                    >
                        <Icono className="mr-1 h-4 w-4" />
                        {procesando ? textoProcesando : textoConfirmar}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
