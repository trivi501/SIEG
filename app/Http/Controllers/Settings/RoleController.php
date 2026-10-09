<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')->paginate(50);

        $roles->getCollection()->transform(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => $role->permissions->count(),
            'permissions' => $role->permissions->pluck('name'),
            'created_at' => $role->created_at?->diffForHumans(),
        ]);

        return Inertia::render('settings/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/roles/create', [
            'permissions' => Permission::all()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'nombre_mostrar' => $p->nombre_mostrar,
                'categoria' => $p->categoria,
                'guard_name' => $p->guard_name,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $validated['name']]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('settings/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('id'),
            ],
            'permissions' => Permission::all()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'nombre_mostrar' => $p->nombre_mostrar,
                'categoria' => $p->categoria,
                'guard_name' => $p->guard_name,
            ]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => "required|string|max:255|unique:roles,name,{$role->id}",
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
