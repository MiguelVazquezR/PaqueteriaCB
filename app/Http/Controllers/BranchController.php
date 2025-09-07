<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Traits\HandlesQueryFiltering;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class BranchController extends Controller implements HasMiddleware
{
    use HandlesQueryFiltering;

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_sucursales', only: ['index', 'show']),
            new Middleware('can:crear_sucursales', only: ['create', 'store']),
            new Middleware('can:editar_sucursales', only: ['edit', 'update']),
            new Middleware('can:eliminar_sucursales', only: ['destroy']),
        ];
    }
    
    public function index(Request $request)
    {
        $query = $this->applyFilters(
            $request,
            Branch::query(),
            searchableColumns: ['name', 'address', 'phone'],
            defaultSort: 'id',
            sortableColumns: ['id', 'name', 'address']
        );

        $perPage = $request->input('per_page', 20);
        $branches = BranchResource::collection($query->paginate($perPage)->withQueryString());

        return Inertia::render('Branch/Index', [
            'branches' => $branches,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction', 'per_page']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Branch/Create');
    }

    // Usamos el FormRequest para la validación
    public function store(StoreBranchRequest $request)
    {
        Branch::create($request->validated());
        return redirect()->route('branches.index')->with('success', 'Sucursal creada con éxito.');
    }

    public function edit(Branch $branch)
    {
        return Inertia::render('Branch/Edit', ['branch' => $branch]);
    }

    // Usamos el FormRequest para la validación
    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());
        return redirect()->route('branches.index')->with('success', 'Sucursal actualizada con éxito.');
    }

    public function show(Branch $branch)
    {
        $branch->loadCount('employees');
        return Inertia::render('Branch/Show', ['branch' => $branch]);
    }

    public function destroy(Branch $branch)
    {
        if ($branch->employees()->exists()) {
            return back()->with('error', 'No se puede eliminar una sucursal que tiene empleados asignados.');
        }

        $branch->delete();
        return redirect()->route('branches.index')->with('success', 'Sucursal eliminada correctamente.');
    }
}