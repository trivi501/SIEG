<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $permissions = Permission::query()
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->when($request->filled('nombre_mostrar'), fn ($q) => $q->where('nombre_mostrar', 'like', '%' . $request->nombre_mostrar . '%'))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', 'like', '%' . $request->categoria . '%'))
            ->when($request->filled('guard_name'), fn ($q) => $q->where('guard_name', 'like', '%' . $request->guard_name . '%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'nombre_mostrar' => $p->nombre_mostrar,
                'categoria' => $p->categoria,
                'guard_name' => $p->guard_name,
                'created_at' => $p->created_at?->diffForHumans(),
            ]);

        return Inertia::render('settings/permissions/index', [
            'permissions' => $permissions,
            'filters' => $request->only(['name', 'nombre_mostrar', 'categoria', 'guard_name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/permissions/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'nombre_mostrar' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
        ]);

        Permission::create([
            'name' => $validated['name'],
            'nombre_mostrar' => $validated['nombre_mostrar'],
            'categoria' => $validated['categoria'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('permissions.index');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('settings/permissions/edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'nombre_mostrar' => $permission->nombre_mostrar,
                'categoria' => $permission->categoria,
                'guard_name' => $permission->guard_name,
            ],
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => "required|string|max:255|unique:permissions,name,{$permission->id}",
            'nombre_mostrar' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
        ]);

        $permission->update([
            'name' => $validated['name'],
            'nombre_mostrar' => $validated['nombre_mostrar'],
            'categoria' => $validated['categoria'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('permissions.index');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('permissions.index');
    }
}
