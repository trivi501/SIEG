import { router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
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

/**
 * Modal para corregir la fecha de un documento (requisición, orden de compra). Manda { fecha } a la
 * URL y muestra el error del servidor (año distinto al del folio, fecha futura…).
 */
export default function EditarFecha({
    abierto,
    onCerrar,
    titulo,
    descripcion,
    documento,
    fechaActual,
    url,
}: {
    abierto: boolean;
    onCerrar: () => void;
    titulo: string;
    descripcion?: ReactNode;
    documento: ReactNode;
    fechaActual: string | null;
    url: string;
}) {
    const [fecha, setFecha] = useState(fechaActual ?? '');
    const [error, setError] = useState<string | null>(null);
    const [guardando, setGuardando] = useState(false);

    const cerrar = () => {
        if (guardando) {
            return;
        }

        setFecha(fechaActual ?? '');
        setError(null);
        onCerrar();
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        setGuardando(true);
        setError(null);
        router.post(
            url,
            { fecha },
            {
                preserveScroll: true,
                onSuccess: () => onCerrar(),
                onError: (errores) =>
                    setError(Object.values(errores).join(' ')),
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <Dialog open={abierto} onOpenChange={(a) => !a && cerrar()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                                <CalendarDays className="h-5 w-5" />
                            </span>
                            {titulo}
                        </DialogTitle>
                        {descripcion && (
                            <DialogDescription>{descripcion}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="flex items-center justify-between rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                        <span className="font-semibold">{documento}</span>
                        <span className="text-muted-foreground">
                            Actual: {fechaActual ?? '—'}
                        </span>
                    </div>

                    <div className="space-y-1">
                        <label
                            htmlFor="nueva-fecha"
                            className="text-sm font-medium"
                        >
                            Nueva fecha
                        </label>
                        <input
                            id="nueva-fecha"
                            type="date"
                            value={fecha}
                            onChange={(e) => setFecha(e.target.value)}
                            required
                            className="block rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        {error && (
                            <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                {error}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando}
                            onClick={cerrar}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                guardando || !fecha || fecha === fechaActual
                            }
                        >
                            <CalendarDays className="mr-1 h-4 w-4" />
                            {guardando ? 'Guardando...' : 'Guardar fecha'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
