import { Head, Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import { edit, index } from '@/routes/secretarias';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Secretarías', href: '/secretarias' },
    { title: 'Detalle', href: '#' },
];

interface User {
    id: number;
    name: string;
    email: string;
}

interface Secretaria {
    id: number;
    nombre: string;
    prefijo?: string;
    users: User[];
    unidades_administrativas: { id_cat_egreso_unidad_administrativa: number; clave: string; nombre: string }[];
}

export default function Show({ secretaria }: { secretaria: Secretaria }) {
    return (
        <>
            <Head title="Detalle de Secretaría" />
            <div className="p-6">
                <div className="mx-auto max-w-4xl rounded-lg border bg-white p-6">
                    <div className="mb-6 flex items-center justify-between">
                        <h3 className="text-lg font-medium">Secretaría: {secretaria.nombre}</h3>
                        <Link href={edit.url(secretaria.id)}
                            className="inline-flex items-center rounded-md bg-yellow-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-yellow-400">
                            Editar
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">ID</label>
                            <p className="mt-1 text-sm text-gray-900">{secretaria.id}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nombre</label>
                            <p className="mt-1 text-sm text-gray-900">{secretaria.nombre}</p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Prefijo</label>
                            <p className="mt-1 text-sm text-gray-900">{secretaria.prefijo ?? '—'}</p>
                        </div>
                    </div>

                    <div className="mt-8">
                        <h4 className="mb-4 text-md font-semibold text-gray-900">Unidades administrativas</h4>
                        {secretaria.unidades_administrativas?.length > 0 ? (
                            <ul className="list-disc space-y-1 pl-5 text-sm text-gray-700">
                                {secretaria.unidades_administrativas.map((u) => (
                                    <li key={u.id_cat_egreso_unidad_administrativa}>
                                        {u.clave} - {u.nombre}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-gray-500">No tiene unidades administrativas asignadas: sus usuarios no verán requisiciones ni presupuesto.</p>
                        )}
                    </div>
                    <div className="mt-8">
                        <h4 className="mb-4 text-md font-semibold text-gray-900">Usuarios en esta secretaría</h4>
                        {secretaria.users?.length > 0 ? (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {secretaria.users.map((user) => (
                                            <tr key={user.id} className="hover:bg-gray-50">
                                                <td className="whitespace-nowrap px-4 py-2 text-sm">{user.id}</td>
                                                <td className="whitespace-nowrap px-4 py-2 text-sm">{user.name}</td>
                                                <td className="whitespace-nowrap px-4 py-2 text-sm">{user.email}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay usuarios en esta secretaría.</p>
                        )}
                    </div>

                    <div className="mt-6">
                        <Link href={index.url()}
                            className="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                            Volver
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
