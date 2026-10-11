<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Bitácora de auditoría fuera de los catálogos: accesos, usuarios, roles y tickets. */
class AuditoriaSistemaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->artisan('permissions:sync');
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    public function test_registra_inicio_cierre_y_acceso_fallido()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'));

        $accesos = Auditoria::where('auditable_type', User::class)
            ->whereIn('accion', ['inicio de sesión', 'cierre de sesión', 'acceso fallido'])
            ->orderBy('id')
            ->get();

        $this->assertSame(['acceso fallido', 'inicio de sesión', 'cierre de sesión'], $accesos->pluck('accion')->all());
        $this->assertNull($accesos[0]->user_id);
        $this->assertSame("Correo: {$user->email}", $accesos[0]->descripcion);
        $this->assertSame($user->id, $accesos[1]->user_id);
        $this->assertSame($user->id, $accesos[2]->user_id);
    }

    public function test_cambios_de_usuario_roles_y_contrasena_sin_guardar_el_valor()
    {
        $this->actingAs($admin = $this->admin());
        $usuario = User::factory()->create(['name' => 'ANTES']);
        $rol = Role::findOrCreate('Recursos Materiales', 'web');

        $this->patch(route('users.update', $usuario), [
            'name' => 'DESPUÉS',
            'email' => $usuario->email,
            'password' => 'otra-contrasena-1',
            'roles' => [$rol->id],
        ])->assertSessionHasNoErrors();

        $movimientos = Auditoria::where('auditable_type', User::class)
            ->where('auditable_id', (string) $usuario->id)
            ->where('accion', 'modificación')
            ->orderBy('id')
            ->get();

        $this->assertSame('ANTES', $movimientos[0]->antes['name']);
        $this->assertSame('DESPUÉS', $movimientos[0]->despues['name']);
        $this->assertSame('[oculto]', $movimientos[1]->despues['password']);
        $this->assertStringNotContainsString('otra-contrasena-1', json_encode($movimientos->toArray()));
        $this->assertSame(['roles' => ''], $movimientos[2]->antes);
        $this->assertSame(['roles' => 'Recursos Materiales'], $movimientos[2]->despues);
        $this->assertSame($admin->id, $movimientos[2]->user_id);
    }

    public function test_usuario_pertenece_a_una_secretaria_o_direccion()
    {
        $this->actingAs($this->admin());
        $usuario = User::factory()->create();

        $this->patch(route('users.update', $usuario), [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'id_cat_egreso_unidad_administrativa' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $usuario->fresh()->id_cat_egreso_unidad_administrativa);
        $this->assertSame(
            ['id_cat_egreso_unidad_administrativa' => 7],
            Auditoria::where('auditable_type', User::class)->where('auditable_id', (string) $usuario->id)->latest('id')->first()->despues,
        );

        $this->get('/secretarias')->assertRedirect('/catalogos/unidades-administrativas');
    }

    public function test_cambio_de_permisos_de_un_rol()
    {
        $this->actingAs($this->admin());
        $rol = Role::findOrCreate('Consulta', 'web');
        $permiso = Permission::findByName('auditoria-index', 'web');

        $this->patch(route('roles.update', $rol), ['name' => 'Consulta', 'permissions' => [$permiso->id]])
            ->assertSessionHasNoErrors();

        $movimiento = Auditoria::where('auditable_type', Role::class)
            ->where('auditable_id', (string) $rol->id)
            ->latest('id')
            ->first();

        $this->assertSame(['permisos' => 'auditoria-index'], $movimiento->despues);

        // Guardar sin cambios no agrega movimientos.
        $antes = Auditoria::count();
        $this->patch(route('roles.update', $rol), ['name' => 'Consulta', 'permissions' => [$permiso->id]]);
        $this->assertSame($antes, Auditoria::count());
    }

    public function test_ticket_de_soporte_y_pantalla_de_bitacora()
    {
        $this->actingAs($this->admin());

        $this->post(route('support-tickets.store'), [
            'title' => 'No abre el PDF',
            'description' => 'Detalle',
            'priority' => 'media',
        ])->assertSessionHasNoErrors();

        $ticket = SupportTicket::firstOrFail();
        $this->assertTrue(Auditoria::where('auditable_type', SupportTicket::class)
            ->where('auditable_id', (string) $ticket->id)->where('accion', 'alta')->exists());

        $this->get('/auditoria')->assertOk();
        $this->get('/auditoria?tipo='.urlencode(User::class).'&accion=alta')->assertOk();
    }
}
