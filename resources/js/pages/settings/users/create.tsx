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

export default function Create({ roles, secretarias }: { roles: Role[]; secretarias: { id: number; nombre: string }[] }) {
    return (
        <>
            <Head title="Create User" />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/settings/users">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Create User"
                        description="Add a new system user"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>User Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            method="post"
                            action="/settings/users"
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="Full name"
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
                                            required
                                            placeholder="user@example.com"
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-red-500">{errors.email}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            required
                                            placeholder="Min. 8 characters"
                                        />
                                        {errors.password && (
                                            <p className="text-sm text-red-500">{errors.password}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="secretaria_id">Secretaría</Label>
                                        <select
                                            id="secretaria_id"
                                            name="secretaria_id"
                                            className="block w-full rounded-md border-input px-3 py-2 text-sm shadow-sm focus:border-ring focus:ring-ring"
                                        >
                                            <option value="">Sin secretaría</option>
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
                                                    />
                                                    <Shield className="h-4 w-4 text-muted-foreground" />
                                                    {role.name}
                                                </label>
                                            ))}
                                        </div>
                                        {roles.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                No roles available. Create roles first.
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Save User
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

Create.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: '/settings/users',
        },
        {
            title: 'Create',
            href: '/settings/users/create',
        },
    ],
};
