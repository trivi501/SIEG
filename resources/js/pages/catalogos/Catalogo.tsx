import { Head, router, useForm } from '@inertiajs/react';
import {
    Download,
    History,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Search,
    Upload,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import CambiosAuditoria, { AccionBadge } from '@/components/cambios-auditoria';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fechaLocal } from '@/lib/presupuesto';

type Valor = string | number | boolean | null;

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Campo {
    nombre: string;
    etiqueta: string;
    tipo:
        | 'texto'
        | 'texto_largo'
        | 'entero'
        | 'fecha'
        | 'booleano'
        | 'seleccion'
        | 'relacion'
        | 'calculado';
    requerido: boolean;
    max: number | null;
    enLista: boolean;
    editable: boolean;
    mayusculas: boolean;
    ayuda: string | null;
    porDefecto: Valor;
    opciones: Opcion[] | null;
}

interface CatalogoInfo {
    slug: string;
    titulo: string;
    singular: string;
    grupo: string;
    descripcion: string;
    tieneActivo: boolean;
    campos: Campo[];
    puede: {
        crear: boolean;
        editar: boolean;
        baja: boolean;
        importar: boolean;
    };
}

interface Registro {
    id: number | string;
    activo: boolean;
    valores: Record<string, Valor>;
    textos: Record<string, string | null>;
}

interface Paginado<T> {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Importacion {
    token: string;
    archivo: string;
    altas: number;
    modificaciones: number;
    sinCambios: number;
    errores: { fila: number; mensajes: string[] }[];
    totalErrores: number;
    faltantes: string[];
    columnasIgnoradas: string[];
}

interface Movimiento {
    id: number;
    fecha: string;
    usuario: string | null;
    accion: string;
    descripcion: string | null;
    antes: Record<string, unknown> | null;
    despues: Record<string, unknown> | null;
}

type Datos = Record<string, string | boolean>;

const inputClase =
    'block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm';

function valorInicial(campo: Campo, valor?: Valor): string | boolean {
    const v = valor === undefined ? campo.porDefecto : valor;

    if (campo.tipo === 'booleano') {
        return Boolean(v);
    }

    return v === null || v === undefined ? '' : String(v);
}

export default function Catalogo({
    catalogo,
    registros,
    filtros,
    importacion,
}: {
    catalogo: CatalogoInfo;
    registros: Paginado<Registro>;
    filtros: { buscar: string | null; estado: string | null };
    importacion: Importacion | null;
}) {
    const url = `/catalogos/${catalogo.slug}`;
    const editables = catalogo.campos.filter((c) => c.editable);
    const columnas = catalogo.campos.filter((c) => c.enLista);

    const [buscar, setBuscar] = useState(filtros?.buscar ?? '');
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Registro | null>(null);
    const [baja, setBaja] = useState<Registro | null>(null);
    const [historial, setHistorial] = useState<{
        registro: Registro;
        movimientos: Movimiento[] | null;
    } | null>(null);
    const [importarManual, setImportarManual] = useState(false);
    const [tokenCerrado, setTokenCerrado] = useState<string | null>(null);

    // Al volver de la vista previa del archivo el diálogo se abre solo con el resultado.
    const importarAbierto =
        importarManual ||
        (!!importacion?.token && importacion.token !== tokenCerrado);
    const setImportarAbierto = (abierto: boolean) => {
        setImportarManual(abierto);

        if (!abierto) {
            setTokenCerrado(importacion?.token ?? null);
        }
    };

    const vacio = (): Datos =>
        Object.fromEntries(editables.map((c) => [c.nombre, valorInicial(c)]));

    const form = useForm<Datos>(vacio());
    const archivo = useForm<{ archivo: File | null }>({ archivo: null });

    // campo → (valor → etiqueta) para el historial.
    const opciones = useMemo(
        () =>
            Object.fromEntries(
                catalogo.campos
                    .filter((c) => c.opciones)
                    .map((c) => [
                        c.nombre,
                        Object.fromEntries(
                            (c.opciones ?? []).map((o) => [
                                o.valor,
                                o.etiqueta,
                            ]),
                        ),
                    ]),
            ),
        [catalogo.campos],
    );
    const etiquetas = Object.fromEntries(
        catalogo.campos.map((c) => [c.nombre, c.etiqueta]),
    );

    const filtrar = (cambios: Partial<{ buscar: string; estado: string }>) => {
        const params = {
            buscar: buscar || undefined,
            estado: filtros?.estado || undefined,
            ...cambios,
        };

        router.get(
            url,
            Object.fromEntries(
                Object.entries(params).filter(([, v]) => v),
            ) as Record<string, string>,
            { preserveState: true, replace: true },
        );
    };

    const abrir = (registro: Registro | null) => {
        setEditando(registro);
        form.clearErrors();
        form.setData(
            registro
                ? Object.fromEntries(
                      editables.map((c) => [
                          c.nombre,
                          valorInicial(c, registro.valores[c.nombre] ?? null),
                      ]),
                  )
                : vacio(),
        );
        setAbierto(true);
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        const opcionesEnvio = {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        };

        if (editando) {
            form.put(`${url}/${editando.id}`, opcionesEnvio);
        } else {
            form.post(url, opcionesEnvio);
        }
    };

    const verHistorial = async (registro: Registro) => {
        setHistorial({ registro, movimientos: null });
        const respuesta = await fetch(`${url}/${registro.id}/historial`, {
            headers: { Accept: 'application/json' },
        });
        const movimientos = respuesta.ok
            ? ((await respuesta.json()) as Movimiento[])
            : [];
        setHistorial((h) =>
            h?.registro.id === registro.id ? { registro, movimientos } : h,
        );
    };

    const cambiarEstado = (registro: Registro, activo: boolean) =>
        router.post(
            `${url}/${registro.id}/estado`,
            { activo },
            { preserveScroll: true, onSuccess: () => setBaja(null) },
        );

    const revisarArchivo = (e: React.FormEvent) => {
        e.preventDefault();
        archivo.post(`${url}/importar/previa`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => archivo.reset(),
        });
    };

