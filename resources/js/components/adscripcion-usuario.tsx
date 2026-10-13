import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const TITULOS = [
    'C.',
    'L.C.',
    'C.P.',
    'LIC.',
    'ING.',
    'ARQ.',
    'MTRO.',
    'MTRA.',
    'DR.',
    'DRA.',
    'PROFR.',
    'PROFRA.',
];

const claseSelect =
    'block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:border-ring focus:ring-ring';

/**
 * Datos del usuario para los formatos: título, cargo y a quién pertenece — a una secretaría o
 * dirección, o a un regidor (sus enlaces; el regidor firma sus requisiciones y CLC).
 */
export default function AdscripcionUsuario({
    unidades,
    regidores,
    errors,
    inicial = {},
}: {
    unidades: { id: number; nombre: string }[];
    regidores: { id: number; nombre: string }[];
    errors: Record<string, string>;
    inicial?: {
        titulo?: string | null;
        cargo?: string | null;
        id_cat_egreso_unidad_administrativa?: number | null;
        regidor_id?: number | null;
    };
}) {
    const [pertenece, setPertenece] = useState<'secretaria' | 'regidor'>(
        inicial.regidor_id ? 'regidor' : 'secretaria',
    );

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-[160px_1fr]">
                <div className="grid gap-2">
                    <Label htmlFor="titulo">Título</Label>
                    <Input
                        id="titulo"
                        name="titulo"
                        list="titulos-usuario"
                        defaultValue={inicial.titulo ?? ''}
                        placeholder="L.C., LIC., MTRA."
                        maxLength={30}
                    />
                    <datalist id="titulos-usuario">
                        {TITULOS.map((t) => (
                            <option key={t} value={t} />
                        ))}
                    </datalist>
                    {errors.titulo && (
                        <p className="text-sm text-red-500">{errors.titulo}</p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="cargo">Cargo</Label>
                    <Input
                        id="cargo"
                        name="cargo"
                        defaultValue={inicial.cargo ?? ''}
                        placeholder="Ej. ENLACE ADMINISTRATIVO"
                        maxLength={150}
                    />
                    {errors.cargo && (
                        <p className="text-sm text-red-500">{errors.cargo}</p>
                    )}
                </div>
            </div>

            <div className="grid gap-2">
                <Label>Pertenece a</Label>
                <input type="hidden" name="pertenece" value={pertenece} />
                <div className="flex flex-wrap gap-2">
                    {(
                        [
                            ['secretaria', 'Secretaría o dirección'],
                            ['regidor', 'Regidor'],
                        ] as const
                    ).map(([valor, texto]) => (
                        <button
                            key={valor}
                            type="button"
                            onClick={() => setPertenece(valor)}
                            className={`rounded-md border px-4 py-2 text-sm ${pertenece === valor ? 'border-primary bg-primary/10 font-semibold text-primary' : 'text-muted-foreground hover:bg-muted'}`}
                        >
                            {texto}
                        </button>
                    ))}
                </div>

                {pertenece === 'secretaria' ? (
                    <>
                        <select
                            id="id_cat_egreso_unidad_administrativa"
                            name="id_cat_egreso_unidad_administrativa"
                            defaultValue={
                                inicial.regidor_id
                                    ? ''
                                    : (inicial.id_cat_egreso_unidad_administrativa ??
                                      '')
                            }
                            className={claseSelect}
                            aria-label="Secretaría o dirección"
                        >
                            <option value="">Sin asignar</option>
                            {unidades.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nombre}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            Solo verá las requisiciones y el presupuesto de esta
                            secretaría o dirección; firma por el área su
                            titular.
                        </p>
                        {errors.id_cat_egreso_unidad_administrativa && (
                            <p className="text-sm text-red-500">
                                {errors.id_cat_egreso_unidad_administrativa}
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <select
                            id="regidor_id"
                            name="regidor_id"
                            defaultValue={inicial.regidor_id ?? ''}
                            className={claseSelect}
                            aria-label="Regidor"
                        >
                            <option value="">Seleccionar…</option>
                            {regidores.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.nombre}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            Es enlace de este regidor: él firma sus
                            requisiciones y CLC. Para el presupuesto queda en
                            Cabildo.
                            {regidores.length === 0 &&
                                ' Primero da de alta los regidores en Catálogos → Regidores.'}
                        </p>
                        {errors.regidor_id && (
                            <p className="text-sm text-red-500">
                                {errors.regidor_id}
                            </p>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
