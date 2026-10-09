import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Key, Pencil, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Permission = {
    id: number;
    name: string;
    nombre_mostrar: string | null;
    categoria: string | null;
    guard_name: string;
    created_at: string;
};

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
}

interface Filters {
    name?: string;
    nombre_mostrar?: string;
    categoria?: string;
    guard_name?: string;
}

export default function Index({ permissions, filters }: { permissions: PaginatedData<Permission>; filters?: Filters }) {
    const [columnFilters, setColumnFilters] = useState<Filters>(filters ?? {});

    const handleDelete = (permission: Permission) => {
        if (confirm(`Are you sure you want to delete "${permission.name}"?`)) {
            router.delete(`/settings/permissions/${permission.id}`);
        }
    };

    const handleColumnFilterChange = (field: keyof Filters, value: string) => {
        setColumnFilters((prev) => ({ ...prev, [field]: value }));
    };

    const handleSearch = () => {
        const params: Record<string, string> = {};
        Object.entries(columnFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        router.get('/settings/permissions', params, { preserveState: true, replace: true });
    };

    const handleColumnFilterKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    return (
        <>
            <Head title="Permissions" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Permissions"
                        description="Manage individual permissions"
                    />
                    <Button asChild>
                        <Link href="/settings/permissions/create">
                            <Plus className="mr-2 h-4 w-4" />
                            New Permission
                        </Link>
                    </Button>
                </div>

                <Card className="w-full">
                    <CardHeader>
                        <CardTitle>All Permissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Display Name</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Guard</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="w-[100px]">Actions</TableHead>
                                </TableRow>
                                <TableRow>
                                    <TableHead>
                                        <input
                                            type="text"
                                            value={columnFilters.name ?? ''}
                                            onChange={(e) => handleColumnFilterChange('name', e.target.value)}
                                            onKeyDown={handleColumnFilterKeyDown}
                                            placeholder="Filtrar..."
                                            className="block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </TableHead>
                                    <TableHead>
                                        <input
                                            type="text"
                                            value={columnFilters.nombre_mostrar ?? ''}
                                            onChange={(e) => handleColumnFilterChange('nombre_mostrar', e.target.value)}
                                            onKeyDown={handleColumnFilterKeyDown}
                                            placeholder="Filtrar..."
                                            className="block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </TableHead>
                                    <TableHead>
                                        <input
                                            type="text"
                                            value={columnFilters.categoria ?? ''}
                                            onChange={(e) => handleColumnFilterChange('categoria', e.target.value)}
                                            onKeyDown={handleColumnFilterKeyDown}
                                            placeholder="Filtrar..."
                                            className="block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </TableHead>
                                    <TableHead>
                                        <input
                                            type="text"
                                            value={columnFilters.guard_name ?? ''}
                                            onChange={(e) => handleColumnFilterChange('guard_name', e.target.value)}
                                            onKeyDown={handleColumnFilterKeyDown}
                                            placeholder="Filtrar..."
                                            className="block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </TableHead>
                                    <TableHead />
                                    <TableHead>
                                        <Button type="button" size="sm" variant="secondary" onClick={handleSearch} className="w-full">
                                            Buscar
                                        </Button>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {permissions.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground">
                                            No permissions found.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {permissions.data.map((permission) => (
                                    <TableRow key={permission.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Key className="h-4 w-4 text-muted-foreground" />
                                                {permission.name}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {permission.nombre_mostrar || (
                                                <span className="text-muted-foreground italic">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {permission.categoria || (
                                                <span className="text-muted-foreground italic">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{permission.guard_name}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {permission.created_at}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/settings/permissions/${permission.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => handleDelete(permission)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {permissions.links && (
                            <div className="mt-4 flex justify-center gap-1">
                                {permissions.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        className={`inline-flex items-center rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    >
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Permissions',
            href: '/settings/permissions',
        },
    ],
};
