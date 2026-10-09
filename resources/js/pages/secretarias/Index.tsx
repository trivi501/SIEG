import { Head, Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import { create, show, edit } from '@/routes/secretarias';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Secretarías', href: '/secretarias' },
];

interface Secretaria {
    id: number;
    nombre: string;
    prefijo: string | null;
    unidades_administrativas_count: number;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
}

export default function Index({ secretarias }: { secretarias: PaginatedData<Secretaria> }) {
    return (
        <>
            <Head title="Secretarías" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h3 className="text-lg font-medium">Listado de Secretarías</h3>
                    <Link
                        href={create.url()}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500"
                    >
                        + Crear Secretaría
                    </Link>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Prefijo</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unidades administrativas</th>
                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {secretarias.data?.length > 0 ? (
                                secretarias.data.map((secretaria) => (
                                    <tr key={secretaria.id} className="hover:bg-gray-50">
                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{secretaria.id}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{secretaria.nombre}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{secretaria.prefijo ?? '—'}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{secretaria.unidades_administrativas_count ?? 0}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <Link href={show.url(secretaria.id)} className="text-indigo-600 hover:text-indigo-900">Ver</Link>
                                            <Link href={edit.url(secretaria.id)} className="ml-3 text-yellow-600 hover:text-yellow-900">Editar</Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="px-6 py-4 text-center text-sm text-gray-500">No hay secretarías registradas.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {secretarias.links && (
                    <div className="mt-4 flex justify-center gap-1">
                        {secretarias.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={`inline-flex items-center rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                preserveScroll
                            >
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
