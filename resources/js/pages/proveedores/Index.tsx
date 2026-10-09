import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recursos Materiales', href: '/recursos-materiales' },
    { title: 'Proveedores', href: '/proveedores' },
];

interface Proveedor {
    id: number;
    nombre: string;
    rfc: string | null;
    correo: string | null;
    telefono: string | null;
    domicilio: string | null;
    activo: boolean;
    ordenes_compra_count: number;
}

interface Paginado<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const vacio = {
    nombre: '',
    rfc: '',
    correo: '',
    telefono: '',
    domicilio: '',
    activo: true,
};

export default function Index({
    proveedores,
    filters,
}: {
    proveedores: Paginado<Proveedor>;
    filters: { buscar?: string };
}) {
    const permisos =
        (usePage().props.userPermissions as string[] | undefined) ?? [];
    const [buscar, setBuscar] = useState(filters?.buscar ?? '');
    const [editando, setEditando] = useState<Proveedor | null>(null);
    const [abierto, setAbierto] = useState(false);
    const [eliminar, setEliminar] = useState<Proveedor | null>(null);
    const form = useForm(vacio);

    const aplicar = () =>
        router.get('/proveedores', buscar ? { buscar } : {}, {
            preserveState: true,
            replace: true,
        });

    const abrir = (p: Proveedor | null) => {
        setEditando(p);
        form.clearErrors();
        form.setData(
            p
                ? {
                      nombre: p.nombre,
                      rfc: p.rfc ?? '',
                      correo: p.correo ?? '',
                      telefono: p.telefono ?? '',
                      domicilio: p.domicilio ?? '',
                      activo: p.activo,
                  }
                : vacio,
        );
        setAbierto(true);
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        const opciones = {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        };

        if (editando) {
            form.put(`/proveedores/${editando.id}`, opciones);
        } else {
            form.post('/proveedores', opciones);
        }
    };

    const campo = (
        nombre: 'nombre' | 'rfc' | 'correo' | 'telefono' | 'domicilio',
        etiqueta: string,
        extra: React.InputHTMLAttributes<HTMLInputElement> = {},
    ) => (
        <div className="space-y-1">
            <label className="text-sm font-medium">{etiqueta}</label>
            <input
                value={form.data[nombre]}
                onChange={(e) =>
                    form.setData(
                        nombre,
                        nombre === 'rfc'
                            ? e.target.value.toUpperCase()
                            : e.target.value,
                    )
                }
                className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm"
                {...extra}
            />
            {form.errors[nombre] && (
                <p className="text-sm text-destructive">
                    {form.errors[nombre]}
                </p>
            )}
        </div>
    );

    return (
        <>
            <Head title="Proveedores" />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium">Proveedores</h3>
                    {permisos.includes('proveedores-create') && (
                        <Button onClick={() => abrir(null)}>
                            <Plus className="mr-2 h-4 w-4" /> Nuevo proveedor
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && aplicar()
                                }
                                placeholder="Buscar por nombre o RFC..."
                                className="w-72 rounded-md border-input px-2 py-1 text-sm font-normal shadow-sm"
                            />
                            <Button size="sm" variant="ghost" onClick={aplicar}>
                                <Search className="h-4 w-4" />
                            </Button>
                            {filters?.buscar && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setBuscar('');
                                        router.get(
                                            '/proveedores',
                                            {},
                                            { replace: true },
                                        );
                                    }}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>RFC</TableHead>
                                    <TableHead>Contacto</TableHead>
                                    <TableHead className="text-center">
                                        Órdenes
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {proveedores.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay proveedores registrados.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {proveedores.data.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="font-medium">
                                            {p.nombre}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {p.rfc ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {[p.correo, p.telefono]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {p.ordenes_compra_count}
                                        </TableCell>
                                        <TableCell>
                                            {p.activo ? (
                                                <Badge variant="secondary">
                                                    Activo
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Inactivo
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {permisos.includes(
                                                'proveedores-edit',
                                            ) && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => abrir(p)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {permisos.includes(
                                                'proveedores-delete',
                                            ) && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setEliminar(p)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {proveedores.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap gap-1">
                                {proveedores.links.map((l, i) => (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={
                                            l.active ? 'default' : 'outline'
                                        }
                                        disabled={!l.url}
                                        onClick={() =>
                                            l.url &&
                                            router.get(
                                                l.url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: l.label,
                                            }}
                                        />
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent>
                    <form onSubmit={guardar} className="space-y-3">
                        <DialogHeader>
                            <DialogTitle>
                                {editando
                                    ? 'Editar proveedor'
                                    : 'Nuevo proveedor'}
                            </DialogTitle>
                            <DialogDescription>
                                El RFC se usa para validar que la factura (XML)
                                la emita este proveedor.
                            </DialogDescription>
                        </DialogHeader>
                        {campo('nombre', 'Nombre o razón social', {
                            required: true,
                            maxLength: 300,
                        })}
                        {campo('rfc', 'RFC', { maxLength: 13 })}
                        {campo('correo', 'Correo', { type: 'email' })}
                        {campo('telefono', 'Teléfono')}
                        {campo('domicilio', 'Domicilio')}
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.activo}
                                onChange={(e) =>
                                    form.setData('activo', e.target.checked)
                                }
                                className="rounded border-input"
                            />
                            Activo
                        </label>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAbierto(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Guardar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!eliminar}
                onOpenChange={(o) => !o && setEliminar(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            ¿Eliminar a {eliminar?.nombre}?
                        </DialogTitle>
                        <DialogDescription>
                            {eliminar && eliminar.ordenes_compra_count > 0
                                ? 'Tiene órdenes de compra, así que no se borra: solo se desactivará.'
                                : 'Se eliminará del catálogo.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEliminar(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                eliminar &&
                                router.delete(`/proveedores/${eliminar.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => setEliminar(null),
                                })
                            }
                        >
                            {eliminar && eliminar.ordenes_compra_count > 0
                                ? 'Desactivar'
                                : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Index.layout = { breadcrumbs };
