export type EstatusOrdenPago = 'Pagado' | 'Vencida' | 'Pendiente';

export function estatusOrdenPago(pagado: boolean, fechaVencimiento: string | null): EstatusOrdenPago {
    if (pagado) return 'Pagado';
    if (fechaVencimiento) {
        const hoy = new Date().toISOString().slice(0, 10);
        if (fechaVencimiento.slice(0, 10) < hoy) return 'Vencida';
    }
    return 'Pendiente';
}

export function estatusOrdenPagoBadgeClass(estatus: EstatusOrdenPago): string {
    switch (estatus) {
        case 'Pagado':
            return 'bg-green-100 text-green-800';
        case 'Vencida':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-yellow-100 text-yellow-800';
    }
}
