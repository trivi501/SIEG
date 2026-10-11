<?php

namespace App\Providers;

use App\Models\Auditoria;
use App\Observers\AuditoriaObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurarAuditoria();
    }

    /**
     * Bitácora de auditoría para lo que no lleva el trait Auditable: roles y permisos (modelos de
     * Spatie) y los accesos al sistema. Los modelos propios usan App\Models\Concerns\Auditable.
     */
    protected function configurarAuditoria(): void
    {
        Role::observe(AuditoriaObserver::class);
        Permission::observe(AuditoriaObserver::class);

        Event::listen(Login::class, fn (Login $e) => Auditoria::acceso('inicio de sesión', $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Auditoria::acceso('cierre de sesión', $e->user));
        Event::listen(Failed::class, fn (Failed $e) => Auditoria::acceso(
            'acceso fallido',
            $e->user,
            'Correo: '.($e->credentials['email'] ?? '—'),
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
