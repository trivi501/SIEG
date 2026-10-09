import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Shield } from 'lucide-react';
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
import { Form } from '@inertiajs/react';

type Role = {
    id: number;
    name: string;
};

type User = {
    id: number;
    name: string;
    email: string;
    secretaria_id: number | null;
    roles: number[];
};

export default function Edit({ user, roles, secretarias }: { user: User; roles: Role[]; secretarias: { id: number; nombre: string }[] }) {
    return (
        <>
            <Head title="Edit User" />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/settings/users">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Edit User"
                        description={`Editing ${user.name}`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>User Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            method="patch"
                            action={`/settings/users/${user.id}`}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={user.name}
                                            required
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-red-500">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                            required
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-red-500">{errors.email}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">New Password (leave blank to keep current)</Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            placeholder="Leave blank to keep current"
                                        />
                                        {errors.password && (
                                            <p className="text-sm text-red-500">{errors.password}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="secretaria_id">Secretar├¡a</Label>
                                        <select
                                            id="secretaria_id"
                                            name="secretaria_id"
                                            defaultValue={user.secretaria_id ?? ''}
                                            className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm focus:border-ring focus:ring-ring"
                                        >
                                            <option value="">Sin secretar├¡a</option>
                                            {secretarias.map((s) => (
                                                <option key={s.id} value={s.id}>{s.nombre}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Roles</Label>
                                        <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                                            {roles.map((role) => (
                                                <label
                                                    key={role.id}
                                                    className="flex items-center gap-2 rounded-md border p-3 text-sm"
                                                >
                                                    <Checkbox
                                                        name="roles[]"
                                                        value={role.id}
                                                        defaultChecked={user.roles.includes(role.id)}
                                                    />
                                                    <Shield className="h-4 w-4 text-muted-foreground" />
                                                    {role.name}
                                                </label>
                                            ))}
                                        </div>
                                        {roles.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                No roles available.
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Update User
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link href="/settings/users">Cancel</Link>
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
            title: 'Usuarios',
            href: '/settings/users',
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
