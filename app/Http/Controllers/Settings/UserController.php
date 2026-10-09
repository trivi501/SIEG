<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Secretaria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/users/index', [
            'users' => User::with('roles', 'secretaria')->paginate(15)->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->diffForHumans(),
                'secretaria' => $user->secretaria?->nombre,
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
            'secretarias' => Secretaria::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            'secretaria_id' => 'nullable|exists:secretarias,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'secretaria_id' => $validated['secretaria_id'] ?? null,
        ]);

        if (!empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        $user->load('roles', 'secretaria');

        return Inertia::render('settings/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'secretaria_id' => $user->secretaria_id,
                'roles' => $user->roles->pluck('id'),
            ],
            'roles' => \Spatie\Permission\Models\Role::all()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
            'secretarias' => Secretaria::orderBy('nombre')->get(['id', 'nombre']),
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
            'secretaria_id' => 'nullable|exists:secretarias,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'secretaria_id' => $validated['secretaria_id'] ?? null,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
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
