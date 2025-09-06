<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query();

        // Aplicar búsqueda
        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });

        // Aplicar ordenamiento
        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');

        if (in_array($sortBy, ['id', 'name', 'address'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Obtener número de filas por página
        $perPage = $request->input('per_page', 20);

        // --- CAMBIO: --- Se pasa la columna 'business_hours' en la transformación.
        $branches = $query->paginate($perPage)->withQueryString()->through(fn($branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'business_hours' => $branch->business_hours, // Reemplazar el placeholder
        ]);

        return Inertia::render('Branch/Index', [
            'branches' => $branches,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction', 'per_page']),
        ]);
    }

    public function create()
    {
        // Podríamos pasar zonas horarias desde el backend si quisiéramos
        return Inertia::render('Branch/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'settings' => 'required|array',
            'settings.timezone' => 'required|string',
            'schedule' => 'required|array',
        ]);

        Branch::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'settings' => $validated['settings'],
            // Guardamos el horario directamente en la columna JSON
            'business_hours' => $validated['schedule'],
        ]);

        return redirect()->route('branches.index')->with('success', 'Sucursal creada con éxito.');
    }

    public function edit(Branch $branch)
    {
        return Inertia::render('Branch/Edit', [
            'branch' => $branch
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'settings' => 'required|array',
            'settings.timezone' => 'required|string',
            'schedule' => 'required|array',
        ]);

        $branch->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'settings' => $validated['settings'],
            'business_hours' => $validated['schedule'],
        ]);

        return redirect()->route('branches.index')->with('success', 'Sucursal actualizada con éxito.');
    }

    public function show(Branch $branch)
    {
        // Cargar la sucursal con el número de empleados asociados
        $branch->loadCount('employees');

        // La lógica de buscar un Schedule ya no es necesaria.
        // El horario de atención ahora está en la columna 'business_hours' de la sucursal.
        return Inertia::render('Branch/Show', [
            'branch' => $branch,
        ]);
    }

    public function destroy(Branch $branch)
    {
        // Regla de negocio: No se puede eliminar una sucursal si tiene empleados.
        if ($branch->employees()->count() > 0) {
            return back()->with('error', 'No se puede eliminar una sucursal que tiene empleados asignados.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Sucursal eliminada correctamente.');
    }
}
