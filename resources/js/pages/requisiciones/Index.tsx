import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Pencil, Download, Search, X } from 'lucide-react';
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
import { useState } from 'react';
import SuficienciaBadge from '@/components/suficiencia-badge';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Requisiciones', href: '/requisiciones' },
];

interface Requisicion {
    id_tb_egreso_requisicion: number;
    folio: number;
    folio_completo: string;
    año: number;
    registro: string | null;
    observaciones: string | null;
    id_cat_egreso_requisicion_estado: number;
    cat_egreso_requisicion_estado?: { descripcion: string } | null;
    suficiencia?: boolean | null;
    orden_compra?: { folio_completo: string; xml_uuid: string | null } | null;
}

export default function Index({ requisiciones, estados, filters }: { requisiciones: { data: Requisicion[] }; estados: { id_cat_egreso_requisicion_estado: number; descripcion: string }[]; filters: Record<string, string> }) {
    const [f, setF] = useState({
        folio: filters?.folio ?? '',
        folio_completo: filters?.folio_completo ?? '',
        concepto: filters?.concepto ?? '',
        estado: filters?.estado ?? '',
        año: filters?.año ?? '',
    });

    const aplicar = () => {
        const params: Record<string, string> = {};
        if (f.folio) params.folio = f.folio;
        if (f.folio_completo) params.folio_completo = f.folio_completo;
        if (f.concepto) params.concepto = f.concepto;
        if (f.estado) params.estado = f.estado;
        if (f.año) params.año = f.año;
        router.get('/requisiciones', params, { preserveState: true, replace: true });
    };

    const limpiar = () => {
        setF({ folio: '', folio_completo: '', concepto: '', estado: '', año: '' });
        router.get('/requisiciones', {}, { replace: true });
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') aplicar();
    };

    return (
        <>
            <Head title="Requisiciones" />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium">Requisiciones</h3>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/requisiciones/gasto">Cómo va el gasto</Link>
                        </Button>
                        <Button asChild>
                            <Link href="/requisiciones/crear">
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Requisición
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Listado de Requisiciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="p-2">
                                        Folio
                                        <div className="mt-1 flex gap-1">
                                            <input value={f.folio} onChange={(e) => setF({ ...f, folio: e.target.value })} onKeyDown={handleKeyDown} placeholder="..." className="w-16 rounded border-input px-1 py-0.5 text-xs shadow-sm" />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Folio Completo
                                        <div className="mt-1 flex gap-1">
                                            <input value={f.folio_completo} onChange={(e) => setF({ ...f, folio_completo: e.target.value })} onKeyDown={handleKeyDown} placeholder="..." className="w-24 rounded border-input px-1 py-0.5 text-xs shadow-sm" />
                                        </div>
                                    </TableHead>
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
                                    <TableHead className="p-2">
                                        Registro
                                        <div className="mt-1 flex gap-1">
                                            <input value={f.año} onChange={(e) => setF({ ...f, año: e.target.value })} onKeyDown={handleKeyDown} placeholder="Año" className="w-14 rounded border-input px-1 py-0.5 text-xs shadow-sm" />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        <div className="flex items-center gap-1">
                                            <Button size="sm" variant="ghost" className="h-6 px-1" onClick={aplicar}><Search className="h-3 w-3" /></Button>
                                            <Button size="sm" variant="ghost" className="h-6 px-1" onClick={limpiar}><X className="h-3 w-3" /></Button>
                                        </div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requisiciones.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground">
                                            No hay requisiciones registradas.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {requisiciones.data.map((req) => (
                                    <TableRow key={req.id_tb_egreso_requisicion}>
                                        <TableCell className="font-medium">{req.folio}</TableCell>
                                        <TableCell>{req.folio_completo}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <Badge variant="secondary">
                                                    {req.cat_egreso_requisicion_estado?.descripcion ?? '—'}
                                                </Badge>
                                                <SuficienciaBadge suficiencia={req.suficiencia} />
                                                {req.orden_compra && (
                                                    <Badge variant="outline" title={req.orden_compra.xml_uuid ? 'Factura cargada' : 'Factura pendiente'}>
                                                        {req.orden_compra.folio_completo}{req.orden_compra.xml_uuid ? ' · facturada' : ''}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-muted-foreground">
                                            {req.observaciones || '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">
                                            {req.registro ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/requisiciones/${req.id_tb_egreso_requisicion}`}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {(req.id_cat_egreso_requisicion_estado === 1 || req.id_cat_egreso_requisicion_estado === 4) && (
                                                    <Button variant="ghost" size="icon" asChild>
                                                        <Link href={`/requisiciones/${req.id_tb_egreso_requisicion}/edit`}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {[3, 5, 6].includes(req.id_cat_egreso_requisicion_estado) && (
                                                    <Button variant="ghost" size="icon" asChild>
                                                        <a href={`/requisiciones/${req.id_tb_egreso_requisicion}/pdf`} target="_blank">
                                                            <Download className="h-4 w-4" />
                                                        </a>
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
