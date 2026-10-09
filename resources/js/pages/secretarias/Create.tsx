import { Head, Link, useForm } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import { store, index } from '@/routes/secretarias';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Secretarías', href: '/secretarias' },
    { title: 'Crear', href: '/secretarias/create' },
];

interface UnidadAdministrativa {
    id_cat_egreso_unidad_administrativa: number;
    nombre: string;
    año: number | null;
    secretaria_id: number | null;
}

export default function Create({ unidadesAdministrativas }: { unidadesAdministrativas: UnidadAdministrativa[] }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        prefijo: '',
        unidades_administrativas: [] as number[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store.url());
    };

    const toggleUnidad = (id: number) => {
        const current = data.unidades_administrativas;
        setData('unidades_administrativas', current.includes(id) ? current.filter((x) => x !== id) : [...current, id]);
    };

    return (
        <>
            <Head title="Crear Secretaría" />
            <div className="p-6">
                <div className="mx-auto max-w-2xl rounded-lg border bg-white p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label htmlFor="nombre" className="block text-sm font-medium text-gray-700">Nombre</label>
                            <input id="nombre" type="text" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                            {errors.nombre && <p className="mt-1 text-sm text-red-600">{errors.nombre}</p>}
                        </div>

                        <div>
                            <label htmlFor="prefijo" className="block text-sm font-medium text-gray-700">Prefijo</label>
                            <input id="prefijo" type="text" value={data.prefijo} onChange={(e) => setData('prefijo', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" maxLength={10}
                                placeholder="Ej: SEG, OFI, DIR" />
                            {errors.prefijo && <p className="mt-1 text-sm text-red-600">{errors.prefijo}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Unidades administrativas (Requisiciones)</label>
                            <p className="mt-1 text-xs text-gray-500">Define qué requisiciones de egresos puede ver esta secretaría.</p>
                            <div className="mt-2 max-h-64 overflow-y-auto rounded-md border p-2">
                                {unidadesAdministrativas.length > 0 ? (
                                    unidadesAdministrativas.map((u) => (
                                        <label key={u.id_cat_egreso_unidad_administrativa} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-50">
                                            <input type="checkbox" checked={data.unidades_administrativas.includes(u.id_cat_egreso_unidad_administrativa)}
                                                onChange={() => toggleUnidad(u.id_cat_egreso_unidad_administrativa)}
                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                            <span className="text-sm text-gray-700">
                                                {u.nombre}{u.año ? ` (${u.año})` : ''}
                                                {u.secretaria_id ? <span className="ml-1 text-xs text-amber-600">ya asignada a otra secretaría</span> : null}
                                            </span>
                                        </label>
                                    ))
                                ) : (
                                    <p className="py-2 text-sm text-gray-500">No hay unidades administrativas disponibles.</p>
                                )}
                            </div>
                            {errors.unidades_administrativas && <p className="mt-1 text-sm text-red-600">{errors.unidades_administrativas}</p>}
                        </div>

                        <div className="flex items-center gap-4">
                            <button type="submit" disabled={processing}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500 disabled:opacity-50">
                                Guardar
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
