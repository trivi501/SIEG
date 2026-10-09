import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/permissions';

export default function Create() {
    return (
        <>
            <Head title="Create Permission" />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/settings/permissions">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Create Permission"
                        description="Add a new permission"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Permission Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={store()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Permission Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="e.g. create-users, edit-roles"
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-red-500">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="nombre_mostrar">Display Name</Label>
                                        <Input
                                            id="nombre_mostrar"
                                            name="nombre_mostrar"
                                            placeholder="e.g. Create Users, Edit Roles"
                                        />
                                        {errors.nombre_mostrar && (
                                            <p className="text-sm text-red-500">{errors.nombre_mostrar}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="categoria">Category</Label>
                                        <Input
                                            id="categoria"
                                            name="categoria"
                                            placeholder="e.g. Users, Roles, Predios"
                                        />
                                        {errors.categoria && (
                                            <p className="text-sm text-red-500">{errors.categoria}</p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Save Permission
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link href="/settings/permissions">Cancel</Link>
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
            title: 'Permissions',
            href: '/settings/permissions',
        },
        {
            title: 'Create',
            href: store(),
        },
    ],
};
