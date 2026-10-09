<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Secretaria;
use App\Models\EgresoUnidadAdministrativa;
use Illuminate\Http\Request;

class SecretariaController extends Controller
{
    public function index()
    {
        $secretarias = Secretaria::withCount('unidadesAdministrativas')->orderBy('nombre')->paginate(50);
        return Inertia::render('secretarias/Index', compact('secretarias'));
    }

    public function create()
    {
        $unidadesAdministrativas = EgresoUnidadAdministrativa::orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'nombre', 'año', 'secretaria_id']);
        return Inertia::render('secretarias/Create', compact('unidadesAdministrativas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'prefijo' => 'nullable|string|max:10',
            'unidades_administrativas' => 'nullable|array',
            'unidades_administrativas.*' => 'exists:cat_egreso_unidad_administrativa,id_cat_egreso_unidad_administrativa',
        ]);

        $secretaria = Secretaria::create([
            'nombre' => $validated['nombre'],
            'prefijo' => $validated['prefijo'] ?? null,
        ]);

        if (!empty($validated['unidades_administrativas'])) {
            EgresoUnidadAdministrativa::whereIn('id_cat_egreso_unidad_administrativa', $validated['unidades_administrativas'])
                ->update(['secretaria_id' => $secretaria->id]);
        }

        return redirect()->route('secretarias.index')->with('success', 'Secretaría creada exitosamente.');
    }

    public function show(Secretaria $secretaria)
    {
        $secretaria->load('users', 'unidadesAdministrativas');
        return Inertia::render('secretarias/Show', compact('secretaria'));
    }

    public function edit(Secretaria $secretaria)
    {
        $secretaria->load('unidadesAdministrativas');
        $unidadesAdministrativas = EgresoUnidadAdministrativa::orderBy('nombre')->get(['id_cat_egreso_unidad_administrativa', 'nombre', 'año', 'secretaria_id']);
        return Inertia::render('secretarias/Edit', compact('secretaria', 'unidadesAdministrativas'));
    }

    public function update(Request $request, Secretaria $secretaria)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'prefijo' => 'nullable|string|max:10',
            'unidades_administrativas' => 'nullable|array',
            'unidades_administrativas.*' => 'exists:cat_egreso_unidad_administrativa,id_cat_egreso_unidad_administrativa',
        ]);

        $secretaria->update([
            'nombre' => $validated['nombre'],
            'prefijo' => $validated['prefijo'] ?? null,
        ]);

        // Las unidades que ya no vienen marcadas se liberan (quedan sin secretaría), las marcadas se asignan a esta.
        EgresoUnidadAdministrativa::where('secretaria_id', $secretaria->id)->update(['secretaria_id' => null]);
        if (!empty($validated['unidades_administrativas'])) {
            EgresoUnidadAdministrativa::whereIn('id_cat_egreso_unidad_administrativa', $validated['unidades_administrativas'])
                ->update(['secretaria_id' => $secretaria->id]);
        }

        return redirect()->route('secretarias.index')->with('success', 'Secretaría actualizada exitosamente.');
    }

    public function destroy(Secretaria $secretaria)
    {
        $secretaria->delete();
        return redirect()->route('secretarias.index')->with('success', 'Secretaría eliminada exitosamente.');
    }
}
