<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::withCount('ordenesCompra');

        if ($filtro = $request->input('buscar')) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$filtro}%")->orWhere('rfc', 'like', "%{$filtro}%"));
        }

        return Inertia::render('proveedores/Index', [
            'proveedores' => $query->orderBy('nombre')->paginate(20)->withQueryString(),
            'filters' => $request->only(['buscar']),
        ]);
    }

    private function reglas(?Proveedor $proveedor = null): array
    {
        return [
            'nombre' => 'required|string|max:300',
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', Rule::unique('proveedores', 'rfc')->ignore($proveedor?->id)],
            'correo' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'domicilio' => 'nullable|string|max:500',
            'activo' => 'boolean',
        ];
    }

    private function normalizar(Request $request): void
    {
        $request->merge(['rfc' => $request->filled('rfc') ? mb_strtoupper(trim($request->input('rfc'))) : null]);
    }

    public function store(Request $request)
    {
        $this->normalizar($request);
        Proveedor::create($request->validate($this->reglas(), ['rfc.regex' => 'El RFC no tiene un formato válido.']));

        return back()->with('success', 'Proveedor registrado.');
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $this->normalizar($request);
        $proveedor->update($request->validate($this->reglas($proveedor), ['rfc.regex' => 'El RFC no tiene un formato válido.']));

        return back()->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Proveedor $proveedor)
    {
        // Con órdenes de compra no se borra (la FK lo impide): solo se desactiva.
        if ($proveedor->ordenesCompra()->exists()) {
            $proveedor->update(['activo' => false]);

            return back()->with('success', 'El proveedor tiene órdenes de compra; se desactivó en lugar de eliminarse.');
        }

        $proveedor->delete();

        return back()->with('success', 'Proveedor eliminado.');
    }
}
