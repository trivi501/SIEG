<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\EjercicioFiscal;
use App\Models\Firmante;
use App\Models\Proveedor;
use App\Models\ProveedorCuenta;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Catálogos sobre tablas nuevas (las del sistema anterior no existen en SQLite y sus catálogos dan 404).
 */
class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->artisan('permissions:sync');
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function excel(array $renglones): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($renglones);
        $ruta = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'vehiculos.xlsx', null, null, true);
    }

    public function test_sin_permiso_no_entra_al_catalogo()
    {
        $this->artisan('permissions:sync');
        $this->actingAs(User::factory()->create());

        $this->get('/catalogos/vehiculos')->assertForbidden();
        $this->post('/catalogos/vehiculos', ['numero_economico' => 'X1'])->assertForbidden();
    }

    public function test_catalogo_inexistente_o_sin_tabla_da_404()
    {
        $this->actingAs($this->admin());

        $this->get('/catalogos/no-existe')->assertNotFound();
        // cat_egreso_objeto_gasto es del sistema anterior: no existe en SQLite.
        $this->get('/catalogos/objeto-gasto')->assertNotFound();
    }

    public function test_alta_modificacion_y_baja_quedan_en_la_bitacora()
    {
        $this->actingAs($admin = $this->admin());

        $this->get('/catalogos')->assertOk();
        $this->get('/catalogos/vehiculos')->assertOk();

        $this->post('/catalogos/vehiculos', [
            'numero_economico' => 'pm-101',
            'placas' => 'zac-1234',
            'estado' => 'en_servicio',
            'resguardante' => 'Juan Pérez',
            'activo' => true,
        ])->assertSessionHasNoErrors();

        $vehiculo = Vehiculo::firstOrFail();
        $this->assertSame('PM-101', $vehiculo->numero_economico);
        $this->assertSame('ZAC-1234', $vehiculo->placas);

        $this->put("/catalogos/vehiculos/{$vehiculo->id}", [
            'numero_economico' => 'PM-101',
            'estado' => 'taller',
            'resguardante' => 'María López',
            'activo' => true,
        ])->assertSessionHasNoErrors();

        $this->post("/catalogos/vehiculos/{$vehiculo->id}/estado", ['activo' => false])->assertSessionHasNoErrors();
        $this->assertFalse($vehiculo->fresh()->activo);

        $movimientos = Auditoria::where('auditable_type', Vehiculo::class)->orderBy('id')->get();
        $this->assertSame(['alta', 'modificación', 'baja'], $movimientos->pluck('accion')->all());
        $this->assertSame($admin->id, $movimientos[1]->user_id);
        $this->assertSame('JUAN PÉREZ', $movimientos[1]->antes['resguardante']);
        $this->assertSame('MARÍA LÓPEZ', $movimientos[1]->despues['resguardante']);

        $this->getJson("/catalogos/vehiculos/{$vehiculo->id}/historial")
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.accion', 'baja');

        $this->get('/auditoria')->assertOk();
    }

    public function test_valida_campos_unicos_y_obligatorios()
    {
        $this->actingAs($this->admin());
        Vehiculo::create(['numero_economico' => 'PM-1', 'estado' => 'en_servicio']);

        $this->post('/catalogos/vehiculos', ['numero_economico' => 'pm-1', 'estado' => 'en_servicio'])
            ->assertSessionHasErrors('numero_economico');
        $this->post('/catalogos/vehiculos', ['numero_economico' => 'PM-2', 'estado' => 'volando'])
            ->assertSessionHasErrors('estado');
        $this->post('/catalogos/vehiculos', ['placas' => 'ABC'])
            ->assertSessionHasErrors(['numero_economico', 'estado']);
    }

    public function test_cuenta_bancaria_valida_la_clabe_y_deja_una_sola_principal()
    {
        $this->actingAs($this->admin());
        $proveedor = Proveedor::create(['nombre' => 'PAPELERÍA', 'rfc' => 'PAP010101AB1', 'activo' => true]);

        $this->post('/catalogos/cuentas-bancarias', ['proveedor_id' => $proveedor->id, 'clabe' => '032180000118359718'])
            ->assertSessionHasErrors('clabe');
        $this->post('/catalogos/cuentas-bancarias', ['proveedor_id' => $proveedor->id])
            ->assertSessionHasErrors('clabe');

        $this->post('/catalogos/cuentas-bancarias', ['proveedor_id' => $proveedor->id, 'clabe' => '032180000118359719', 'principal' => true])
            ->assertSessionHasNoErrors();
        $this->post('/catalogos/cuentas-bancarias', ['proveedor_id' => $proveedor->id, 'cuenta' => '123456', 'principal' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProveedorCuenta::where('principal', true)->count());
        $this->assertSame('123456', ProveedorCuenta::where('principal', true)->value('cuenta'));
    }

    public function test_importacion_revisa_antes_de_guardar()
    {
        $this->actingAs($this->admin());
        Vehiculo::create(['numero_economico' => 'PM-1', 'estado' => 'en_servicio', 'resguardante' => 'ANTES', 'marca' => 'NISSAN']);

        $this->get('/catalogos/vehiculos/plantilla')->assertOk()->assertDownload('plantilla_vehiculos.xlsx');

        // Con un renglón inválido no se puede importar nada.
        $this->post('/catalogos/vehiculos/importar/previa', ['archivo' => $this->excel([
            ['Número económico *', 'Resguardante', 'Estado *', 'Columna rara'],
            ['PM-2', 'Nuevo', 'En servicio', 'x'],
            ['PM-3', 'Otro', 'Volando', 'x'],
        ])])->assertSessionHasNoErrors();

        $token = session('importacion');
        $analisis = Cache::get('importacion:'.auth()->id().":{$token}");
        $this->assertCount(1, $analisis['errores']);
        $this->assertSame(3, $analisis['errores'][0]['fila']);
        $this->assertSame(['Columna rara'], $analisis['columnasIgnoradas']);

        $this->post('/catalogos/vehiculos/importar', ['token' => $token])->assertSessionHas('error');
        $this->assertSame(1, Vehiculo::count());

        // Archivo correcto: una alta y una modificación (la celda vacía de Marca no borra el valor).
        $this->post('/catalogos/vehiculos/importar/previa', ['archivo' => $this->excel([
            ['numero_economico', 'Resguardante', 'Marca', 'Estado', 'Activo'],
            ['PM-1', 'Después', null, 'taller', 'sí'],
            ['PM-2', 'Nuevo', 'Ford', null, null],
        ])]);
        $token = session('importacion');

        $this->post('/catalogos/vehiculos/importar', ['token' => $token])->assertSessionHas('success');

        $this->assertSame(2, Vehiculo::count());
        $existente = Vehiculo::where('numero_economico', 'PM-1')->first();
        $this->assertSame('DESPUÉS', $existente->resguardante);
        $this->assertSame('NISSAN', $existente->marca);
        $this->assertSame('taller', $existente->estado);
        $nuevo = Vehiculo::where('numero_economico', 'PM-2')->first();
        $this->assertSame('en_servicio', $nuevo->estado);
        $this->assertTrue($nuevo->activo);

        // Los cambios de la importación comparten lote.
        $lotes = Auditoria::where('auditable_type', Vehiculo::class)->whereNotNull('lote')->pluck('lote')->unique();
        $this->assertCount(1, $lotes);

        // El token ya se usó.
        $this->post('/catalogos/vehiculos/importar', ['token' => $token])->assertSessionHas('error');
    }

    public function test_firmante_vigente_prefiere_el_de_la_unidad_y_respeta_vigencias()
    {
        $tipo = TipoDocumento::where('clave', 'orden_compra')->firstOrFail();
        $base = ['tipo_documento_id' => $tipo->id, 'rol' => 'autoriza', 'cargo' => 'Director', 'activo' => true];

        Firmante::create([...$base, 'nombre' => 'GENERAL VIEJO', 'vigente_desde' => '2025-01-01', 'vigente_hasta' => '2025-12-31']);
        Firmante::create([...$base, 'nombre' => 'GENERAL', 'vigente_desde' => '2026-01-01']);
        Firmante::create([...$base, 'nombre' => 'DE LA UNIDAD 7', 'vigente_desde' => '2026-01-01', 'id_cat_egreso_unidad_administrativa' => 7]);

        $this->assertSame('GENERAL VIEJO', Firmante::vigente('orden_compra', 'autoriza', null, now()->setDate(2025, 6, 1))?->nombre);
        $this->assertSame('GENERAL', Firmante::vigente('orden_compra', 'autoriza', 3, now()->setDate(2026, 6, 1))?->nombre);
        $this->assertSame('DE LA UNIDAD 7', Firmante::vigente('orden_compra', 'autoriza', 7, now()->setDate(2026, 6, 1))?->nombre);
        $this->assertNull(Firmante::vigente('orden_compra', 'elabora', 7));
    }

    public function test_ejercicio_cerrado()
    {
        EjercicioFiscal::create(['año' => 2025, 'estado' => 'cerrado']);
        EjercicioFiscal::create(['año' => 2026, 'estado' => 'abierto']);

        $this->assertTrue(EjercicioFiscal::estaCerrado(2025));
        $this->assertFalse(EjercicioFiscal::estaCerrado(2026));
        $this->assertFalse(EjercicioFiscal::estaCerrado(2030));
    }

    public function test_clabe_valida()
    {
        $this->assertTrue(ProveedorCuenta::clabeValida('032180000118359719'));
        $this->assertFalse(ProveedorCuenta::clabeValida('032180000118359718'));
        $this->assertFalse(ProveedorCuenta::clabeValida('12345'));
    }
}
