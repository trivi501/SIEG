import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { useState } from 'react';
import EstadoModificacionBadge from '@/components/estado-modificacion-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { etiquetaLinea, fechaLocal, money } from '@/lib/presupuesto';
import type { LineaPresupuesto } from '@/lib/presupuesto';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Modificaciones Presupuestales',
        href: '/modificaciones-presupuestales',
    },
    { title: 'Detalle', href: '#' },
];

export default function Show({
    modificacion,
    tipos,
    origen,
    destino,
}: {
    modificacion: any;
    tipos: Record<string, string>;
    origen: Required<LineaPresupuesto> | null;
    destino: Required<LineaPresupuesto> | null;
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const [accion, setAccion] = useState<'autorizar' | 'rechazar' | null>(null);
    const form = useForm({ observaciones: '' });
    const pendiente = modificacion.estado === 'pendiente';
    const importe = Number(modificacion.importe);

    const resolver = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            `/modificaciones-presupuestales/${modificacion.id}/${accion}`,
            { preserveScroll: true, onSuccess: () => setAccion(null) },
        );
    };

    const lados = [
        {
            lado: 'origen',
            titulo: 'Origen (disminuye)',
            linea: origen,
            signo: -1,
            descripcion: modificacion.origen_descripcion,
        },
        {
            lado: 'destino',
            titulo: 'Destino (aumenta)',
            linea: destino,
            signo: 1,
            descripcion: modificacion.destino_descripcion,
        },
    ].filter((l) => modificacion[`${l.lado}_clave`] !== null);

    return (
        <>
            <Head title={`Modificación ${modificacion.folio_completo}`} />
            <div className="p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/modificaciones-presupuestales">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h3 className="text-lg font-medium">
                        Modificación {modificacion.folio_completo}
                    </h3>
                    <EstadoModificacionBadge estado={modificacion.estado} />
                </div>

                <div className="grid max-w-4xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos generales</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="font-semibold">Tipo:</span>{' '}
                                {tipos[modificacion.tipo] ?? modificacion.tipo}
                            </div>
                            <div>
                                <span className="font-semibold">Importe:</span>{' '}
                                {money(importe)}
                            </div>
                            <div>
                                <span className="font-semibold">Solicitó:</span>{' '}
                                {modificacion.solicitante?.name ?? '—'} (
                                {fechaLocal(modificacion.created_at)})
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Requisición:
                                </span>{' '}
                                {modificacion.requisicion ? (
                                    <Link
                                        href={`/requisiciones/${modificacion.requisicion.id_tb_egreso_requisicion}`}
                                        className="text-primary hover:underline"
                                    >
                                        {
                                            modificacion.requisicion
                                                .folio_completo
                                        }
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </div>
                            <div className="col-span-2">
                                <span className="font-semibold">
                                    Justificación:
                                </span>
                                <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                    {modificacion.justificacion}
                                </p>
                            </div>
                            {!pendiente && (
                                <div className="col-span-2 rounded-md border p-3">
                                    <span className="font-semibold">
                                        {modificacion.estado === 'autorizada'
                                            ? 'Autorizó'
                                            : 'Rechazó'}
                                        :
                                    </span>{' '}
                                    {modificacion.autorizador?.name ?? '—'} el{' '}
                                    {fechaLocal(modificacion.resuelto_at)}
                                    {modificacion.observaciones_resolucion && (
                                        <p className="mt-1 text-muted-foreground">
                                            {
                                                modificacion.observaciones_resolucion
                                            }
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Partidas</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {lados.map(
                                ({
                                    lado,
                                    titulo,
                                    linea,
                                    signo,
                                    descripcion,
                                }) => (
                                    <div
                                        key={lado}
                                        className="rounded-md border p-3 text-sm"
                                    >
                                        <p className="font-semibold">
                                            {titulo}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {linea
                                                ? `${linea.unidad} · ${etiquetaLinea(linea)}`
                                                : descripcion}
                                        </p>
                                        {linea ? (
                                            <div className="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Vigente actual:
                                                    </span>{' '}
                                                    {money(linea.vigente)}
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Comprometido:
                                                    </span>{' '}
                                                    {money(linea.comprometido)}
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Disponible actual:
                                                    </span>{' '}
                                                    {money(linea.disponible)}
                                                </div>
                                                {pendiente && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Disponible al
                                                            autorizar:
                                                        </span>{' '}
                                                        <span
                                                            className={
                                                                linea.disponible +
                                                                    signo *
                                                                        importe <
                                                                0
                                                                    ? 'font-semibold text-destructive'
                                                                    : 'font-semibold'
                                                            }
                                                        >
                                                            {money(
                                                                linea.disponible +
                                                                    signo *
                                                                        importe,
                                                            )}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-xs text-destructive">
                                                Esta línea ya no existe en el
                                                presupuesto importado.
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/modificaciones-presupuestales">
                                Volver
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={`/modificaciones-presupuestales/${modificacion.id}/pdf`}
                                target="_blank"
                            >
                                <FileText className="mr-2 h-4 w-4" /> Imprimir
                                formato
                            </a>
                        </Button>
                        {pendiente &&
                            permisos.includes('modificaciones-autorizar') && (
                                <>
                                    <Button
                                        onClick={() => {
                                            form.reset();
                                            form.clearErrors();
                                            setAccion('autorizar');
                                        }}
                                    >
                                        Autorizar (procede)
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            form.reset();
                                            form.clearErrors();
                                            setAccion('rechazar');
                                        }}
                                    >
                                        Rechazar (no procede)
                                    </Button>
                                </>
                            )}
                    </div>
                </div>
            </div>

            <Dialog
                open={accion !== null}
                onOpenChange={(o) => !o && setAccion(null)}
            >
                <DialogContent>
                    <form onSubmit={resolver} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {accion === 'autorizar'
                                    ? '¿Autorizar la modificación?'
                                    : '¿Rechazar la modificación?'}
                            </DialogTitle>
                            <DialogDescription>
                                {accion === 'autorizar'
                                    ? `Se aplicará al presupuesto: ${money(importe)} ${modificacion.tipo === 'traspaso' ? 'pasan del origen al destino' : modificacion.tipo === 'reduccion' ? 'se reducen del origen' : 'se amplían en el destino'}.`
                                    : 'La solicitud quedará como "no procede" y el presupuesto no cambia.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Observaciones
                                {accion === 'rechazar' ? ' (motivo)' : ''}
                            </label>
                            <textarea
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                                className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm"
                                required={accion === 'rechazar'}
                            />
                            {form.errors.observaciones && (
                                <p className="text-sm text-destructive">
                                    {form.errors.observaciones}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAccion(null)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    accion === 'rechazar'
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={form.processing}
                            >
                                {accion === 'autorizar'
                                    ? 'Autorizar'
                                    : 'Rechazar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Show.layout = { breadcrumbs };
