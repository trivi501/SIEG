<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\EgresoUnidadAdministrativa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/users/index', [
            'users' => User::with('roles', 'unidadAdministrativa')->paginate(15)->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
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
            'roles' => \Spatie\Permission\Models\Role::all()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
            'unidades' => $this->unidades(),
        ]);
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
            'id_cat_egreso_unidad_administrativa' => $this->reglaUnidad(),
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'id_cat_egreso_unidad_administrativa' => $validated['id_cat_egreso_unidad_administrativa'] ?? null,
        ]);

        if (!empty($validated['roles'])) {
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
                'email' => $user->email,
                'id_cat_egreso_unidad_administrativa' => $user->id_cat_egreso_unidad_administrativa,
                'roles' => $user->roles->pluck('id'),
            ],
            'roles' => \Spatie\Permission\Models\Role::all()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
            'unidades' => $this->unidades($user->id_cat_egreso_unidad_administrativa),
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
            'id_cat_egreso_unidad_administrativa' => $this->reglaUnidad(),
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'id_cat_egreso_unidad_administrativa' => $validated['id_cat_egreso_unidad_administrativa'] ?? null,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (isset($validated['roles'])) {
            $this->sincronizarRoles($user, $validated['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
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
