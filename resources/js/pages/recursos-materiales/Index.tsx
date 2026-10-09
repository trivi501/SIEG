import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, CheckCircle, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SuficienciaBadge from '@/components/suficiencia-badge';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recursos Materiales', href: '/recursos-materiales' },
];

interface Requisicion {
    id_tb_egreso_requisicion: number;
    folio_completo: string;
    observaciones: string | null;
    id_cat_egreso_requisicion_estado: number;
    cat_egreso_requisicion_estado?: { descripcion: string } | null;
    cat_egreso_unidad_administrativa?: { nombre: string } | null;
    suficiencia?: boolean | null;
    total?: string | null;
    orden_compra?: { id: number; folio_completo: string; xml_uuid: string | null } | null;
}

export default function Index({ requisiciones, estados, filters }: { requisiciones: { data: Requisicion[] }; estados: { id_cat_egreso_requisicion_estado: number; descripcion: string }[]; filters?: Record<string, string> }) {
    const [f, setF] = useState({
        folio_completo: filters?.folio_completo ?? '',
        concepto: filters?.concepto ?? '',
        estado: filters?.estado ?? '',
    });

    const aplicar = () => {
        const params: Record<string, string> = {};
        if (f.folio_completo) params.folio_completo = f.folio_completo;
        if (f.concepto) params.concepto = f.concepto;
        if (f.estado) params.estado = f.estado;
        router.get('/recursos-materiales', params, { preserveState: true, replace: true });
    };

    const limpiar = () => {
        setF({ folio_completo: '', concepto: '', estado: '' });
        router.get('/recursos-materiales', {}, { replace: true });
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') aplicar();
    };

    const handleRecibido = (id: number) => {
        router.post(`/requisiciones/${id}/recibido`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const estadoColor = (estadoId: number) => {
        switch (estadoId) {
            case 5: return 'bg-amber-100 text-amber-800';
            case 6: return 'bg-blue-100 text-blue-800';
            default: return '';
        }
    };

    return (
        <>
            <Head title="Recursos Materiales" />
            <div className="p-6">
                <div className="mb-4 flex items-start justify-between">
                    <div>
                    <h3 className="text-lg font-medium">Recursos Materiales</h3>
                    <p className="text-sm text-muted-foreground mt-1">
                        Requisiciones pendientes de recibir, en cotización, con suficiencia y órdenes de compra
                    </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/proveedores">Proveedores</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Bandeja de Recursos Materiales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="p-2">
                                        Folio Completo
                                        <div className="mt-1 flex gap-1">
                                            <input value={f.folio_completo} onChange={(e) => setF({ ...f, folio_completo: e.target.value })} onKeyDown={handleKeyDown} placeholder="..." className="w-24 rounded border-input px-1 py-0.5 text-xs shadow-sm" />
                                        </div>
                                    </TableHead>
                                    <TableHead>Unidad Administrativa</TableHead>
                                    <TableHead className="p-2">
                                        Estado
                                        <div className="mt-1">
                                            <select value={f.estado} onChange={(e) => { setF({ ...f, estado: e.target.value }); setTimeout(aplicar, 50); }} className="w-28 rounded border-input px-1 py-0.5 text-xs shadow-sm">
                                                <option value="">Todos</option>
                                                {estados.map((e) => <option key={e.id_cat_egreso_requisicion_estado} value={e.id_cat_egreso_requisicion_estado}>{e.descripcion}</option>)}
                                            </select>
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Concepto
                                        <div className="mt-1 flex gap-1">
                                            <input value={f.concepto} onChange={(e) => setF({ ...f, concepto: e.target.value })} onKeyDown={handleKeyDown} placeholder="..." className="w-32 rounded border-input px-1 py-0.5 text-xs shadow-sm" />
                                        </div>
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button size="sm" variant="ghost" className="h-6 px-1" onClick={aplicar}><Search className="h-3 w-3" /></Button>
                                            <Button size="sm" variant="ghost" className="h-6 px-1" onClick={limpiar}><X className="h-3 w-3" /></Button>
                                        </div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requisiciones.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground">
                                            No hay requisiciones pendientes en Recursos Materiales.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {requisiciones.data.map((req) => (
                                    <TableRow key={req.id_tb_egreso_requisicion}>
                                        <TableCell className="font-medium">{req.folio_completo}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {req.cat_egreso_unidad_administrativa?.nombre ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <Badge className={estadoColor(req.id_cat_egreso_requisicion_estado)} variant="secondary">
                                                    {req.cat_egreso_requisicion_estado?.descripcion ?? '—'}
                                                </Badge>
                                                <SuficienciaBadge suficiencia={req.suficiencia} />
                                                {req.orden_compra && (
                                                    <Badge variant="outline">{req.orden_compra.folio_completo}{req.orden_compra.xml_uuid ? ' · facturada' : ' · sin factura'}</Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-muted-foreground text-xs">
                                            {req.observaciones || '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/requisiciones/${req.id_tb_egreso_requisicion}`}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {req.id_cat_egreso_requisicion_estado === 5 && (
                                                    <Button size="sm" onClick={() => handleRecibido(req.id_tb_egreso_requisicion)}>
                                                        <CheckCircle className="mr-1 h-4 w-4" /> Recibido
                                                    </Button>
                                                )}
                                                {req.id_cat_egreso_requisicion_estado === 6 && req.suficiencia !== true && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/requisiciones/${req.id_tb_egreso_requisicion}/edit`}>
                                                            <Pencil className="mr-1 h-4 w-4" /> Cotización
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Index.layout = { breadcrumbs };
