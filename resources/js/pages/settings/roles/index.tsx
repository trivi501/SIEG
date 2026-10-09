import { Head, Link, router } from '@inertiajs/react';
import { Plus, Shield, Pencil, Trash2 } from 'lucide-react';
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
import { create, index } from '@/routes/roles';

type Permission = string;

type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions_count: number;
    permissions: Permission[];
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

export default function Index({ roles }: { roles: PaginatedData<Role> }) {
    const handleDelete = (role: Role) => {
        if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
            router.delete(`/settings/roles/${role.id}`);
        }
    };

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Roles"
                        description="Manage roles and their permissions"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="mr-2 h-4 w-4" />
                            New Role
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Roles</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Guard</TableHead>
                                    <TableHead>Permissions</TableHead>
                                    <TableHead className="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roles.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="text-center text-muted-foreground">
                                            No roles found.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {roles.data.map((role) => (
                                    <TableRow key={role.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Shield className="h-4 w-4 text-muted-foreground" />
                                                {role.name}
                                            </div>
                                        </TableCell>
                                        <TableCell>{role.guard_name}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {role.permissions.slice(0, 5).map((perm) => (
                                                    <Badge key={perm} variant="secondary">
                                                        {perm}
                                                    </Badge>
                                                ))}
                                                {role.permissions_count > 5 && (
                                                    <Badge variant="outline">
                                                        +{role.permissions_count - 5}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link href={`/settings/roles/${role.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDelete(role)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {roles.links && (
                            <div className="mt-4 flex justify-center gap-1">
                                {roles.links.map((link, i) => (
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
            title: 'Roles',
            href: index(),
        },
    ],
};