    const confirmarImportacion = () =>
        router.post(
            `${url}/importar`,
            { token: importacion?.token ?? '' },
            {
                preserveScroll: true,
                onSuccess: () => setImportarAbierto(false),
            },
        );

    const control = (campo: Campo) => {
        const valor = form.data[campo.nombre];
        const error = form.errors[campo.nombre];
        const ancho =
            campo.tipo === 'texto_largo' ||
            (campo.tipo === 'texto' && (campo.max ?? 0) > 200)
                ? 'sm:col-span-2'
                : '';

        if (campo.tipo === 'booleano') {
            return (
                <label
                    key={campo.nombre}
                    className="flex items-center gap-2 self-end py-2 text-sm"
                >
                    <input
                        type="checkbox"
                        checked={Boolean(valor)}
                        onChange={(e) =>
                            form.setData(campo.nombre, e.target.checked)
                        }
                        className="rounded border-input"
                    />
                    {campo.etiqueta}
                </label>
            );
        }

        let entrada: React.ReactNode;

        if (campo.tipo === 'seleccion' || campo.tipo === 'relacion') {
            entrada = (
                <SearchableSelect
                    inline
                    value={String(valor ?? '')}
                    onChange={(v) => form.setData(campo.nombre, v)}
                    options={[
                        ...(campo.requerido
                            ? []
                            : [{ value: '', label: '— Ninguno —' }]),
                        ...(campo.opciones ?? []).map((o) => ({
                            value: o.valor,
                            label: o.etiqueta,
                        })),
                    ]}
                    showValue={false}
                    panelWidth={360}
                    placeholder="Seleccionar..."
                />
            );
        } else if (campo.tipo === 'texto_largo') {
            entrada = (
                <textarea
                    value={String(valor ?? '')}
                    onChange={(e) => form.setData(campo.nombre, e.target.value)}
                    maxLength={campo.max ?? undefined}
                    rows={3}
                    className={inputClase}
                />
            );
        } else {
            entrada = (
                <input
                    type={
                        campo.tipo === 'entero'
                            ? 'number'
                            : campo.tipo === 'fecha'
                              ? 'date'
                              : 'text'
                    }
                    value={String(valor ?? '')}
                    onChange={(e) =>
                        form.setData(
                            campo.nombre,
                            campo.mayusculas
                                ? e.target.value.toUpperCase()
                                : e.target.value,
                        )
                    }
                    required={campo.requerido}
                    maxLength={
                        campo.tipo === 'texto'
                            ? (campo.max ?? undefined)
                            : undefined
                    }
                    className={inputClase}
                />
            );
        }

        return (
            <div key={campo.nombre} className={`space-y-1 ${ancho}`}>
                <label className="text-sm font-medium">
                    {campo.etiqueta}
                    {campo.requerido && (
                        <span className="text-destructive"> *</span>
                    )}
                </label>
                {entrada}
                {campo.ayuda && (
                    <p className="text-xs text-muted-foreground">
                        {campo.ayuda}
                    </p>
                )}
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
        );
    };

    const puedeImportar =
        !!importacion &&
        importacion.totalErrores === 0 &&
        importacion.faltantes.length === 0 &&
        importacion.altas + importacion.modificaciones > 0;

