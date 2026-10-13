<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\Regidor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Unidad del presupuesto a la que quedan los enlaces de los regidores. */
    private const CLAVE_CABILDO = '140';

    public function index(): Response
    {
        return Inertia::render('settings/users/index', [
            'users' => User::with('roles', 'unidadAdministrativa', 'regidor')->paginate(15)->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->nombreConTitulo(),
                'cargo' => $user->cargo,
                'regidor' => $user->regidor ? "{$user->regidor->cargo} {$user->regidor->nombreConTitulo()}" : null,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->diffForHumans(),
                'unidad' => $user->unidadAdministrativa?->nombre,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->diffForHumans(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/users/create', [
            'roles' => Role::all()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
            'unidades' => $this->unidades(),
            'regidores' => $this->regidores(),
        ]);
    }

    /** Regidores activos (y el del usuario aunque esté inactivo) para "Pertenece a: regidor". */
    private function regidores(?int $actual = null): array
    {
        if (! Schema::hasTable('regidores')) {
            return [];
        }

        return Regidor::where(fn ($q) => $q->where('activo', true)->orWhere('id', $actual))
            ->orderBy('nombre')->get()
            ->map(fn (Regidor $r) => ['id' => $r->id, 'nombre' => "{$r->nombreConTitulo()} ({$r->cargo})"])
            ->all();
    }

    /** Reglas comunes del alta y la edición: título, cargo y a quién pertenece (secretaría o regidor). */
    private function reglasAdscripcion(): array
    {
        return [
            'titulo' => 'nullable|string|max:30',
            'cargo' => 'nullable|string|max:150',
            // Sin secretaría es válido (p. ej. Sindicatura o Egresos, que ven las CLC de todas).
            'pertenece' => 'nullable|in:secretaria,regidor',
            'id_cat_egreso_unidad_administrativa' => $this->reglaUnidad(),
            'regidor_id' => ['nullable', 'required_if:pertenece,regidor', 'integer', Rule::exists('regidores', 'id')],
        ];
    }

    /**
     * Datos de adscripción a guardar: con regidor, la secretaría es Cabildo (clave 140, para su
     * presupuesto y requisiciones); con secretaría, sin regidor.
     */
    private function adscripcion(array $validated): array
    {
        $datos = [
            'titulo' => filled($validated['titulo'] ?? null) ? mb_strtoupper(trim($validated['titulo'])) : null,
            'cargo' => filled($validated['cargo'] ?? null) ? mb_strtoupper(trim($validated['cargo'])) : null,
        ];

        if (($validated['pertenece'] ?? null) === 'regidor') {
            $cabildo = Schema::hasTable('cat_egreso_unidad_administrativa')
                ? EgresoUnidadAdministrativa::where('clave', self::CLAVE_CABILDO)->where('activo', true)->orderByDesc('año')->value('id_cat_egreso_unidad_administrativa')
                : null;

            return $datos + [
                'regidor_id' => $validated['regidor_id'],
                'id_cat_egreso_unidad_administrativa' => $cabildo ?? ($validated['id_cat_egreso_unidad_administrativa'] ?? null),
            ];
        }

        return $datos + ['regidor_id' => null, 'id_cat_egreso_unidad_administrativa' => $validated['id_cat_egreso_unidad_administrativa'] ?? null];
    }

    /**
     * Secretarías y direcciones para asignar al usuario: una por clave (la del año más reciente),
     * solo activas.
     */
    private function unidades(?int $actual = null): array
    {
        if (! Schema::hasTable('cat_egreso_unidad_administrativa')) {
            return [];
        }

        // La que ya tiene el usuario siempre aparece (aunque esté inactiva), para no perderla al guardar.
        return EgresoUnidadAdministrativa::where(fn ($q) => $q->where('activo', true)->orWhere('id_cat_egreso_unidad_administrativa', $actual))
            ->orderByRaw('id_cat_egreso_unidad_administrativa = ? desc', [$actual ?? 0])
            ->orderByDesc('año')
            ->get(['id_cat_egreso_unidad_administrativa', 'clave', 'nombre'])
            ->unique('clave')
            ->sortBy('clave')
            ->map(fn ($u) => ['id' => $u->id_cat_egreso_unidad_administrativa, 'nombre' => "{$u->clave} — {$u->nombre}"])
            ->values()
            ->all();
    }

    private function reglaUnidad(): array
    {
        return Schema::hasTable('cat_egreso_unidad_administrativa')
            ? ['nullable', 'integer', Rule::exists('cat_egreso_unidad_administrativa', 'id_cat_egreso_unidad_administrativa')]
            : ['nullable', 'integer'];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            ...$this->reglasAdscripcion(),
        ], $this->mensajesAdscripcion());

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            ...$this->adscripcion($validated),
        ]);

        if (! empty($validated['roles'])) {
            $this->sincronizarRoles($user, $validated['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('settings/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'titulo' => $user->titulo,
                'cargo' => $user->cargo,
                'email' => $user->email,
                'id_cat_egreso_unidad_administrativa' => $user->id_cat_egreso_unidad_administrativa,
                'regidor_id' => $user->regidor_id,
                'roles' => $user->roles->pluck('id'),
            ],
            'roles' => Role::all()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
            'unidades' => $this->unidades($user->id_cat_egreso_unidad_administrativa),
            'regidores' => $this->regidores($user->regidor_id),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            ...$this->reglasAdscripcion(),
        ], $this->mensajesAdscripcion());

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...$this->adscripcion($validated),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (isset($validated['roles'])) {
            $this->sincronizarRoles($user, $validated['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    private function mensajesAdscripcion(): array
    {
        return [
            'regidor_id.required_if' => 'Selecciona el regidor al que pertenece.',
        ];
    }

    /** El cambio de roles de un usuario queda en la bitácora de auditoría (por nombre de rol). */
    private function sincronizarRoles(User $user, array $roles): void
    {
        $antes = $user->roles()->pluck('name')->all();
        $user->roles()->sync($roles);

        Auditoria::relacion($user, 'roles', $antes, $user->roles()->pluck('name')->all());
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete yourself.')]);

            return to_route('users.index');
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }
}
