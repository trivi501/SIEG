<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CorridaSaldo;
use App\Models\EgresoRequisicion;
use App\Models\EjercicioFiscal;
use App\Models\ModificacionPresupuestal;
use App\Models\OrdenCompra;
use App\Models\PresupuestoEgreso;
use App\Models\Proveedor;
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

        // Corrida automática del 1 de marzo (el comando usa el mes actual).
        $this->travelTo(Carbon::parse('2026-03-01 06:05:00', 'UTC'));
        $this->artisan('presupuesto:correr-saldos')->assertSuccessful();

        $corrida = CorridaSaldo::where('ejercicio', 2026)->where('mes', 3)->firstOrFail();
        $this->assertSame('automatica', $corrida->origen);
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

        $this->post("/ordenes-compra/{$orden->id}/pago", $pago)->assertSessionHasNoErrors();
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
        // Sin movimiento = 0, no celda vacía (modificado y en trámite).
        $this->assertNotNull($hoja->getCell('J5')->getValue());
        $this->assertEquals(0, $hoja->getCell('J5')->getValue());
        $this->assertNotNull($hoja->getCell('M5')->getValue());
        $this->assertEquals(300, $hoja->getCell('O5')->getValue()); // pagado
        $this->assertSame('#,##0.00;[Red]-#,##0.00', $hoja->getStyle('L5')->getNumberFormat()->getFormatCode());
        $this->assertSame('=SUM(L5:L5)', $hoja->getCell('L6')->getValue());

        // Una búsqueda sin resultados exporta solo encabezados y totales en cero.
        file_put_contents($ruta, $this->get('/requisiciones/gasto/exportar?buscar=inexistente')->streamedContent());
        $this->assertSame('Total', IOFactory::load($ruta)->getActiveSheet()->getCell('A5')->getValue());

        // Un solo pago por orden, y no antes de la orden.
        $this->post("/ordenes-compra/{$orden->id}/pago", ['pago_fecha' => '2026-10-06', 'pago_forma' => 'cheque', 'pago_referencia' => '9'])
            ->assertSessionHas('error');
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

        // El movimiento cuenta en el periodo de cada lado, no en el mes en que se autorizó.
        $saldos = new SaldosMensuales;
        $this->assertEquals(-50, $saldos->situacion(2026, 12)[$emisor]['modificado_mes']);
        $this->assertEquals(0, $saldos->situacion(2026, 10)[$emisor]['modificado_mes']);
        $this->assertEquals(50, $saldos->situacion(2026, 10)[$receptor]['modificado_mes']);

        $this->get('/modificaciones-presupuestales')->assertOk()
            ->assertInertia(fn ($p) => $p->where('modificaciones.data.0.origen_mes', 12)->where('meses.12', 'diciembre'));
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

        $this->actingAs($area)->post('/requisiciones/1/enviar')->assertSessionHas('success');
        $this->assertSame(2, $estado());
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
