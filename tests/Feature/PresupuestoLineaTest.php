<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\ClcMovimiento;
use App\Models\ClcRelacion;
use App\Models\CorridaSaldo;
use App\Models\EgresoRequisicion;
use App\Models\EjercicioFiscal;
use App\Models\Firmante;
use App\Models\ModificacionPresupuestal;
use App\Models\OrdenCompra;
use App\Models\PresupuestoEgreso;
use App\Models\Proveedor;
use App\Models\Regidor;
use App\Models\SaldoMensual;
use App\Models\User;
use App\Services\DistribucionMensual;
use App\Services\PresupuestoService;
use App\Services\SaldosMensuales;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Alta de partidas y regla de distribución mensual. Las tablas del presupuesto y sus catálogos son del
 * sistema anterior (no las crea ninguna migración), así que la prueba arma una versión mínima.
 */
class PresupuestoLineaTest extends TestCase
{
    use RefreshDatabase;

    private const MESES = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEP', 'OCTUBRE', 'NOV', 'DIC'];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('presupuesto 2024', function (Blueprint $t) {
            $t->bigIncrements('id_presupuesto');
            foreach (['CLAVE', 'UNIDAD_ADMINISTRATIVA', 'CLAVE3', 'FUENTE_DE_FINANCIAMIENTO', 'CLAVE6', 'PROYECTO', 'PARTIDA', 'capitulo', 'TIPO_DE_GASTO', 'NOMBRE_PARTIDA', 'id'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['IMPORTE_TOTAL', ...self::MESES] as $c) {
                $t->decimal($c, 15, 2)->nullable();
            }
        });

        Schema::create('cat_egreso_unidad_administrativa', function (Blueprint $t) {
            $t->smallInteger('id_cat_egreso_unidad_administrativa')->primary();
            $t->unsignedBigInteger('secretaria_id')->nullable();
            $t->string('clave');
            $t->string('nombre');
            $t->smallInteger('año');
            $t->boolean('activo');
            $t->smallInteger('id_cat_area_x_nombre_y_puesto')->default(1);
            $t->string('responsable')->nullable();
            $t->string('cargo_responsable')->nullable();
            $t->string('prefijo')->nullable();
        });

        foreach (['fuente_financiamiento', 'proyecto'] as $catalogo) {
            Schema::create("cat_egreso_{$catalogo}", function (Blueprint $t) use ($catalogo) {
                $t->integer("id_cat_egreso_{$catalogo}")->primary();
                $t->string('clave');
                $t->string('nombre');
                $t->boolean('activo');
            });
        }

        Schema::create('cat_egreso_objeto_gasto', function (Blueprint $t) {
            $t->integer('id_cat_egreso_objeto_gasto')->primary();
            $t->string('clave');
            $t->tinyInteger('tipo_gasto');
            $t->string('nombre');
            $t->boolean('activo');
        });

        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 6, 'clave' => '310', 'nombre' => 'TESORERÍA', 'año' => 2024, 'activo' => true]);
        DB::table('cat_egreso_fuente_financiamiento')->insert(['id_cat_egreso_fuente_financiamiento' => 1, 'clave' => '561', 'nombre' => 'PARTICIPACIONES', 'activo' => true]);
        DB::table('cat_egreso_proyecto')->insert(['id_cat_egreso_proyecto' => 1, 'clave' => '101001', 'nombre' => 'GASTOS ADMINISTRATIVOS', 'activo' => true]);
        DB::table('cat_egreso_objeto_gasto')->insert(['id_cat_egreso_objeto_gasto' => 1, 'clave' => '2111', 'tipo_gasto' => 1, 'nombre' => 'MATERIALES DE OFICINA', 'activo' => true]);

        $this->artisan('permissions:sync');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    private function linea(array $cambios = []): array
    {
        return [
            'CLAVE' => '310', 'CLAVE3' => '561', 'CLAVE6' => '101001', 'PARTIDA' => '2111',
            'IMPORTE_TOTAL' => '1200.12',
            ...array_fill_keys(self::MESES, '100.01'),
            ...$cambios,
        ];
    }

    public function test_alta_de_partida_toma_los_nombres_de_los_catalogos()
    {
        $this->get('/presupuesto/crear')->assertOk();

        $this->post('/presupuesto', $this->linea())->assertSessionHasNoErrors()->assertRedirect();

        $linea = PresupuestoEgreso::firstOrFail();
        $this->assertSame('TESORERÍA', $linea->UNIDAD_ADMINISTRATIVA);
        $this->assertSame('PARTICIPACIONES', $linea->FUENTE_DE_FINANCIAMIENTO);
        $this->assertSame('GASTOS ADMINISTRATIVOS', $linea->PROYECTO);
        $this->assertSame('MATERIALES DE OFICINA', $linea->NOMBRE_PARTIDA);
        $this->assertSame('2000', $linea->capitulo);
        $this->assertEquals(1200.12, $linea->IMPORTE_TOTAL);
        $this->assertTrue(Auditoria::where('auditable_type', PresupuestoEgreso::class)->where('accion', 'alta')->exists());
    }

    public function test_la_distribucion_no_puede_tener_ni_de_mas_ni_de_menos()
    {
        // Un centavo de menos.
        $this->post('/presupuesto', $this->linea(['DIC' => '100.00']))->assertSessionHasErrors('distribucion');
        // Un centavo de más.
        $this->post('/presupuesto', $this->linea(['DIC' => '100.02']))->assertSessionHasErrors('distribucion');
        // Sin distribuir.
        $this->post('/presupuesto', $this->linea(array_fill_keys(self::MESES, '')))->assertSessionHasErrors('distribucion');

        $this->assertSame(0, PresupuestoEgreso::count());
    }

    public function test_claves_fuera_de_catalogo_y_lineas_repetidas()
    {
        $this->post('/presupuesto', $this->linea(['PARTIDA' => '9999', 'CLAVE3' => '000']))
            ->assertSessionHasErrors(['PARTIDA', 'CLAVE3']);

        $this->post('/presupuesto', $this->linea())->assertSessionHasNoErrors();
        $this->post('/presupuesto', $this->linea())->assertSessionHasErrors('PARTIDA');

        $this->assertSame(1, PresupuestoEgreso::count());
    }

    public function test_editar_tambien_exige_que_cuadre()
    {
        $this->post('/presupuesto', $this->linea());
        $linea = PresupuestoEgreso::firstOrFail();

        $this->patch("/presupuesto/{$linea->id_presupuesto}", $this->linea(['IMPORTE_TOTAL' => '1300']))
            ->assertSessionHasErrors('distribucion');

        $this->patch("/presupuesto/{$linea->id_presupuesto}", $this->linea(['IMPORTE_TOTAL' => '1300.12', 'DIC' => '200.01']))
            ->assertSessionHasNoErrors();
        $this->assertEquals(1300.12, $linea->fresh()->IMPORTE_TOTAL);
    }

    public function test_importacion_rechaza_todo_si_un_renglon_no_cuadra()
    {
        $encabezados = ['CLAVE', 'CLAVE3', 'CLAVE6', 'PARTIDA', 'IMPORTE TOTAL', ...self::MESES];
        $bueno = ['310', '561', '101001', '2111', 120, ...array_fill(0, 12, 10)];
        $malo = ['310', '561', '101001', '2112', 125, ...array_fill(0, 12, 10)];

        $this->post('/presupuesto/import', ['file' => $this->excel([$encabezados, $bueno, $malo])])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se importó nada') && str_contains($m, 'renglón 3'));
        $this->assertSame(0, PresupuestoEgreso::count());

        $this->post('/presupuesto/import', ['file' => $this->excel([$encabezados, $bueno])])->assertSessionHas('success');
        $this->assertSame(1, PresupuestoEgreso::count());
    }

    /** Tablas mínimas de requisiciones (del sistema anterior) para los saldos y momentos del gasto. */
    private function crearTablasRequisicion(): void
    {
        Schema::create('tb_egreso_requisicion', function (Blueprint $t) {
            $t->integer('id_tb_egreso_requisicion')->primary();
            $t->smallInteger('id_cat_egreso_unidad_administrativa');
            $t->integer('id_cat_egreso_requisicion_estado');
            $t->boolean('suficiencia')->nullable();
            $t->smallInteger('año')->nullable();
            $t->date('solicitado')->nullable();
            $t->dateTime('cancelada_at')->nullable();
            $t->unsignedBigInteger('cancelada_user_id')->nullable();
            $t->string('motivo_cancelacion', 1000)->nullable();
            $t->dateTime('suficiencia_fecha')->nullable();
            $t->unsignedBigInteger('suficiencia_user_id')->nullable();
            $t->boolean('extemporanea')->default(false);
            $t->unsignedBigInteger('extemporanea_user_id')->nullable();
            $t->dateTime('extemporanea_autorizada_at')->nullable();
            $t->decimal('total', 15, 2)->nullable();
        });
        Schema::create('tb_egreso_requisicion_detalle', function (Blueprint $t) {
            $t->integer('id_tb_egreso_requisicion_detalle')->primary();
            $t->integer('id_tb_egreso_requisicion');
            $t->string('fuente')->nullable();
            $t->string('proyecto')->nullable();
            $t->string('partida')->nullable();
            $t->decimal('precio_unitario', 15, 2)->nullable();
            $t->decimal('sub_total', 15, 2)->nullable();
            $t->decimal('total', 15, 2)->nullable();
        });
        Schema::create('cat_egreso_requisicion_estado', function (Blueprint $t) {
            $t->integer('id_cat_egreso_requisicion_estado')->primary();
            $t->string('descripcion');
        });
        foreach ([5 => 'En Recursos Materiales', 6 => 'En Cotización', 7 => 'Comprometida', 8 => 'Orden de Compra', 9 => 'Con Factura', 10 => 'CLC Generada', 11 => 'Pagada'] as $id => $descripcion) {
            DB::table('cat_egreso_requisicion_estado')->insert(['id_cat_egreso_requisicion_estado' => $id, 'descripcion' => $descripcion]);
        }
        Schema::create('tb_egreso_requisicion_bitacora', function (Blueprint $t) {
            $t->integer('id_tb_egreso_requisicion_bitacora')->primary();
            $t->integer('id_tb_egreso_requisicion');
            $t->integer('id_cat_egreso_requisicion_estado_anterior')->nullable();
            $t->integer('id_cat_egreso_requisicion_estado_nuevo')->nullable();
            $t->integer('id_tb_usuarios')->nullable();
            $t->text('observaciones')->nullable();
        });
    }

    private function requisicion(int $id, float $monto, ?string $suficienciaUtc = null): void
    {
        DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => $id, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => 6, 'suficiencia' => true, 'suficiencia_fecha' => $suficienciaUtc, 'total' => $monto]);
        DB::table('tb_egreso_requisicion_detalle')->insert(['id_tb_egreso_requisicion_detalle' => $id, 'id_tb_egreso_requisicion' => $id, 'fuente' => '561', 'proyecto' => '101001', 'partida' => '2111', 'total' => $monto]);
    }

    public function test_correr_saldos_suma_al_mes_lo_que_quedo_disponible_el_anterior()
    {
        $this->crearTablasRequisicion();
        $clave = '310|561|101001|2111';

        // 100 por mes. Enero se comprometen 30; febrero, 50 (suficiencia el 28 de febrero a las 23:00 hora local).
        $this->post('/presupuesto', $this->linea(['IMPORTE_TOTAL' => '1200', ...array_fill_keys(self::MESES, '100')]))->assertSessionHasNoErrors();
        $this->requisicion(1, 30, '2026-01-15 18:00:00');
        $this->requisicion(2, 50, '2026-03-01 05:00:00');

        $saldos = new SaldosMensuales;

        // Sin corridas, el saldo corrido de marzo se calcula: (100 − 30) + (100 − 50) = 120.
        $this->assertEquals(120, $saldos->situacion(2026, 3)[$clave]['saldo_corrido']);
        $this->assertEquals(220, $saldos->situacion(2026, 3)[$clave]['disponible_mes']);

        // Corrida del 1 de marzo con el comando (manual: ya no se programa; usa el mes actual).
        $this->travelTo(Carbon::parse('2026-03-01 06:05:00', 'UTC'));
        $this->artisan('presupuesto:correr-saldos')->assertSuccessful();

        $corrida = CorridaSaldo::where('ejercicio', 2026)->where('mes', 3)->firstOrFail();
        $this->assertSame('manual', $corrida->origen);
        $this->assertEquals(120, SaldoMensual::where('clave', $clave)->where('mes', 3)->value('saldo_anterior'));

        // Queda congelado: un movimiento tardío de febrero ya no cambia lo corrido a marzo...
        $this->requisicion(3, 20, '2026-02-10 18:00:00');
        $this->assertEquals(120, (new SaldosMensuales)->situacion(2026, 3)[$clave]['saldo_corrido']);
        // ...pero sí lo que se corra a abril desde marzo (120 + 100 de marzo).
        $this->assertEquals(220, (new SaldosMensuales)->situacion(2026, 4)[$clave]['saldo_corrido']);

        // Una sola corrida por mes: el botón avisa y el comando no la repite.
        $this->post('/presupuesto/correr-saldos')->assertSessionHas('error');
        $this->artisan('presupuesto:correr-saldos')->assertSuccessful();
        $this->assertSame(1, CorridaSaldo::count());

        // Forzada, vuelve a calcular con los movimientos actuales: (100−30) + (100−50−20) = 100.
        $this->artisan('presupuesto:correr-saldos', ['--mes' => '2026-03', '--forzar' => true])->assertSuccessful();
        $this->assertEquals(100, SaldoMensual::where('clave', $clave)->where('mes', 3)->value('saldo_anterior'));

        // Abril, desde la pantalla (manual).
        $this->travelTo(Carbon::parse('2026-04-01 18:00:00', 'UTC'));
        $this->post('/presupuesto/correr-saldos')->assertSessionHas('success');
        $this->assertSame('manual', CorridaSaldo::where('mes', 4)->value('origen'));
        $this->assertEquals(200, SaldoMensual::where('clave', $clave)->where('mes', 4)->value('saldo_anterior'));

        $this->get('/requisiciones/gasto')->assertOk();
    }

    public function test_lista_muestra_comprometido_devengado_y_pagado()
    {
        $this->crearTablasRequisicion();
        $this->post('/presupuesto', $this->linea());

        // Dos requisiciones de 300 y 200 con suficiencia; solo la primera tiene orden de compra con factura.
        foreach ([1 => [300, EgresoRequisicion::CON_FACTURA], 2 => [200, EgresoRequisicion::COMPROMETIDA]] as $id => [$monto, $estado]) {
            DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => $id, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => $estado, 'suficiencia' => true, 'total' => $monto]);
            DB::table('tb_egreso_requisicion_detalle')->insert(['id_tb_egreso_requisicion_detalle' => $id, 'id_tb_egreso_requisicion' => $id, 'fuente' => '561', 'proyecto' => '101001', 'partida' => '2111', 'total' => $monto]);
        }
        $proveedor = Proveedor::create(['nombre' => 'PAPELERÍA', 'activo' => true]);
        $orden = OrdenCompra::create([
            'id_tb_egreso_requisicion' => 1, 'proveedor_id' => $proveedor->id, 'folio' => 1, 'año' => 2026,
            'folio_completo' => 'OC-2026-0001', 'fecha' => '2026-10-01', 'importe' => 300, 'xml_uuid' => 'UUID-1',
        ]);

        $momentos = fn () => $this->get('/presupuesto')->assertOk()->viewData('page')['props']['presupuestos']['data'][0];

        // Con factura, los 300 pasan de comprometido a devengado; el disponible no cambia.
        $this->assertEquals([200, 300, 0], [$momentos()['comprometido'], $momentos()['devengado'], $momentos()['pagado']]);
        $linea = app(PresupuestoService::class)->linea('310|561|101001|2111');
        $this->assertEquals(round(1200.12 - 500, 2), $linea['disponible']);

        // Sin CLC no se paga.
        $pago = ['pago_fecha' => '2026-10-05', 'pago_forma' => 'transferencia', 'pago_referencia' => 'SPEI 123'];
        $this->post("/ordenes-compra/{$orden->id}/pago", $pago)->assertSessionHas('error');
        $this->assertNull($orden->fresh()->pago_fecha);

        // El área genera la CLC (una sola vez) y queda su PDF.
        $this->post("/ordenes-compra/{$orden->id}/clc")->assertSessionHas('success');
        $this->post("/ordenes-compra/{$orden->id}/clc")->assertSessionHas('error');
        $this->assertSame(sprintf('CLC-%d-0001', now('America/Mexico_City')->year), $orden->fresh()->clc_folio_completo);
        $this->assertSame(EgresoRequisicion::CLC_GENERADA, (int) DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', 1)->value('id_cat_egreso_requisicion_estado'));
        $this->get("/ordenes-compra/{$orden->id}/clc")->assertOk()->assertHeader('content-type', 'application/pdf');

        // La CLC requiere factura.
        $sinFactura = OrdenCompra::create([
            'id_tb_egreso_requisicion' => 2, 'proveedor_id' => $proveedor->id, 'folio' => 2, 'año' => 2026,
            'folio_completo' => 'OC-2026-0002', 'fecha' => '2026-10-01', 'importe' => 200,
        ]);
        $this->post("/ordenes-compra/{$sinFactura->id}/clc")->assertSessionHas('error');
        $sinFactura->delete();

        // Trámite de la CLC: queda en el área, que la envía a Recursos Materiales; no se paga hasta que llega a Egresos.
        $this->assertSame('generada', $orden->fresh()->clc_estado);
        $this->post("/ordenes-compra/{$orden->id}/pago", $pago)->assertSessionHas('error');
        $paso = fn (string $accion, array $extra = []) => $this->post('/clc/accion', ['accion' => $accion, 'ordenes' => [$orden->id], ...$extra]);

        $paso('recibir_rm')->assertSessionHasErrors('ordenes'); // fuera de orden
        $paso('enviar_rm')->assertSessionHas('success');
        $paso('recibir_rm')->assertSessionHas('success');
        $paso('enviar_cp')->assertSessionHas('success');
        $paso('recibir_cp')->assertSessionHas('success');
        $this->assertSame(0, ClcRelacion::count()); // área → RM y RM → CP van sin relación

        // Control Presupuestal la devuelve con observaciones (obligatorias) y Recursos Materiales la reenvía.
        $paso('devolver')->assertSessionHasErrors('observaciones');
        $paso('devolver', ['observaciones' => 'Falta el sello del proveedor'])->assertSessionHas('success');
        $this->assertSame('devuelta', $orden->fresh()->clc_estado);
        $this->get('/clc')->assertOk()->assertInertia(fn ($p) => $p->where('clcs.0.devolucion', 'Falta el sello del proveedor'));
        $paso('enviar_cp')->assertSessionHas('success');
        $paso('recibir_cp')->assertSessionHas('success');

        // Relación solo de Control Presupuestal a Sindicatura y de regreso.
        $paso('enviar_sindicatura')->assertSessionHas('success');
        $relacion = ClcRelacion::firstOrFail();
        $this->assertSame(['control_presupuestal', 'sindicatura'], [$relacion->origen, $relacion->destino]);
        $this->get("/clc/relaciones/{$relacion->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $paso('recibir_sindicatura')->assertSessionHas('success');
        $this->assertNotNull(DB::table('clc_relacion_orden')->where('clc_relacion_id', $relacion->id)->value('recibida_at'));

        foreach (['enviar_cp_firmada', 'recibir_cp_firmada', 'enviar_egresos', 'recibir_egresos'] as $accion) {
            $paso($accion)->assertSessionHas('success');
        }
        $this->assertSame('recibida_egresos', $orden->fresh()->clc_estado);
        $this->assertSame(2, ClcRelacion::count()); // CP → Sindicatura y Sindicatura → CP
        // Egresos la marca pagada.
        // El cheque exige su número; la transferencia toma su número automático (la referencia bancaria es opcional).
        $paso('pagar', ['pago_fecha' => '2026-10-05', 'pago_forma' => 'cheque'])->assertSessionHasErrors('pago_referencia');
        $paso('pagar', $pago)->assertSessionHas('success');
        $this->assertSame('pagada', $orden->fresh()->clc_estado);
        $this->assertSame(['TRF-2026-0001', 'SPEI 123'], [$orden->fresh()->transferencia_numero, $orden->fresh()->pago_referencia]);

        // Ya pagada, Egresos (y cualquier área del trámite) la sigue viendo en Seguimiento.
        $egresos = User::factory()->create();
        $egresos->givePermissionTo('clc-index', 'clc-egresos');
        $this->actingAs($egresos)->get('/clc')->assertInertia(fn ($p) => $p->where('seguimientoTodas', true)
            ->where('clcs.0.estado', 'pagada')->where('clcs.0.seguimiento', true)
            ->where('clcs.0.transferencia', 'TRF-2026-0001')->where('clcs.0.pago_fecha', '2026-10-05'));
        $this->actingAs(User::role('Admin')->first());
        $this->assertSame(15, ClcMovimiento::where('orden_compra_id', $orden->id)->count()); // generar + 14 pasos
        $this->assertEquals([200, 0, 300], [$momentos()['comprometido'], $momentos()['devengado'], $momentos()['pagado']]);
        $this->assertSame(EgresoRequisicion::PAGADA, (int) DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', 1)->value('id_cat_egreso_requisicion_estado'));

        // El Excel de "Ejercicio y control del gasto" trae la línea con sus momentos como números.
        $respuesta = $this->get('/requisiciones/gasto/exportar?buscar=oficina')->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'gas').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());
        $hoja = IOFactory::load($ruta)->getActiveSheet();
        $this->assertSame('Ejercicio y control del gasto', $hoja->getCell('A1')->getValue());
        $this->assertSame('2111', $hoja->getCell('G5')->getValue());
        $this->assertEquals(200, $hoja->getCell('L5')->getValue()); // comprometido (lo pagado ya salió)
        // Sin movimiento = 0, no celda vacía (modificado y devengado).
        $this->assertNotNull($hoja->getCell('J5')->getValue());
        $this->assertEquals(0, $hoja->getCell('J5')->getValue());
        $this->assertNotNull($hoja->getCell('M5')->getValue());
        $this->assertEquals(300, $hoja->getCell('N5')->getValue()); // pagado
        $this->assertSame('#,##0.00;[Red]-#,##0.00', $hoja->getStyle('L5')->getNumberFormat()->getFormatCode());
        $this->assertSame('=SUM(L5:L5)', $hoja->getCell('L6')->getValue());

        // Una búsqueda sin resultados exporta solo encabezados y totales en cero.
        file_put_contents($ruta, $this->get('/requisiciones/gasto/exportar?buscar=inexistente')->streamedContent());
        $this->assertSame('Total', IOFactory::load($ruta)->getActiveSheet()->getCell('A5')->getValue());

        // Un solo pago por orden, y no antes de la orden.
        $this->post("/ordenes-compra/{$orden->id}/pago", ['pago_fecha' => '2026-10-06', 'pago_forma' => 'cheque', 'pago_referencia' => '9'])
            ->assertSessionHas('error');

        // La fecha de la orden se corrige: mismo año del folio y no después del pago (5 de octubre).
        $this->post("/ordenes-compra/{$orden->id}/fecha", ['fecha' => '2026-10-06'])->assertSessionHasErrors('fecha');
        $this->post("/ordenes-compra/{$orden->id}/fecha", ['fecha' => '2025-12-31'])->assertSessionHasErrors('fecha');
        $this->post("/ordenes-compra/{$orden->id}/fecha", ['fecha' => '2026-09-30'])->assertSessionHas('success');
        $this->assertSame('2026-09-30', $orden->fresh()->fecha->toDateString());
        $this->assertStringContainsString('cambiada del 2026-10-01 al 2026-09-30', DB::table('tb_egreso_requisicion_bitacora')->orderByDesc('id_tb_egreso_requisicion_bitacora')->value('observaciones'));

        // Sin el permiso no se cambia.
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('requisiciones-index');
        $this->actingAs($sinPermiso)->post("/ordenes-compra/{$orden->id}/fecha", ['fecha' => '2026-09-29'])->assertForbidden();
    }

    public function test_no_se_carga_dos_veces_la_misma_factura()
    {
        Storage::fake('local');
        $this->crearTablasRequisicion();
        $proveedor = Proveedor::create(['nombre' => 'DORACELI VALADEZ RODRIGUEZ', 'rfc' => 'VARD870519SR4', 'activo' => true]);
        $ordenes = [];
        foreach ([1, 2, 3] as $id) {
            $this->requisicion($id, 4524);
            DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', $id)->update(['id_cat_egreso_requisicion_estado' => EgresoRequisicion::CON_ORDEN_COMPRA]);
            $ordenes[$id] = OrdenCompra::create([
                'id_tb_egreso_requisicion' => $id, 'proveedor_id' => $proveedor->id, 'folio' => $id, 'año' => 2026,
                'folio_completo' => sprintf('OC-2026-%04d', $id), 'fecha' => '2026-09-01', 'importe' => 4524,
            ]);
        }

        $cfdi = fn (string $uuid, string $folio) => UploadedFile::fake()->createWithContent("{$uuid}.xml", <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Version="4.0" Serie="1" Folio="{$folio}" Fecha="2026-09-01T08:26:09" SubTotal="3900.00" Total="4524.00" TipoDeComprobante="I">
              <cfdi:Emisor Rfc="VARD870519SR4" Nombre="DORACELI VALADEZ RODRIGUEZ" RegimenFiscal="612" />
              <cfdi:Receptor Rfc="MGU6501015R2" Nombre="MUNICIPIO DE GUADALUPE ZAC" UsoCFDI="G03" />
              <cfdi:Complemento>
                <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="1.1" UUID="{$uuid}" />
              </cfdi:Complemento>
            </cfdi:Comprobante>
            XML);
        $subir = fn (int $orden, $archivo) => $this->post("/ordenes-compra/{$ordenes[$orden]->id}/xml", ['xml' => $archivo]);

        $subir(1, $cfdi('E788CBF2-688D-473B-BCC1-340042ECC3A8', '273'))->assertSessionHasNoErrors();

        // El mismo folio fiscal en otra orden: dice en cuál está.
        $subir(2, $cfdi('e788cbf2-688d-473b-bcc1-340042ecc3a8', '273'))
            ->assertSessionHasErrors('xml');
        $this->assertStringContainsString('La factura E788CBF2-688D-473B-BCC1-340042ECC3A8 ya está cargada en la orden OC-2026-0001', session('errors')->first('xml'));

        // La misma serie y folio del mismo emisor, vuelta a timbrar con otro UUID.
        $subir(2, $cfdi('11111111-2222-3333-4444-555555555555', '273'))->assertSessionHasErrors('xml');
        $this->assertStringContainsString('serie 1 folio 273 de VARD870519SR4 ya está cargada en la orden OC-2026-0001', session('errors')->first('xml'));
        $this->assertNull($ordenes[2]->fresh()->xml_uuid);

        // Otra factura del mismo proveedor sí entra.
        $subir(2, $cfdi('99999999-2222-3333-4444-555555555555', '274'))->assertSessionHasNoErrors();
        $this->assertSame('274', $ordenes[2]->fresh()->xml_folio);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_tramite_clc_por_area_y_recepcion_parcial()
    {
        $this->crearTablasRequisicion();
        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 7, 'clave' => '320', 'nombre' => 'OTRA', 'año' => 2024, 'activo' => true]);
        $proveedor = Proveedor::create(['nombre' => 'PAPELERÍA', 'activo' => true]);
        $ordenes = [];
        foreach ([1, 2, 3] as $id) {
            $this->requisicion($id, 100);
            $ordenes[$id] = OrdenCompra::create([
                'id_tb_egreso_requisicion' => $id, 'proveedor_id' => $proveedor->id, 'folio' => $id, 'año' => 2026,
                'folio_completo' => "OC-2026-000{$id}", 'fecha' => '2026-10-01', 'importe' => 100, 'xml_uuid' => "UUID-{$id}",
                'clc_folio' => $id, 'clc_año' => 2026, 'clc_folio_completo' => "CLC-2026-000{$id}", 'clc_fecha' => '2026-10-02', 'clc_estado' => 'generada',
            ]);
        }
        DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', 3)->update(['id_cat_egreso_unidad_administrativa' => 7]);
        Storage::fake('local'); // el comando guarda la contraseña en un archivo
        $this->artisan('clc:usuarios-prueba')->assertSuccessful();
        Storage::disk('local')->assertExists('usuarios-prueba-clc.txt');
        $usuario = fn (string $correo) => User::where('email', "{$correo}.prueba@sieg.test")->firstOrFail();
        $accion = fn (string $correo, string $accion, array $ids) => $this->actingAs($usuario($correo))->post('/clc/accion', ['accion' => $accion, 'ordenes' => $ids]);
        $pantalla = fn (string $correo) => $this->actingAs($usuario($correo))->get('/clc')->assertOk();

        // El área ve solo las de su secretaría (310), no ve relaciones y envía las suyas a Recursos Materiales.
        $pantalla('area')->assertInertia(fn ($p) => $p->where('veTodo', false)->where('relaciones', [])->has('clcs', 2)
            ->where('clcs', fn ($clcs) => collect($clcs)->every(fn ($c) => $c['seguimiento'])));
        $accion('area', 'enviar_rm', [$ordenes[3]->id])->assertForbidden(); // de otra secretaría
        $accion('area', 'enviar_rm', [$ordenes[1]->id, $ordenes[2]->id])->assertSessionHas('success');
        $this->assertSame(0, ClcRelacion::count());
        $accion('area', 'recibir_rm', [$ordenes[1]->id])->assertForbidden();

        // Sindicatura no recibe lo que está en Recursos Materiales; RM recibe solo la que llegó.
        $accion('sindicatura', 'recibir_rm', [$ordenes[1]->id])->assertForbidden();
        $pantalla('recursos.materiales')->assertInertia(fn ($p) => $p->where('veTodo', false)->has('clcs', 3)->where('seguimientoTodas', true)); // sigue todas; actúa solo en su bandeja
        $accion('recursos.materiales', 'recibir_rm', [$ordenes[1]->id])->assertSessionHas('success');
        $this->assertSame(['recibida_rm', 'enviada_rm'], [$ordenes[1]->fresh()->clc_estado, $ordenes[2]->fresh()->clc_estado]);
        // Egresos aún no tiene nada en su bandeja, pero en Seguimiento ve todas las CLC.
        $pantalla('egresos')->assertInertia(fn ($p) => $p->has('clcs', 3)->where('clcs', fn ($clcs) => collect($clcs)->every(fn ($c) => $c['seguimiento'])));

        // Control Presupuestal ve todo y administra usuarios, roles y permisos; las demás áreas no.
        $pantalla('control.presupuestal')->assertInertia(fn ($p) => $p->where('veTodo', true)->has('clcs', 3));
        $this->actingAs($usuario('control.presupuestal'))->get('/settings/roles')->assertOk();
        $this->actingAs($usuario('recursos.materiales'))->get('/settings/roles')->assertForbidden();

        // "Recibido – Iniciar cotización" solo lo hace Recursos Materiales.
        DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => 9, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => 5]);
        $this->actingAs($usuario('area'))->post('/requisiciones/9/recibido')->assertForbidden();
        $this->actingAs($usuario('control.presupuestal'))->post('/requisiciones/9/recibido')->assertForbidden();
        $this->actingAs($usuario('recursos.materiales'))->post('/requisiciones/9/recibido')->assertSessionHas('success');
        $this->assertSame(6, (int) DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', 9)->value('id_cat_egreso_requisicion_estado'));

        // Recursos Materiales y proveedores: solo su rol; Modificaciones: cada área las suyas y Control Presupuestal.
        foreach (['/recursos-materiales' => ['recursos.materiales'], '/catalogos/proveedores' => ['recursos.materiales'], '/modificaciones-presupuestales' => ['area', 'control.presupuestal']] as $pagina => $pueden) {
            foreach (['area', 'recursos.materiales', 'control.presupuestal', 'sindicatura', 'egresos'] as $quien) {
                $this->actingAs($usuario($quien))->get($pagina)->assertStatus(in_array($quien, $pueden, true) ? 200 : 403);
            }
        }

        // Presupuesto y Ejercicio y control del gasto: solo Admin y Control Presupuestal.
        foreach (['/presupuesto', '/requisiciones/gasto'] as $pagina) {
            $this->actingAs($usuario('control.presupuestal'))->get($pagina)->assertOk();
            foreach (['area', 'recursos.materiales', 'sindicatura', 'egresos'] as $otro) {
                $this->actingAs($usuario($otro))->get($pagina)->assertForbidden();
            }
        }
    }

    public function test_cancelar_requisicion_con_motivo()
    {
        $this->crearTablasRequisicion();
        $this->post('/presupuesto', $this->linea());
        $clave = '310|561|101001|2111';

        // Comprometida (con suficiencia) por 300: al cancelarla se libera el presupuesto.
        $this->requisicion(1, 300);
        DB::table('tb_egreso_requisicion')->update(['id_cat_egreso_requisicion_estado' => EgresoRequisicion::COMPROMETIDA]);
        $disponible = fn () => app(PresupuestoService::class)->linea($clave)['disponible'];
        $this->assertEquals(round(1200.12 - 300, 2), $disponible());

        // El enlace de la secretaría no la cancela ya enviada; sin motivo tampoco procede.
        $enlace = User::factory()->create(['id_cat_egreso_unidad_administrativa' => 6]);
        $enlace->givePermissionTo('requisiciones-index', 'requisiciones-create');
        $this->actingAs($enlace)->post('/requisiciones/1/cancelar', ['motivo' => 'Ya no se ocupa'])->assertForbidden();

        $admin = User::role('Admin')->first();
        $this->actingAs($admin)->post('/requisiciones/1/cancelar', ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post('/requisiciones/1/cancelar', ['motivo' => 'Se compró por otro medio'])->assertSessionHas('success');

        $requisicion = EgresoRequisicion::find(1);
        $this->assertSame(EgresoRequisicion::CANCELADA, (int) $requisicion->id_cat_egreso_requisicion_estado);
        $this->assertSame('Se compró por otro medio', $requisicion->motivo_cancelacion);
        $this->assertSame($admin->id, (int) $requisicion->cancelada_user_id);
        $this->assertStringContainsString('Motivo: Se compró por otro medio', DB::table('tb_egreso_requisicion_bitacora')->value('observaciones'));
        $this->assertEquals(1200.12, $disponible());

        // Una cancelada (o con orden de compra) ya no se cancela.
        $this->post('/requisiciones/1/cancelar', ['motivo' => 'Otra vez'])->assertForbidden();

        // El enlace sí cancela las suyas mientras están pendientes.
        $this->requisicion(2, 100);
        DB::table('tb_egreso_requisicion')->where('id_tb_egreso_requisicion', 2)->update(['id_cat_egreso_requisicion_estado' => 1, 'suficiencia' => null]);
        $this->actingAs($enlace)->post('/requisiciones/2/cancelar', ['motivo' => 'Capturada por error'])->assertSessionHas('success');
    }

    public function test_cambiar_fecha_de_la_requisicion()
    {
        $this->crearTablasRequisicion();
        $this->travelTo(Carbon::parse('2026-10-15 18:00', 'UTC'));
        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 7, 'clave' => '320', 'nombre' => 'OTRA', 'año' => 2024, 'activo' => true]);
        DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => 1, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => 3, 'año' => 2026, 'solicitado' => '2026-10-11']);
        $fecha = fn () => substr((string) DB::table('tb_egreso_requisicion')->value('solicitado'), 0, 10);

        // Recursos Materiales / Control Presupuestal (Admin tiene requisiciones-fecha): en cualquier estado.
        $this->post('/requisiciones/1/fecha', ['fecha' => '2026-10-20'])->assertSessionHasErrors('fecha'); // futura
        $this->post('/requisiciones/1/fecha', ['fecha' => '2025-12-31'])->assertSessionHasErrors('fecha'); // otro ejercicio
        $this->post('/requisiciones/1/fecha', ['fecha' => '2026-10-02'])->assertSessionHas('success');
        $this->assertSame('2026-10-02', $fecha());
        $this->assertStringContainsString('del 2026-10-11 al 2026-10-02', DB::table('tb_egreso_requisicion_bitacora')->value('observaciones'));

        // El enlace solo cambia las de su secretaría.
        $enlace = User::factory()->create(['id_cat_egreso_unidad_administrativa' => 7]);
        $enlace->givePermissionTo('requisiciones-index', 'requisiciones-create');
        $this->actingAs($enlace)->post('/requisiciones/1/fecha', ['fecha' => '2026-10-03'])->assertForbidden();

        $enlace->update(['id_cat_egreso_unidad_administrativa' => 6]);
        $this->actingAs($enlace->fresh())->post('/requisiciones/1/fecha', ['fecha' => '2026-10-03'])->assertSessionHas('success');
        $this->assertSame('2026-10-03', $fecha());
    }

    public function test_con_suficiencia_queda_comprometida_y_solo_entonces_se_genera_la_orden_de_compra()
    {
        $this->crearTablasRequisicion();
        $this->post('/presupuesto', $this->linea());

        DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => 1, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => EgresoRequisicion::EN_COTIZACION, 'total' => 116]);
        DB::table('tb_egreso_requisicion_detalle')->insert(['id_tb_egreso_requisicion_detalle' => 1, 'id_tb_egreso_requisicion' => 1, 'fuente' => '561', 'proyecto' => '101001', 'partida' => '2111', 'precio_unitario' => 100, 'sub_total' => 100, 'total' => 116]);
        $proveedor = Proveedor::create(['nombre' => 'PAPELERÍA', 'activo' => true]);
        $orden = ['proveedor_id' => $proveedor->id, 'fecha' => '2026-10-01'];
        $estado = fn () => (int) DB::table('tb_egreso_requisicion')->value('id_cat_egreso_requisicion_estado');

        // En cotización (sin suficiencia) no hay orden de compra.
        $this->post('/requisiciones/1/orden-compra', $orden)->assertForbidden();

        $this->post('/requisiciones/1/suficiencia')->assertSessionHas('success');
        $this->assertSame(EgresoRequisicion::COMPROMETIDA, $estado());
        $this->assertSame(EgresoRequisicion::COMPROMETIDA, (int) DB::table('tb_egreso_requisicion_bitacora')->value('id_cat_egreso_requisicion_estado_nuevo'));

        // En su pantalla, la partida ya muestra el importe de la requisición como comprometido.
        $partida = app(PresupuestoService::class)->validarRequisicion(EgresoRequisicion::find(1))['partidas'][0];
        $this->assertEquals([116, round(1200.12 - 116, 2), true], [$partida['comprometido'], $partida['disponible'], $partida['suficiente']]);

        // Formato único de requisición (FUR).
        $this->get('/requisiciones/1/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->post('/requisiciones/1/orden-compra', $orden)->assertSessionHas('success');
        $this->assertSame(EgresoRequisicion::CON_ORDEN_COMPRA, $estado());
        $this->assertSame(1, OrdenCompra::count());

        // Recursos Materiales sigue viéndola en su lista.
        $this->get('/recursos-materiales')->assertOk()
            ->assertInertia(fn ($p) => $p->where('requisiciones.data.0.id_tb_egreso_requisicion', 1));
    }

    public function test_modificacion_con_periodo_de_emisor_y_receptor_y_autorizacion_desde_la_lista()
    {
        $this->crearTablasRequisicion();
        $this->travelTo(Carbon::parse('2026-10-15 18:00:00', 'UTC'));
        DB::table('cat_egreso_objeto_gasto')->insert(['id_cat_egreso_objeto_gasto' => 2, 'clave' => '2112', 'tipo_gasto' => 1, 'nombre' => 'EQUIPOS MENORES', 'activo' => true]);
        $this->post('/presupuesto', $this->linea());
        $this->post('/presupuesto', $this->linea(['PARTIDA' => '2112']));
        [$emisor, $receptor] = ['310|561|101001|2111', '310|561|101001|2112'];

        $this->get('/modificaciones-presupuestales/crear')->assertOk()
            ->assertInertia(fn ($p) => $p->where('mesActual', 10)->has('meses', 3)
                // Octubre trae lo corrido de enero a septiembre: 10 × 100.01.
                ->where("disponiblesMes.{$emisor}.10", 1000.1));

        $solicitud = ['tipo' => 'traspaso', 'origen' => $emisor, 'destino' => $receptor, 'importe' => 50, 'justificacion' => 'Prueba', 'origen_mes' => 12, 'destino_mes' => 10];

        // Sin periodo, con un mes ya pasado o con más de lo disponible en el mes del emisor, no procede.
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'origen_mes' => null])->assertSessionHasErrors('origen_mes');
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'destino_mes' => 9])->assertSessionHasErrors('destino_mes');
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'origen_mes' => 10, 'importe' => 1100])->assertSessionHasErrors('importe');

        $this->post('/modificaciones-presupuestales', $solicitud)->assertSessionHasNoErrors();
        $modificacion = ModificacionPresupuestal::firstOrFail();
        $this->assertSame([12, 10], [$modificacion->origen_mes, $modificacion->destino_mes]);

        // Se acepta desde la lista (regresa a la lista).
        $this->from('/modificaciones-presupuestales')->post("/modificaciones-presupuestales/{$modificacion->id}/autorizar")
            ->assertRedirect('/modificaciones-presupuestales');
        $this->assertSame('autorizada', $modificacion->fresh()->estado);

        // Formato PDF (hoja horizontal con QR).
        $this->get("/modificaciones-presupuestales/{$modificacion->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        // El movimiento cuenta en el periodo de cada lado, no en el mes en que se autorizó.
        $saldos = new SaldosMensuales;
        $this->assertEquals(-50, $saldos->situacion(2026, 12)[$emisor]['modificado_mes']);
        $this->assertEquals(0, $saldos->situacion(2026, 10)[$emisor]['modificado_mes']);
        $this->assertEquals(50, $saldos->situacion(2026, 10)[$receptor]['modificado_mes']);

        $this->get('/modificaciones-presupuestales')->assertOk()
            ->assertInertia(fn ($p) => $p->where('modificaciones.data.0.origen_mes', 12)->where('meses.12', 'diciembre')
                // Saldos actuales de cada partida: anual y del periodo elegido.
                ->where('modificaciones.data.0.origen_disponible', 1150.12)->where('modificaciones.data.0.origen_disponible_mes', 1150.12)
                ->where('modificaciones.data.0.destino_disponible', 1250.12)->where('modificaciones.data.0.destino_disponible_mes', 1050.1));

        // Traspaso solo entre partidas de la misma fuente de financiamiento.
        DB::table('cat_egreso_fuente_financiamiento')->insert(['id_cat_egreso_fuente_financiamiento' => 2, 'clave' => '562', 'nombre' => 'APORTACIONES', 'activo' => true]);
        $this->post('/presupuesto', $this->linea(['CLAVE3' => '562']))->assertSessionHasNoErrors();
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'destino' => '310|562|101001|2111'])->assertSessionHasErrors('destino');
        $this->assertSame(1, ModificacionPresupuestal::count());

        // En el ejercicio del gasto, la partida muestra sus modificaciones (con signo para esta partida).
        $this->getJson('/requisiciones/gasto/detalle?clave='.urlencode($emisor))->assertOk()
            ->assertJsonPath('modificaciones.0.lado', 'Emisor')
            ->assertJsonPath('modificaciones.0.importe', -50)
            ->assertJsonPath('modificaciones.0.mes', 'diciembre');
    }

    public function test_las_areas_capturan_sus_modificaciones_hasta_tres_dias_antes_del_fin_de_mes()
    {
        $this->crearTablasRequisicion();
        $this->travelTo(Carbon::parse('2026-10-28 18:00:00', 'UTC'));
        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 7, 'clave' => '320', 'nombre' => 'OTRA', 'año' => 2024, 'activo' => true]);
        DB::table('cat_egreso_objeto_gasto')->insert(['id_cat_egreso_objeto_gasto' => 2, 'clave' => '2112', 'tipo_gasto' => 1, 'nombre' => 'EQUIPOS MENORES', 'activo' => true]);
        $this->post('/presupuesto', $this->linea());
        $this->post('/presupuesto', $this->linea(['PARTIDA' => '2112']));
        $this->post('/presupuesto', $this->linea(['CLAVE' => '320']));

        $area = User::factory()->create(['id_cat_egreso_unidad_administrativa' => 6]);
        $area->givePermissionTo('modificaciones-index', 'modificaciones-create');
        $control = User::factory()->create();
        $control->givePermissionTo('modificaciones-index', 'modificaciones-create', 'modificaciones-autorizar');
        $solicitud = ['tipo' => 'traspaso', 'origen' => '310|561|101001|2111', 'destino' => '310|561|101001|2112', 'importe' => 10, 'justificacion' => 'Prueba', 'origen_mes' => 10, 'destino_mes' => 10];

        // 28 de octubre (hora de México): último día de captura para el área.
        $this->actingAs($area)->get('/modificaciones-presupuestales/crear')->assertOk()
            ->assertInertia(fn ($p) => $p->where('captura.abierta', true)->where('captura.ultimoDia', '2026-10-28'));
        $this->post('/modificaciones-presupuestales', $solicitud)->assertSessionHasNoErrors();

        // No puede usar líneas de otra área ni autorizar.
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'origen' => '320|561|101001|2111'])->assertSessionHasErrors('origen');
        $this->post('/modificaciones-presupuestales/'.ModificacionPresupuestal::first()->id.'/autorizar')->assertForbidden();

        // Del 29 al 31 la captura está cerrada para el área; Control Presupuestal siempre puede.
        $this->travelTo(Carbon::parse('2026-10-29 07:00:00', 'UTC'));
        $this->get('/modificaciones-presupuestales')->assertInertia(fn ($p) => $p->where('captura.abierta', false));
        $this->post('/modificaciones-presupuestales', $solicitud)->assertSessionHasErrors('tipo');

        $this->actingAs($control)->get('/modificaciones-presupuestales/crear')
            ->assertInertia(fn ($p) => $p->where('captura.abierta', true)->where('captura.siempre', true));
        $this->post('/modificaciones-presupuestales', [...$solicitud, 'origen' => '320|561|101001|2111', 'destino' => '310|561|101001|2111'])->assertSessionHasNoErrors();
        $this->assertSame(2, ModificacionPresupuestal::count());

        // El día 1 se abre de nuevo.
        $this->travelTo(Carbon::parse('2026-11-01 07:00:00', 'UTC'));
        $this->actingAs($area)->post('/modificaciones-presupuestales', [...$solicitud, 'origen_mes' => 11, 'destino_mes' => 11])->assertSessionHasNoErrors();
    }

    public function test_cada_area_ve_sus_requisiciones_y_control_presupuestal_todas()
    {
        $this->crearTablasRequisicion();
        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 7, 'clave' => '320', 'nombre' => 'OTRA', 'año' => 2024, 'activo' => true]);
        foreach ([1 => 6, 2 => 7] as $id => $unidad) {
            DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => $id, 'id_cat_egreso_unidad_administrativa' => $unidad, 'id_cat_egreso_requisicion_estado' => 1]);
        }

        $area = User::factory()->create(['id_cat_egreso_unidad_administrativa' => 6]);
        $area->givePermissionTo('requisiciones-index');
        $this->actingAs($area)->get('/requisiciones')->assertInertia(fn ($p) => $p->has('requisiciones.data', 1)->where('requisiciones.data.0.id_tb_egreso_requisicion', 1));
        $this->get('/requisiciones/2')->assertForbidden();

        // Control Presupuestal (sin unidad asignada) ve las de todas las áreas.
        $control = User::factory()->create();
        $control->givePermissionTo('requisiciones-index', 'modificaciones-autorizar');
        $this->actingAs($control)->get('/requisiciones')->assertInertia(fn ($p) => $p->has('requisiciones.data', 2)->where('isAdmin', true));
        $this->get('/requisiciones/2')->assertOk();
    }

    public function test_la_cancelacion_libera_el_comprometido_en_el_mes_vigente()
    {
        $this->crearTablasRequisicion();
        $this->travelTo(Carbon::parse('2026-10-15 18:00:00', 'UTC'));
        $this->post('/presupuesto', $this->linea());
        $clave = '310|561|101001|2111';

        // Comprometida en marzo por 300.
        $this->requisicion(1, 300, '2026-03-10 18:00:00');
        DB::table('tb_egreso_requisicion')->update(['id_cat_egreso_requisicion_estado' => EgresoRequisicion::COMPROMETIDA]);
        $situacion = fn (int $mes) => (new SaldosMensuales)->situacion(2026, $mes)[$clave];
        $this->assertEquals(round(10 * 100.01 - 300, 2), $situacion(10)['disponible_mes']);

        $this->post('/requisiciones/1/cancelar', ['motivo' => 'Ya no se ocupa'])->assertSessionHas('success');

        // Marzo conserva lo comprometido; el importe regresa en octubre (mes de la cancelación).
        $this->assertEquals(300, $situacion(3)['comprometido_mes']);
        $this->assertEquals(-300, $situacion(10)['comprometido_mes']);
        $this->assertEquals(round(3 * 100.01 - 300, 2), $situacion(3)['disponible_mes']);
        $this->assertEquals(round(10 * 100.01, 2), $situacion(10)['disponible_mes']);

        // El detalle de la partida en el ejercicio del gasto la muestra, ya sin contar en el presupuesto.
        $this->getJson('/requisiciones/gasto/detalle?clave='.urlencode($clave))->assertOk()
            ->assertJsonPath('requisiciones.0.id', 1)
            ->assertJsonPath('requisiciones.0.importe', 300)
            ->assertJsonPath('requisiciones.0.momento', null);

        // Solo con el permiso del ejercicio del gasto y de su unidad.
        $enlace = User::factory()->create(['id_cat_egreso_unidad_administrativa' => 6]);
        $enlace->givePermissionTo('requisiciones-index');
        $this->actingAs($enlace)->getJson('/requisiciones/gasto/detalle?clave='.urlencode($clave))->assertForbidden();
        $enlace->givePermissionTo('gasto-index');
        $this->getJson('/requisiciones/gasto/detalle?clave='.urlencode('999|561|101001|2111'))->assertForbidden();
    }

    public function test_calendario_de_captura_y_autorizacion_de_extemporaneas()
    {
        $this->crearTablasRequisicion();

        // Sin ejercicio registrado: del 1 al 7.
        $this->assertTrue(EjercicioFiscal::enPeriodoDeCaptura(Carbon::parse('2026-10-07 23:00', 'America/Mexico_City')));
        $this->assertFalse(EjercicioFiscal::enPeriodoDeCaptura(Carbon::parse('2026-10-08 00:30', 'America/Mexico_City')));

        // Configurable por ejercicio (el día final no puede ser anterior al inicial).
        $this->post('/catalogos/ejercicios', ['año' => 2026, 'estado' => 'abierto', 'captura_dia_inicio' => 10, 'captura_dia_fin' => 5, 'activo' => true])
            ->assertSessionHasErrors('captura_dia_fin');
        $this->post('/catalogos/ejercicios', ['año' => 2026, 'estado' => 'abierto', 'captura_dia_inicio' => 1, 'captura_dia_fin' => 10, 'activo' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame([1, 10], EjercicioFiscal::periodoCaptura(2026));
        $this->assertTrue(EjercicioFiscal::enPeriodoDeCaptura(Carbon::parse('2026-10-08 12:00', 'America/Mexico_City')));

        // Una extemporánea no se envía a revisión hasta que se autoriza.
        DB::table('tb_egreso_requisicion')->insert(['id_tb_egreso_requisicion' => 1, 'id_cat_egreso_unidad_administrativa' => 6, 'id_cat_egreso_requisicion_estado' => 1, 'extemporanea' => true]);
        $estado = fn () => (int) DB::table('tb_egreso_requisicion')->value('id_cat_egreso_requisicion_estado');

        $this->post('/requisiciones/1/enviar')->assertSessionHas('error');
        $this->assertSame(1, $estado());

        // Solo con el permiso de la Subdirección Administrativa.
        $area = User::factory()->create();
        $area->givePermissionTo('requisiciones-create', 'requisiciones-index');
        $this->actingAs($area)->post('/requisiciones/1/autorizar-extemporanea')->assertForbidden();

        $subdireccion = User::factory()->create(['name' => 'SUBDIRECCIÓN']);
        $subdireccion->givePermissionTo('requisiciones-extemporaneas');
        $this->actingAs($subdireccion)->post('/requisiciones/1/autorizar-extemporanea')->assertSessionHas('success');
        $requisicion = EgresoRequisicion::find(1);
        $this->assertSame($subdireccion->id, (int) $requisicion->extemporanea_user_id);
        $this->assertNotNull($requisicion->extemporanea_autorizada_at);
        $this->assertStringContainsString('autorizada por SUBDIRECCIÓN', DB::table('tb_egreso_requisicion_bitacora')->value('observaciones'));
        $this->post('/requisiciones/1/autorizar-extemporanea')->assertSessionHas('error');

        // Al autorizarla se envía sola a revisión.
        $this->assertSame(2, $estado());
        $this->assertSame(2, (int) DB::table('tb_egreso_requisicion_bitacora')->value('id_cat_egreso_requisicion_estado_nuevo'));
        $this->assertStringContainsString('Enviada a revisión', DB::table('tb_egreso_requisicion_bitacora')->value('observaciones'));
    }

    public function test_usuario_pertenece_a_secretaria_o_a_regidor()
    {
        DB::table('cat_egreso_unidad_administrativa')->insert(['id_cat_egreso_unidad_administrativa' => 8, 'clave' => '140', 'nombre' => 'CABILDO', 'año' => 2024, 'activo' => true]);
        $this->post('/catalogos/regidores', ['titulo' => 'mtra.', 'nombre' => 'maria elena marquez martinez', 'cargo' => 'REGIDORA', 'activo' => true])
            ->assertSessionHasNoErrors();
        $regidor = Regidor::firstOrFail();
        $this->assertSame('MTRA. MARIA ELENA MARQUEZ MARTINEZ', $regidor->nombreConTitulo());

        $datos = ['name' => 'FATIMA JANETH ARTEAGA SALINAS', 'titulo' => 'l.c.', 'cargo' => 'Enlace administrativo', 'email' => 'fatima@sieg.test', 'password' => 'secreto-123'];

        // Hay que elegir a quién pertenece.
        $this->post('/settings/users', [...$datos, 'pertenece' => 'regidor'])->assertSessionHasErrors('regidor_id');

        // Enlace de regidor: para el presupuesto queda en Cabildo y su regidor firma por el área.
        $this->post('/settings/users', [...$datos, 'pertenece' => 'regidor', 'regidor_id' => $regidor->id, 'id_cat_egreso_unidad_administrativa' => 6])
            ->assertSessionHasNoErrors();
        $enlace = User::where('email', 'fatima@sieg.test')->firstOrFail();
        $this->assertSame(['L.C.', 'ENLACE ADMINISTRATIVO', 8, $regidor->id], [$enlace->titulo, $enlace->cargo, (int) $enlace->id_cat_egreso_unidad_administrativa, (int) $enlace->regidor_id]);
        $this->assertSame('L.C. FATIMA JANETH ARTEAGA SALINAS', $enlace->nombreConTitulo());
        $this->assertSame(['nombre' => 'MTRA. MARIA ELENA MARQUEZ MARTINEZ', 'cargo' => 'REGIDORA'], Firmante::titularDe($enlace, 'requisicion', 8));

        // De secretaría: sin regidor; firma el titular de la secretaría (aquí, el capturado en la unidad).
        DB::table('cat_egreso_unidad_administrativa')->where('id_cat_egreso_unidad_administrativa', 6)->update(['responsable' => 'L.C. JESÚS RODRÍGUEZ DEL MURO']);
        $this->patch("/settings/users/{$enlace->id}", [...$datos, 'pertenece' => 'secretaria', 'id_cat_egreso_unidad_administrativa' => 6, 'regidor_id' => $regidor->id])
            ->assertSessionHasNoErrors();
        $enlace->refresh();
        $this->assertSame([6, null], [(int) $enlace->id_cat_egreso_unidad_administrativa, $enlace->regidor_id]);
        $this->assertSame('L.C. JESÚS RODRÍGUEZ DEL MURO', Firmante::titularDe($enlace, 'requisicion', 6)['nombre']);
    }

    public function test_titular_de_la_secretaria_para_los_formatos()
    {
        $tipo = fn (string $clave) => DB::table('tipos_documento')->where('clave', $clave)->value('id');
        $firmante = fn (string $documento, string $nombre, string $desde) => Firmante::create([
            'tipo_documento_id' => $tipo($documento), 'rol' => 'autoriza', 'id_cat_egreso_unidad_administrativa' => 6,
            'nombre' => $nombre, 'cargo' => 'SECRETARIO DE LA TESORERÍA Y FINANZAS', 'vigente_desde' => $desde, 'activo' => true,
        ]);

        // Sin firmantes: el titular capturado en la unidad.
        DB::table('cat_egreso_unidad_administrativa')->where('id_cat_egreso_unidad_administrativa', 6)->update(['responsable' => 'TITULAR DE LA UNIDAD']);
        $this->assertSame('TITULAR DE LA UNIDAD', Firmante::titular('requisicion', 6)['nombre']);

        // Capturado solo para modificaciones (y con vigencia posterior al documento): también firma la requisición.
        $firmante('modificacion_presupuestal', 'L.C. JESÚS RODRÍGUEZ DEL MURO', '2026-10-13');
        $this->assertSame(
            ['nombre' => 'L.C. JESÚS RODRÍGUEZ DEL MURO', 'cargo' => 'TESORERÍA'], // debajo del nombre, la secretaría
            Firmante::titular('requisicion', 6, Carbon::parse('2026-10-11 14:00', 'America/Mexico_City')),
        );

        // El del propio documento tiene prioridad.
        $firmante('requisicion', 'FIRMANTE DE REQUISICIONES', '2026-01-01');
        $this->assertSame('FIRMANTE DE REQUISICIONES', Firmante::titular('requisicion', 6, Carbon::parse('2026-10-11 14:00', 'America/Mexico_City'))['nombre']);

        // Una fecha de Laravel (UTC) se compara en hora de México: 12 oct 23:30 local = 13 oct 05:30 UTC.
        $this->assertNull(Firmante::vigente('modificacion_presupuestal', 'autoriza', 6, Carbon::parse('2026-10-13 05:30', 'UTC')));
    }

    public function test_distribucion_mensual_en_centavos()
    {
        $meses = array_fill_keys(self::MESES, 0.1);
        $this->assertSame(0, DistribucionMensual::diferencia(1.2, $meses));
        $this->assertSame(1, DistribucionMensual::diferencia(1.21, $meses));
        $this->assertSame(-1, DistribucionMensual::diferencia(1.19, $meses));
        $this->assertNull(DistribucionMensual::mensaje('1.20', $meses));
        $this->assertStringContainsString('faltan $0.01', DistribucionMensual::mensaje(1.21, $meses));
    }

    private function excel(array $renglones): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($renglones);
        $ruta = tempnam(sys_get_temp_dir(), 'pre').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'presupuesto.xlsx', null, null, true);
    }
}
