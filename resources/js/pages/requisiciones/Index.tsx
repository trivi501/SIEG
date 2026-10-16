import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Eye, Pencil, Download, Search, Send, X } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmarAccion from '@/components/confirmar-accion';
import SuficienciaBadge from '@/components/suficiencia-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fecha } from '@/lib/presupuesto';
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
    cat_egreso_unidad_administrativa?: { clave: string; nombre: string } | null;
    suficiencia?: boolean | null;
    extemporanea?: boolean | null;
    extemporanea_autorizada_at?: string | null;
    orden_compra?: { folio_completo: string; xml_uuid: string | null } | null;
}

export default function Index({
    requisiciones,
    estados,
    filters,
}: {
    requisiciones: { data: Requisicion[] };
    estados: {
        id_cat_egreso_requisicion_estado: number;
        descripcion: string;
    }[];
    filters: Record<string, string>;
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const [enviarRm, setenviarRm] = useState<Requisicion | null>(null);
    const [f, setF] = useState({
        folio: filters?.folio ?? '',
        folio_completo: filters?.folio_completo ?? '',
        unidad: filters?.unidad ?? '',
        concepto: filters?.concepto ?? '',
        estado: filters?.estado ?? '',
        año: filters?.año ?? '',
    });

    const aplicar = (valores: typeof f) => {
        const params: Record<string, string> = {};

        if (valores.folio) {
            params.folio = valores.folio;
        }

        if (valores.folio_completo) {
            params.folio_completo = valores.folio_completo;
        }

        if (valores.unidad) {
            params.unidad = valores.unidad;
        }

        if (valores.concepto) {
            params.concepto = valores.concepto;
        }

        if (valores.estado) {
            params.estado = valores.estado;
        }

        if (valores.año) {
            params.año = valores.año;
        }

        router.get('/requisiciones', params, {
            preserveState: true,
            replace: true,
        });
    };

    // Cada filtro busca solo: el estado al momento y el texto al dejar de escribir.
    const espera = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const cambiar = (
        campo: keyof typeof f,
        valor: string,
        inmediato = false,
    ) => {
        const nuevo = { ...f, [campo]: valor };
        setF(nuevo);
        clearTimeout(espera.current);

        if (inmediato) {
            aplicar(nuevo);
        } else {
            espera.current = setTimeout(() => aplicar(nuevo), 400);
        }
    };

    const limpiar = () => {
        clearTimeout(espera.current);
        setF({
            folio: '',
            folio_completo: '',
            unidad: '',
            concepto: '',
            estado: '',
            año: '',
        });
        router.get('/requisiciones', {}, { replace: true });
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            clearTimeout(espera.current);
            aplicar(f);
        }
    };

    return (
        <>
            <Head title="Requisiciones" />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium">Requisiciones</h3>
                    <div className="flex items-center gap-2">
                        {permisos.includes('gasto-index') && (
                            <Button variant="outline" asChild>
                                <Link href="/requisiciones/gasto">
                                    Ejercicio y control del gasto
                                </Link>
                            </Button>
                        )}
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
                                            <input
                                                value={f.folio}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'folio',
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={handleKeyDown}
                                                placeholder="..."
                                                className="w-16 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Folio Completo
                                        <div className="mt-1 flex gap-1">
                                            <input
                                                value={f.folio_completo}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'folio_completo',
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={handleKeyDown}
                                                placeholder="..."
                                                className="w-24 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Secretaría / Unidad administrativa
                                        <div className="mt-1 flex gap-1">
                                            <input
                                                value={f.unidad}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'unidad',
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={handleKeyDown}
                                                placeholder="Clave o nombre..."
                                                className="w-36 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Estado
                                        <div className="mt-1">
                                            <select
                                                value={f.estado}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'estado',
                                                        e.target.value,
                                                        true,
                                                    )
                                                }
                                                className="w-28 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            >
                                                <option value="">Todos</option>
                                                {estados.map((e) => (
                                                    <option
                                                        key={
                                                            e.id_cat_egreso_requisicion_estado
                                                        }
                                                        value={
                                                            e.id_cat_egreso_requisicion_estado
                                                        }
                                                    >
                                                        {e.descripcion}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Concepto
                                        <div className="mt-1 flex gap-1">
                                            <input
                                                value={f.concepto}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'concepto',
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={handleKeyDown}
                                                placeholder="..."
                                                className="w-32 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        Registro
                                        <div className="mt-1 flex gap-1">
                                            <input
                                                value={f.año}
                                                onChange={(e) =>
                                                    cambiar(
                                                        'año',
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={handleKeyDown}
                                                placeholder="Año"
                                                className="w-14 rounded border-input px-1 py-0.5 text-xs shadow-sm"
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="p-2">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-6 px-1"
                                                onClick={() => aplicar(f)}
                                            >
                                                <Search className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-6 px-1"
                                                onClick={limpiar}
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requisiciones.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay requisiciones registradas.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {requisiciones.data.map((req) => (
                                    <TableRow
                                        key={req.id_tb_egreso_requisicion}
                                    >
                                        <TableCell className="font-medium">
                                            {req.folio}
                                        </TableCell>
                                        <TableCell>
                                            {req.folio_completo}
                                        </TableCell>
                                        <TableCell className="max-w-56 text-xs whitespace-normal">
                                            {req.cat_egreso_unidad_administrativa ? (
                                                <>
                                                    <span className="font-mono text-muted-foreground">
                                                        {
                                                            req
                                                                .cat_egreso_unidad_administrativa
                                                                .clave
                                                        }
                                                    </span>{' '}
                                                    {
                                                        req
                                                            .cat_egreso_unidad_administrativa
                                                            .nombre
                                                    }
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <Badge
                                                    variant="secondary"
                                                    className={
                                                        req.id_cat_egreso_requisicion_estado ===
                                                        12
                                                            ? 'bg-red-100 text-red-800'
                                                            : ''
                                                    }
                                                >
                                                    {req
                                                        .cat_egreso_requisicion_estado
                                                        ?.descripcion ?? '—'}
                                                </Badge>
                                                <SuficienciaBadge
                                                    suficiencia={
                                                        req.suficiencia
                                                    }
                                                />
                                                {!!req.extemporanea && (
                                                    <Badge
                                                        variant="secondary"
                                                        className={
                                                            req.extemporanea_autorizada_at
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-amber-100 text-amber-900'
                                                        }
                                                    >
                                                        {req.extemporanea_autorizada_at
                                                            ? 'Extemporánea autorizada'
                                                            : 'Extemporánea por autorizar'}
                                                    </Badge>
                                                )}
                                                {req.orden_compra && (
                                                    <Badge
                                                        variant="outline"
                                                        title={
                                                            req.orden_compra
                                                                .xml_uuid
                                                                ? 'Factura cargada'
                                                                : 'Factura pendiente'
                                                        }
                                                    >
                                                        {
                                                            req.orden_compra
                                                                .folio_completo
                                                        }
                                                        {req.orden_compra
                                                            .xml_uuid
                                                            ? ' · facturada'
                                                            : ''}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-muted-foreground">
                                            {req.observaciones || '—'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {fecha(req.registro, true)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/requisiciones/${req.id_tb_egreso_requisicion}`}
                                                        title="Ver requisición"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {(req.id_cat_egreso_requisicion_estado ===
                                                    1 ||
                                                    req.id_cat_egreso_requisicion_estado ===
                                                        4) && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/requisiciones/${req.id_tb_egreso_requisicion}/edit`}
                                                            title="Editar"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {req.id_cat_egreso_requisicion_estado ===
                                                    3 && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        title="Enviar a Recursos Materiales"
                                                        onClick={() =>
                                                            setenviarRm(req)
                                                        }
                                                    >
                                                        <Send className="mr-1 h-4 w-4" />{' '}
                                                        A Recursos Materiales
                                                    </Button>
                                                )}
                                                {(req.id_cat_egreso_requisicion_estado ===
                                                    3 ||
                                                    (req.id_cat_egreso_requisicion_estado >=
                                                        5 &&
                                                        req.id_cat_egreso_requisicion_estado !==
                                                            12)) && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/requisiciones/${req.id_tb_egreso_requisicion}/pdf`}
                                                            target="_blank"
                                                            title="Imprimir formato (PDF)"
                                                        >
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
            <ConfirmarAccion
                abierto={enviarRm !== null}
                onCerrar={() => setenviarRm(null)}
                titulo="Enviar a Recursos Materiales"
                descripcion="Recursos Materiales la recibe para cotizarla y validar la suficiencia presupuestal."
                tono="info"
                encabezado={{
                    titulo: enviarRm?.folio_completo,
                    subtitulo: enviarRm?.cat_egreso_unidad_administrativa
                        ? `${enviarRm.cat_egreso_unidad_administrativa.clave} ${enviarRm.cat_egreso_unidad_administrativa.nombre}`
                        : undefined,
                }}
                datos={[
                    {
                        etiqueta: 'Concepto',
                        valor: enviarRm?.observaciones || '—',
                        ancho: true,
                    },
                ]}
                cambio={{ de: 'Aprobada', a: 'En Recursos Materiales' }}
                textoConfirmar="Enviar a Recursos Materiales"
                onConfirmar={(_, op) =>
                    router.post(
                        `/requisiciones/${enviarRm?.id_tb_egreso_requisicion}/recursos-materiales`,
                        {},
                        op,
                    )
                }
            />
        </>
    );
}

Index.layout = { breadcrumbs };
