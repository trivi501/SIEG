import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { BreadcrumbItem } from '@/types';
import { index, create, show } from '@/routes/support-tickets';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets de Soporte', href: '/support-tickets' },
];

interface User {
    id: number;
    name: string;
}

interface Ticket {
    id: number;
    title: string;
    priority: string;
    status: string;
    created_at: string;
    user: User | null;
    assigned_user: User | null;
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

interface Filters {
    search?: string;
    status?: string;
    priority?: string;
}

const statusColors: Record<string, string> = {
    abierto: 'bg-blue-100 text-blue-800',
    en_proceso: 'bg-yellow-100 text-yellow-800',
    resuelto: 'bg-green-100 text-green-800',
    cerrado: 'bg-gray-100 text-gray-800',
};

const priorityColors: Record<string, string> = {
    baja: 'bg-gray-100 text-gray-600',
    media: 'bg-blue-100 text-blue-600',
    alta: 'bg-orange-100 text-orange-600',
    urgente: 'bg-red-100 text-red-600',
};

export default function Index({ tickets, filters: initialFilters, users }: { tickets: PaginatedData<Ticket>; filters: Filters; users: User[] }) {
    const [filters, setFilters] = useState<Filters>(initialFilters ?? {});

    const setFilter = (key: string, value: string) => {
        setFilters(prev => ({ ...prev, [key]: value }));
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get(index.url(), params);
    };

    const formatDateTime = (iso: string) => {
        if (!iso) return '';
        const d = new Date(iso);
        const pad = (n: number) => String(n).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };

    return (
        <>
            <Head title="Tickets de Soporte" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h3 className="text-lg font-medium">Mis Tickets</h3>
                    <Link href={create.url()}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500">
                        + Nuevo Ticket
                    </Link>
                </div>

                <form onSubmit={handleSearch} className="mb-4">
                    <div className="flex flex-wrap gap-2">
                        <input type="text" value={filters.search ?? ''} onChange={(e) => setFilter('search', e.target.value)}
                            placeholder="Buscar por título..."
                            className="block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <select value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los estados</option>
                            <option value="abierto">Abierto</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="resuelto">Resuelto</option>
                            <option value="cerrado">Cerrado</option>
                        </select>
                        <select value={filters.priority ?? ''} onChange={(e) => setFilter('priority', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas las prioridades</option>
                            <option value="baja">Baja</option>
                            <option value="media">Media</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                        <button type="submit"
                            className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                            Filtrar
                        </button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Título</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Creado por</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Asignado a</th>
                                <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Prioridad</th>
                                <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Estatus</th>
                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Fecha</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {tickets.data?.length > 0 ? (
                                tickets.data.map((ticket) => (
                                    <tr key={ticket.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => router.visit(show.url(ticket.id))}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">#{ticket.id}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{ticket.title}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{ticket.user?.name ?? '—'}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{ticket.assigned_user?.name ?? '—'}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-center">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${priorityColors[ticket.priority] ?? ''}`}>
                                                {ticket.priority}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-center">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColors[ticket.status] ?? ''}`}>
                                                {ticket.status === 'en_proceso' ? 'En Proceso' : ticket.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">{formatDateTime(ticket.created_at)}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={7} className="px-6 py-4 text-center text-sm text-gray-500">No hay tickets registrados.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {tickets.links && (
                    <div className="mt-4 flex justify-center gap-1">
                        {tickets.links.map((link, i) => (
                            <Link key={i} href={link.url ?? '#'}
                                className={`inline-flex items-center rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                preserveScroll>
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
