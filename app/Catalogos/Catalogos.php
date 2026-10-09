<?php

namespace App\Catalogos;

use App\Models\Banco;
use App\Models\Departamento;
use App\Models\EgresoFuenteFinanciamiento;
use App\Models\EgresoObjetoGasto;
use App\Models\EgresoPrograma;
use App\Models\EgresoProyecto;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\EjercicioFiscal;
use App\Models\Firmante;
use App\Models\Proveedor;
use App\Models\ProveedorCuenta;
use App\Models\Secretaria;
use App\Models\TipoDocumento;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de los catálogos administrables. Agregar uno aquí le da pantalla, validación,
 * importación desde Excel, historial y permisos (después de `php artisan permissions:sync`).
 */
final class Catalogos
{
    public const GRUPOS = [
        'Estructura orgánica',
        'Clasificación presupuestal',
        'Ejercicios y documentos',
        'Proveedores y bancos',
        'Padrón vehicular',
    ];

    /** @var array<string, Catalogo>|null */
    private static ?array $catalogos = null;

    /** @return array<string, Catalogo> */
    public static function todos(): array
    {
        return self::$catalogos ??= collect(self::definir())->keyBy('slug')->all();
    }

    public static function buscar(string $slug): ?Catalogo
    {
        return self::todos()[$slug] ?? null;
    }

    /** Catálogo al que pertenece un modelo (para la bitácora de auditoría). */
    public static function deModelo(string $modelo): ?Catalogo
    {
        foreach (self::todos() as $catalogo) {
            if ($catalogo->modelo === $modelo) {
                return $catalogo;
            }
        }

        return null;
    }

    public static function permisos(): array
    {
        return collect(self::todos())->flatMap(fn (Catalogo $c) => $c->permisos())->unique()->values()->all();
    }

