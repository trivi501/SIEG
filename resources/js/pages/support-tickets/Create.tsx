import { Head, Link, useForm } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import { store, index } from '@/routes/support-tickets';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets de Soporte', href: '/support-tickets' },
    { title: 'Crear', href: '/support-tickets/create' },
];

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        url: '',
        image: null as File | null,
        priority: 'media',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <>
            <Head title="Nuevo Ticket" />
            <div className="p-6">
                <div className="mx-auto max-w-2xl rounded-lg border bg-white p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Título</label>
                            <input type="text" value={data.title} onChange={(e) => setData('title', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                            {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={5}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">URL afectada</label>
                            <input type="url" value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://..."
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            {errors.url && <p className="mt-1 text-sm text-red-600">{errors.url}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Imagen (opcional)</label>
                            <input type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                                className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            {data.image && (
                                <div className="mt-2">
                                    <img src={URL.createObjectURL(data.image)} alt="Preview" className="max-h-48 rounded border" />
                                </div>
                            )}
                            {errors.image && <p className="mt-1 text-sm text-red-600">{errors.image}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Prioridad</label>
                            <select value={data.priority} onChange={(e) => setData('priority', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="baja">Baja</option>
                                <option value="media">Media</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-4">
                            <button type="submit" disabled={processing}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500 disabled:opacity-50">
                                {processing ? 'Guardando...' : 'Crear Ticket'}
                            </button>
                            <Link href={index.url()}
                                className="rounded-md bg-gray-100 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-200">
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