    return (
        <>
            <Head title={catalogo.titulo} />
            <div className="space-y-4 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-medium">
                            {catalogo.titulo}
                        </h3>
                        {catalogo.descripcion && (
                            <p className="text-sm text-muted-foreground">
                                {catalogo.descripcion}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {catalogo.puede.importar && (
                            <Button
                                variant="outline"
                                onClick={() => setImportarAbierto(true)}
                            >
                                <Upload className="mr-2 h-4 w-4" /> Importar
                            </Button>
                        )}
                        {catalogo.puede.crear && (
                            <Button onClick={() => abrir(null)}>
                                <Plus className="mr-2 h-4 w-4" /> Nuevo
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 font-normal">
                            <input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && filtrar({ buscar })
                                }
                                placeholder="Buscar..."
                                className="w-full rounded-md border border-input px-2 py-1 text-sm shadow-sm sm:w-72"
                            />
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => filtrar({ buscar })}
                            >
                                <Search className="h-4 w-4" />
                            </Button>
                            {filtros?.buscar && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setBuscar('');
                                        filtrar({ buscar: '' });
                                    }}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                            {catalogo.tieneActivo && (
                                <select
                                    value={filtros?.estado ?? ''}
                                    onChange={(e) =>
                                        filtrar({ estado: e.target.value })
                                    }
                                    className="rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="activos">Activos</option>
                                    <option value="inactivos">
                                        Dados de baja
                                    </option>
                                </select>
                            )}
                            <span className="ml-auto text-sm text-muted-foreground">
                                {registros.total} registros
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {columnas.map((c) => (
                                        <TableHead key={c.nombre}>
                                            {c.etiqueta}
                                        </TableHead>
                                    ))}
                                    {catalogo.tieneActivo && (
                                        <TableHead>Estatus</TableHead>
                                    )}
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {registros.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columnas.length + 2}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay registros.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {registros.data.map((r) => (
                                    <TableRow
                                        key={r.id}
                                        className={
                                            r.activo
                                                ? ''
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {columnas.map((c) => (
                                            <TableCell
                                                key={c.nombre}
                                                className={
                                                    c.nombre === 'clave'
                                                        ? 'font-mono text-xs'
                                                        : 'max-w-xs truncate'
                                                }
                                                title={r.textos[c.nombre] ?? ''}
                                            >
                                                {r.textos[c.nombre] ?? '—'}
                                            </TableCell>
                                        ))}
                                        {catalogo.tieneActivo && (
                                            <TableCell>
                                                {r.activo ? (
                                                    <Badge variant="secondary">
                                                        Activo
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Baja
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title="Historial de cambios"
                                                onClick={() => verHistorial(r)}
                                            >
                                                <History className="h-4 w-4" />
                                            </Button>
                                            {catalogo.puede.editar && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Editar"
                                                    onClick={() => abrir(r)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {catalogo.puede.baja &&
                                                catalogo.tieneActivo &&
                                                (r.activo ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Dar de baja"
                                                        onClick={() =>
                                                            setBaja(r)
                                                        }
                                                    >
                                                        <Power className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Reactivar"
                                                        onClick={() =>
                                                            cambiarEstado(
                                                                r,
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <RotateCcw className="h-4 w-4" />
                                                    </Button>
                                                ))}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {registros.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap gap-1">
                                {registros.links.map((l, i) => (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={
                                            l.active ? 'default' : 'outline'
                                        }
                                        disabled={!l.url}
                                        onClick={() =>
                                            l.url &&
                                            router.get(
                                                l.url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: l.label,
                                            }}
                                        />
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Alta / edición */}
            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={guardar} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editando ? 'Editar' : 'Nuevo'}:{' '}
                                {catalogo.singular}
                            </DialogTitle>
                            {catalogo.descripcion && (
                                <DialogDescription>
                                    {catalogo.descripcion}
                                </DialogDescription>
                            )}
                        </DialogHeader>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {editables.map(control)}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAbierto(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Guardar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Baja lógica */}
            <Dialog open={!!baja} onOpenChange={(o) => !o && setBaja(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Dar de baja este registro?</DialogTitle>
                        <DialogDescription>
                            No se borra: queda inactivo, deja de ofrecerse en
                            las capturas y se puede reactivar después. El cambio
                            queda en la bitácora.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setBaja(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => baja && cambiarEstado(baja, false)}
                        >
                            Dar de baja
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Historial */}
            <Dialog
                open={!!historial}
                onOpenChange={(o) => !o && setHistorial(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Historial de cambios</DialogTitle>
                        <DialogDescription>
                            {historial &&
                                columnas
                                    .slice(0, 2)
                                    .map(
                                        (c) =>
                                            historial.registro.textos[c.nombre],
                                    )
                                    .filter(Boolean)
                                    .join(' — ')}
                        </DialogDescription>
                    </DialogHeader>
                    {historial?.movimientos === null && (
                        <p className="text-sm text-muted-foreground">
                            Cargando...
                        </p>
                    )}
                    {historial?.movimientos?.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Sin cambios registrados. La bitácora empezó a
                            guardar movimientos con esta versión del sistema.
                        </p>
                    )}
                    <ol className="space-y-3">
                        {historial?.movimientos?.map((m) => (
                            <li
                                key={m.id}
                                className="rounded-md border p-3 text-sm"
                            >
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <AccionBadge accion={m.accion} />
                                    <span>{fechaLocal(m.fecha)}</span>
                                    <span className="text-muted-foreground">
                                        {m.usuario ?? 'Sistema'}
                                    </span>
                                </div>
                                {m.descripcion && (
                                    <p className="mb-1 text-xs text-muted-foreground">
                                        {m.descripcion}
                                    </p>
                                )}
                                <CambiosAuditoria
                                    antes={m.antes}
                                    despues={m.despues}
                                    etiquetas={etiquetas}
                                    opciones={opciones}
                                />
                            </li>
                        ))}
                    </ol>
                </DialogContent>
            </Dialog>

            {/* Importación masiva */}
            <Dialog open={importarAbierto} onOpenChange={setImportarAbierto}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Importar {catalogo.titulo}</DialogTitle>
                        <DialogDescription>
                            Descarga la plantilla, llénala y súbela. Primero se
                            revisa el archivo; nada se guarda hasta que
                            confirmes.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a href={`${url}/plantilla`}>
                                <Download className="mr-2 h-4 w-4" /> Descargar
                                plantilla
                            </a>
                        </Button>
                    </div>

                    <form
                        onSubmit={revisarArchivo}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) =>
                                archivo.setData(
                                    'archivo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            className="text-sm"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                !archivo.data.archivo || archivo.processing
                            }
                        >
                            {archivo.processing
                                ? 'Revisando...'
                                : 'Revisar archivo'}
                        </Button>
                        {archivo.errors.archivo && (
                            <p className="w-full text-sm text-destructive">
                                {archivo.errors.archivo}
                            </p>
                        )}
                    </form>

                    {importacion && (
                        <div className="space-y-3 rounded-md border p-3 text-sm">
                            <p className="font-medium">{importacion.archivo}</p>

                            {importacion.faltantes.length > 0 ? (
                                <p className="text-destructive">
                                    Faltan columnas obligatorias:{' '}
                                    {importacion.faltantes.join(', ')}. Usa la
                                    plantilla.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    <Badge>{importacion.altas} altas</Badge>
                                    <Badge variant="secondary">
                                        {importacion.modificaciones}{' '}
                                        modificaciones
                                    </Badge>
                                    <Badge variant="outline">
                                        {importacion.sinCambios} sin cambios
                                    </Badge>
                                    {importacion.totalErrores > 0 && (
                                        <Badge variant="destructive">
                                            {importacion.totalErrores} con
                                            errores
                                        </Badge>
                                    )}
                                </div>
                            )}

                            {importacion.columnasIgnoradas.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Columnas que no se reconocieron y se
                                    ignoran:{' '}
                                    {importacion.columnasIgnoradas.join(', ')}
                                </p>
                            )}

                            {importacion.errores.length > 0 && (
                                <div className="max-h-64 overflow-y-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-20">
                                                    Renglón
                                                </TableHead>
                                                <TableHead>Error</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {importacion.errores.map((e) => (
                                                <TableRow key={e.fila}>
                                                    <TableCell>
                                                        {e.fila}
                                                    </TableCell>
                                                    <TableCell className="text-destructive">
                                                        {e.mensajes.join(' ')}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                    {importacion.totalErrores >
                                        importacion.errores.length && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Se muestran los primeros{' '}
                                            {importacion.errores.length}.
                                        </p>
                                    )}
                                </div>
                            )}

                            {importacion.totalErrores > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Corrige el archivo y vuelve a subirlo: no se
                                    importa nada mientras tenga errores.
                                </p>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setImportarAbierto(false)}
                        >
                            Cerrar
                        </Button>
                        {importacion && (
                            <Button
                                disabled={!puedeImportar}
                                onClick={confirmarImportacion}
                            >
                                Importar{' '}
                                {importacion.altas + importacion.modificaciones}{' '}
                                registros
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Catalogo.layout = (props: { catalogo: CatalogoInfo }) => ({
    breadcrumbs: [
        { title: 'Catálogos', href: '/catalogos' },
        {
            title: props.catalogo.titulo,
            href: `/catalogos/${props.catalogo.slug}`,
        },
    ],
});
