import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/roles';
import { useState } from 'react';

type Permission = {
    id: number;
    name: string;
    nombre_mostrar: string | null;
    categoria: string | null;
    guard_name: string;
};

type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions: number[];
};

function groupPermissions(perms: Permission[]): Record<string, Permission[]> {
    const groups: Record<string, Permission[]> = {};
    for (const p of perms) {
        const raw = p.categoria || (p.name.includes('-') ? p.name.split('-')[0] : 'generales');
        const category = raw.toLowerCase();
        if (!groups[category]) groups[category] = [];
        groups[category].push(p);
    }
    return groups;
}

function categoryLabel(cat: string): string {
    return categoryLabels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
}

const categoryLabels: Record<string, string> = {
    secretarias: 'Secretar├¡as',
    cuentas: 'Cuentas',
    'ordenes-pago': '├ôrdenes de Pago',
    cajas: 'Cajas',
    cajeros: 'Cajeros',
    cortes: 'Cortes de Caja',
    'historial-caja': 'Historial de Caja',
    logs: 'Logs',
    tickets: 'Tickets de Soporte',
    contribuyentes: 'Contribuyentes',
    predios: 'Predios',
    pagos: 'Pagos',
    'estado-cuenta': 'Estado de Cuenta Masivo',
    calculos: 'C├ílculos',
    descuentos: 'Descuentos',
    presupuesto: 'Presupuesto',
    users: 'Usuarios',
    roles: 'Roles',
    permisos: 'Permisos',
    create: 'Crear',
    ver: 'Ver',
    editar: 'Editar',
    eliminar: 'Eliminar',
    admin: 'Admin',
    caja: 'Caja',
    user: 'User',
    ordenes: '├ôrdenes',
    generales: 'Generales',
};

export default function Edit({ role, permissions }: { role: Role; permissions: Permission[] }) {
    const grouped = groupPermissions(permissions);
    const categories = Object.keys(grouped).sort();
    const [openCategories, setOpenCategories] = useState<Record<string, boolean>>(() => {
        const all: Record<string, boolean> = {};
        categories.forEach((c) => { all[c] = true; });
        return all;
    });
    const [search, setSearch] = useState('');

    const toggleCategory = (cat: string) => {
        setOpenCategories((prev) => ({ ...prev, [cat]: !prev[cat] }));
    };

    const term = search.trim().toLowerCase();
    const matchesSearch = (p: Permission) =>
        term === '' ||
        p.name.toLowerCase().includes(term) ||
        (p.nombre_mostrar ?? '').toLowerCase().includes(term);
    const visibleCategories = categories.filter((cat) => grouped[cat].some(matchesSearch));

    return (
        <>
            <Head title="Edit Role" />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/settings/roles">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title={`Edit "${role.name}"`}
                        description="Update role name and permissions"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Role Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={update({ role: role.id })}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Role Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={role.name}
                                            required
                                            placeholder="e.g. admin, editor"
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-red-500">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Permissions</Label>
                                        <Input
                                            type="text"
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            placeholder="Buscar permiso..."
                                            className="mb-2"
                                        />
                                        {term !== '' && visibleCategories.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                No se encontraron permisos que coincidan con &quot;{search}&quot;.
                                            </p>
                                        )}
                                        {categories.map((cat) => {
                                            const perms = grouped[cat];
                                            const isOpen = term !== '' ? true : openCategories[cat];
                                            const checked = perms.filter((p) => role.permissions.includes(p.id)).length;
                                            const categoryVisible = visibleCategories.includes(cat);
                                            return (
                                                <div key={cat} className={`rounded-lg border ${categoryVisible ? '' : 'hidden'}`}>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleCategory(cat)}
                                                        className="flex w-full items-center justify-between rounded-t-lg bg-muted/50 px-4 py-3 text-left text-sm font-medium hover:bg-muted/70"
                                                    >
                                                        <span>{categoryLabel(cat)} ({checked}/{perms.length})</span>
                                                        <ChevronDown className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                                                    </button>
                                                    {/* Checkboxes stay mounted (just hidden) when a category is
                                                        collapsed or filtered out, so their checked state still
                                                        submits with the form. */}
                                                    <div className={`grid grid-cols-2 gap-2 p-4 md:grid-cols-3 ${isOpen ? '' : 'hidden'}`}>
                                                        {perms.map((perm) => (
                                                            <label
                                                                key={perm.id}
                                                                className={`flex items-center gap-2 rounded-md border p-3 text-sm hover:bg-muted/30 ${matchesSearch(perm) ? '' : 'hidden'}`}
                                                            >
                                                                <Checkbox
                                                                    name="permissions[]"
                                                                    value={perm.id}
                                                                    defaultChecked={role.permissions.includes(perm.id)}
                                                                />
                                                                {perm.nombre_mostrar || perm.name}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        {permissions.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                No permissions available.
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Update Role
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link href="/settings/roles">Cancel</Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: '/settings/roles',
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