    /** @return Catalogo[] */
    private static function definir(): array
    {
        return [
            // Estructura orgánica
            new Catalogo(
                slug: 'unidades-administrativas',
                titulo: 'Direcciones (unidades administrativas)',
                singular: 'dirección',
                grupo: 'Estructura orgánica',
                descripcion: 'Centros gestores del presupuesto. La clave es la de la columna CLAVE del presupuesto importado.',
                modelo: EgresoUnidadAdministrativa::class,
                campos: [
                    Campo::texto('clave', 'Clave')->requerido()->max(20),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(1500)->mayusculas(),
                    Campo::relacion('secretaria_id', 'Secretaría', Secretaria::class, ['nombre']),
                    Campo::entero('año', 'Año')->requerido()->min(2000)->max(2100)->porDefecto(fn () => (int) date('Y')),
                    Campo::texto('responsable', 'Responsable (titular)')->max(200),
                    Campo::texto('cargo_responsable', 'Cargo del responsable')->max(200)->oculto(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre', 'responsable'],
                llave: ['clave', 'año'],
                valoresFijos: ['id_cat_area_x_nombre_y_puesto' => 1],
                orden: ['año' => 'desc', 'clave' => 'asc'],
            ),
            new Catalogo(
                slug: 'departamentos',
                titulo: 'Departamentos',
                singular: 'departamento',
                grupo: 'Estructura orgánica',
                descripcion: 'Áreas dentro de cada dirección.',
                modelo: Departamento::class,
                campos: [
                    Campo::relacion('id_cat_egreso_unidad_administrativa', 'Dirección', EgresoUnidadAdministrativa::class, ['clave', 'nombre'], 'clave')->requerido(),
                    Campo::texto('clave', 'Clave')->max(20),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(300)->mayusculas(),
                    Campo::texto('responsable', 'Responsable')->max(200),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre', 'responsable', 'unidadAdministrativa.nombre'],
                llave: ['id_cat_egreso_unidad_administrativa', 'nombre'],
            ),
            new Catalogo(
                slug: 'firmantes',
                titulo: 'Responsables y firmantes',
                singular: 'firmante',
                grupo: 'Estructura orgánica',
                descripcion: 'Quién firma cada documento impreso. Si se indica dirección, tiene prioridad sobre el firmante general para esa dirección.',
                modelo: Firmante::class,
                campos: [
                    Campo::relacion('tipo_documento_id', 'Documento', TipoDocumento::class, ['nombre'], 'clave')->requerido(),
                    Campo::seleccion('rol', 'Firma como', Firmante::ROLES)->requerido(),
                    Campo::relacion('id_cat_egreso_unidad_administrativa', 'Dirección', EgresoUnidadAdministrativa::class, ['clave', 'nombre'], 'clave')
                        ->ayuda('Vacío = aplica a todas las direcciones.'),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(200),
                    Campo::texto('cargo', 'Cargo')->requerido()->max(200),
                    Campo::fecha('vigente_desde', 'Vigente desde')->requerido()->porDefecto(fn () => now()->toDateString()),
                    Campo::fecha('vigente_hasta', 'Vigente hasta')->reglas(['after_or_equal:vigente_desde'])->ayuda('Vacío = sigue vigente.'),
                    Campo::activo(),
                ],
                buscar: ['nombre', 'cargo', 'tipoDocumento.nombre'],
                llave: [],
                orden: ['tipo_documento_id' => 'asc', 'rol' => 'asc', 'vigente_desde' => 'desc'],
            ),

            // Clasificación presupuestal
            new Catalogo(
                slug: 'objeto-gasto',
                titulo: 'Clasificador por Objeto del Gasto',
                singular: 'partida',
                grupo: 'Clasificación presupuestal',
                descripcion: 'Partidas específicas (CONAC). Capítulo, concepto y partida genérica se derivan de la clave.',
                modelo: EgresoObjetoGasto::class,
                campos: [
                    Campo::texto('clave', 'Partida')->requerido()->max(20)->unico(),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(1500)->mayusculas(),
                    Campo::calculado('capitulo', 'Capítulo', fn ($r) => EgresoObjetoGasto::niveles($r->clave)['capitulo']),
                    Campo::calculado('concepto', 'Concepto', fn ($r) => EgresoObjetoGasto::niveles($r->clave)['concepto']),
                    Campo::calculado('generica', 'Partida genérica', fn ($r) => EgresoObjetoGasto::niveles($r->clave)['generica']),
                    Campo::seleccion('tipo_gasto', 'Tipo de gasto', EgresoObjetoGasto::TIPOS_GASTO)->requerido()->porDefecto('1'),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre'],
                orden: ['clave' => 'asc'],
            ),
            new Catalogo(
                slug: 'fuentes-financiamiento',
                titulo: 'Fuentes de financiamiento',
                singular: 'fuente de financiamiento',
                grupo: 'Clasificación presupuestal',
                descripcion: 'La clave es la de la columna CLAVE3 del presupuesto importado.',
                modelo: EgresoFuenteFinanciamiento::class,
                campos: [
                    Campo::texto('clave', 'Clave')->requerido()->max(20)->unico(),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(1500)->mayusculas(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre'],
                orden: ['clave' => 'asc'],
            ),
            new Catalogo(
                slug: 'programas',
                titulo: 'Programas presupuestarios',
                singular: 'programa',
                grupo: 'Clasificación presupuestal',
                modelo: EgresoPrograma::class,
                campos: [
                    Campo::texto('clave', 'Clave')->requerido()->max(20)->unico(),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(500)->mayusculas(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre'],
                orden: ['clave' => 'asc'],
            ),
            new Catalogo(
                slug: 'proyectos',
                titulo: 'Proyectos',
                singular: 'proyecto',
                grupo: 'Clasificación presupuestal',
                descripcion: 'La clave es la de la columna CLAVE6 del presupuesto importado.',
                modelo: EgresoProyecto::class,
                campos: [
                    Campo::texto('clave', 'Clave')->requerido()->max(20)->unico(),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(500)->mayusculas(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre'],
                orden: ['clave' => 'asc'],
            ),

            // Ejercicios y documentos
            new Catalogo(
                slug: 'ejercicios',
                titulo: 'Ejercicios fiscales',
                singular: 'ejercicio fiscal',
                grupo: 'Ejercicios y documentos',
                descripcion: 'Un ejercicio cerrado ya no admite requisiciones nuevas.',
                modelo: EjercicioFiscal::class,
                campos: [
                    Campo::entero('año', 'Año')->requerido()->min(2000)->max(2100)->unico()->porDefecto(fn () => (int) date('Y')),
                    Campo::texto('descripcion', 'Descripción')->max(200),
                    Campo::seleccion('estado', 'Estado', ['abierto' => 'Abierto', 'cerrado' => 'Cerrado'])->requerido()->porDefecto('abierto'),
                    Campo::activo(),
                ],
                buscar: ['año', 'descripcion'],
                llave: ['año'],
                orden: ['año' => 'desc'],
            ),
            new Catalogo(
                slug: 'tipos-documento',
                titulo: 'Tipos de documento',
                singular: 'tipo de documento',
                grupo: 'Ejercicios y documentos',
                descripcion: 'Las claves requisicion, orden_compra y modificacion_presupuestal las usan los PDF para buscar a sus firmantes.',
                modelo: TipoDocumento::class,
                campos: [
                    Campo::texto('clave', 'Clave')->requerido()->max(40)->unico()->regex('/^[a-z0-9_]+$/', 'La clave solo admite minúsculas, números y guion bajo.'),
                    Campo::texto('nombre', 'Nombre')->requerido()->max(150),
                    Campo::texto('prefijo', 'Prefijo de folio')->max(10)->mayusculas(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'nombre'],
            ),

            // Proveedores y bancos
            new Catalogo(
                slug: 'proveedores',
                titulo: 'Proveedores',
                singular: 'proveedor',
                grupo: 'Proveedores y bancos',
                descripcion: 'El RFC se usa para validar que la factura (XML) la emita este proveedor.',
                modelo: Proveedor::class,
                permiso: 'proveedores',
                campos: [
                    Campo::texto('nombre', 'Nombre o razón social')->requerido()->max(300),
                    Campo::texto('rfc', 'RFC')->max(13)->unico()->mayusculas()->regex('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', 'El RFC no tiene un formato válido.'),
                    Campo::texto('correo', 'Correo')->max(150)->reglas(['email']),
                    Campo::texto('telefono', 'Teléfono')->max(30),
                    Campo::texto('domicilio', 'Domicilio')->max(500)->oculto(),
                    Campo::activo(),
                ],
                buscar: ['nombre', 'rfc'],
                llave: ['rfc'],
            ),
            new Catalogo(
                slug: 'cuentas-bancarias',
                titulo: 'Cuentas bancarias de proveedores',
                singular: 'cuenta bancaria',
                grupo: 'Proveedores y bancos',
                descripcion: 'El banco se toma de los 3 primeros dígitos de la CLABE si no se indica.',
                modelo: ProveedorCuenta::class,
                campos: [
                    Campo::relacion('proveedor_id', 'Proveedor', Proveedor::class, ['nombre', 'rfc'], 'rfc')->requerido()
                        ->ayuda('Al importar, el proveedor se identifica por su RFC.'),
                    Campo::relacion('id_cat_banco', 'Banco', Banco::class, ['clave', 'descripcion'], 'clave'),
                    Campo::texto('clabe', 'CLABE')->max(18)->unico()->regex('/^\d{18}$/', 'La CLABE debe tener 18 dígitos.'),
                    Campo::texto('cuenta', 'Número de cuenta')->max(20),
                    Campo::texto('sucursal', 'Sucursal')->max(100)->oculto(),
                    Campo::booleano('principal', 'Cuenta principal', false),
                    Campo::activo(),
                ],
                buscar: ['clabe', 'cuenta', 'proveedor.nombre', 'proveedor.rfc'],
                llave: ['clabe'],
                orden: ['proveedor_id' => 'asc'],
                preparar: function (array $datos) {
                    if (empty($datos['id_cat_banco']) && ! empty($datos['clabe']) && Schema::hasTable('cat_banco')) {
                        $datos['id_cat_banco'] = Banco::where('clave', substr($datos['clabe'], 0, 3))->value('id_cat_banco');
                    }

                    return $datos;
                },
                validar: function (array $datos) {
                    $errores = [];

                    if (empty($datos['clabe']) && empty($datos['cuenta'])) {
                        $errores['clabe'] = 'Captura la CLABE o el número de cuenta.';
                    }

                    if (! empty($datos['clabe']) && ! ProveedorCuenta::clabeValida($datos['clabe'])) {
                        $errores['clabe'] = 'La CLABE no es válida (el dígito verificador no coincide).';
                    }

                    return $errores;
                },
                despuesDeGuardar: function (ProveedorCuenta $cuenta) {
                    // Solo una cuenta principal por proveedor.
                    if ($cuenta->principal) {
                        ProveedorCuenta::where('proveedor_id', $cuenta->proveedor_id)
                            ->whereKeyNot($cuenta->getKey())
                            ->where('principal', true)
                            ->get()
                            ->each(fn ($otra) => $otra->update(['principal' => false]));
                    }
                },
            ),
            new Catalogo(
                slug: 'bancos',
                titulo: 'Bancos',
                singular: 'banco',
                grupo: 'Proveedores y bancos',
                descripcion: 'La clave de 3 dígitos es con la que empieza la CLABE.',
                modelo: Banco::class,
                campos: [
                    Campo::texto('clave', 'Clave')->max(3)->unico()->regex('/^\d{3}$/', 'La clave del banco tiene 3 dígitos.'),
                    Campo::texto('descripcion', 'Nombre')->requerido()->max(500)->mayusculas(),
                    Campo::activo(),
                ],
                buscar: ['clave', 'descripcion'],
                orden: ['descripcion' => 'asc'],
            ),

            // Padrón vehicular
            new Catalogo(
                slug: 'vehiculos',
                titulo: 'Padrón vehicular',
                singular: 'vehículo',
                grupo: 'Padrón vehicular',
                descripcion: 'Los cambios de resguardante y de secretaría quedan en el historial de cada vehículo.',
                modelo: Vehiculo::class,
                campos: [
                    Campo::texto('numero_economico', 'Número económico')->requerido()->max(20)->unico()->mayusculas(),
                    Campo::texto('placas', 'Placas')->max(15)->unico()->mayusculas(),
                    Campo::texto('serie', 'Número de serie (NIV)')->max(30)->unico()->mayusculas()->oculto(),
                    Campo::texto('marca', 'Marca')->max(60)->mayusculas(),
                    Campo::texto('linea', 'Línea')->max(80)->mayusculas(),
                    Campo::entero('modelo', 'Modelo (año)')->min(1950)->max(2100),
                    Campo::texto('color', 'Color')->max(40)->mayusculas()->oculto(),
                    Campo::texto('tipo', 'Tipo')->max(40)->mayusculas()->oculto(),
                    Campo::relacion('secretaria_id', 'Secretaría asignada', Secretaria::class, ['nombre']),
                    Campo::texto('resguardante', 'Resguardante')->max(200)->mayusculas(),
                    Campo::texto('cargo_resguardante', 'Cargo del resguardante')->max(200)->oculto(),
                    Campo::texto('numero_resguardo', 'Número de resguardo')->max(40)->oculto(),
                    Campo::fecha('fecha_resguardo', 'Fecha de resguardo')->oculto(),
                    Campo::seleccion('estado', 'Estado', Vehiculo::ESTADOS)->requerido()->porDefecto('en_servicio'),
                    Campo::textoLargo('observaciones', 'Observaciones')->max(2000),
                    Campo::activo(),
                ],
                buscar: ['numero_economico', 'placas', 'serie', 'marca', 'resguardante'],
                llave: ['numero_economico'],
                orden: ['numero_economico' => 'asc'],
            ),
        ];
    }
}
