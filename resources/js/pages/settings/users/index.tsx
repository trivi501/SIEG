import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users as UsersIcon, Pencil, Trash2, Shield } from 'lucide-react';
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

type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    secretaria: string | null;
    roles: string[];
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

export default function Index({ users }: { users: PaginatedData<User> }) {
    const handleDelete = (user: User) => {
        if (confirm(`Are you sure you want to delete user "${user.name}"?`)) {
            router.delete(`/settings/users/${user.id}`);
        }
    };

    return (
        <>
            <Head title="Usuarios" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Usuarios"
                        description="Manage system users"
                    />
                    <Button asChild>
                        <Link href="/settings/users/create">
                            <Plus className="mr-2 h-4 w-4" />
                            New User
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Secretaría</TableHead>
                                    <TableHead>Verified</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center text-muted-foreground">
                                            No users found.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <UsersIcon className="h-4 w-4 text-muted-foreground" />
                                                {user.name}
                                            </div>
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.secretaria || <span className="text-muted-foreground italic">—</span>}
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at ? (
                                                <Badge variant="secondary">{user.email_verified_at}</Badge>
                                            ) : (
                                                <Badge variant="outline">Unverified</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge key={role} variant="secondary">
                                                            <Shield className="mr-1 h-3 w-3" />
                                                            {role}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground italic">No roles</span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{user.created_at}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/settings/users/${user.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => handleDelete(user)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {users.links && (
                            <div className="mt-4 flex justify-center gap-1">
                                {users.links.map((link, i) => (
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
            title: 'Usuarios',
            href: '/settings/users',
        },
    ],
};
