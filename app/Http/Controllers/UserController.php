<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource; // Necesitarás crear este recurso
use App\Models\Branch;
use App\Models\User;
use App\Models\Schedule;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public function __construct(private UserService $userService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_usuarios', only: ['index', 'show']),
            new Middleware('can:crear_usuarios', only: ['create', 'store']),
            new Middleware('can:editar_usuarios', only: ['edit', 'update']),
            new Middleware('can:eliminar_usuarios', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->where('users.id', '!=', 1)
            // 1. Carga ansiosa (Eager Loading) para obtener los datos de las relaciones.
            // Siempre obtendremos un modelo User con su relación employee (o null).
            ->with(['employee.branch', 'roles'])
            // 2. Unimos con `employees` y `branches` para poder ordenar por sus columnas.
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            // 3. Seleccionamos todas las columnas de `users` para asegurar que obtenemos modelos Eloquent completos.
            ->select('users.*');

        // Lógica de Búsqueda
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('employees.employee_number', 'like', "%{$search}%")
                    // Busca por nombre completo del empleado
                    ->orWhere(DB::raw("CONCAT(employees.first_name, ' ', employees.last_name)"), 'like', "%{$search}%");
            });
        });

        // Lógica de Ordenamiento
        $sortBy = $request->input('sort_by');
        $sortDirection = $request->input('sort_direction', 'asc');

        $sortableColumns = [
            'name' => DB::raw("CASE WHEN employees.first_name IS NOT NULL THEN CONCAT(employees.first_name, ' ', employees.last_name) ELSE users.name END"),
            'employee_number' => 'employees.employee_number',
            'position' => 'employees.position',
            'branch' => 'branches.name',
            'status' => 'employees.is_active',
        ];

        if ($sortBy && isset($sortableColumns[$sortBy])) {
            $query->orderBy($sortableColumns[$sortBy], $sortDirection);
        } else {
            $query->orderBy('users.id', 'desc'); // Orden por defecto
        }

        $paginator = $query->paginate($request->input('per_page', 20))->withQueryString();

        // Se corrigió el return duplicado. Ahora se pasa el recurso a la vista Inertia.
        return Inertia::render('User/Index', [
            'users' => UserResource::collection($paginator),
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('User/Create', [
            'branches' => Branch::where('is_active', true)->get(['id', 'name']),
            'roles' => Role::with('permissions:name')->get(['id', 'name']),
            'schedules' => Schedule::with('details')->get(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->createEmployeeAndUser($request->validated());
        return redirect()->route('users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        $user->load(['employee.schedules', 'roles:id']);
        return Inertia::render('User/Edit', [
            'user' => $user,
            'branches' => Branch::where('is_active', true)->get(['id', 'name']),
            'roles' => Role::with('permissions:name')->get(['id', 'name']),
            'schedules' => Schedule::with('details')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->updateEmployeeAndUser($user, $request->validated());
        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function show(User $user)
    {
        $user->load(['employee.branch', 'employee.schedules.details', 'employee.vacationLedger']);
        // return UserResource::make($user);
        return Inertia::render('User/Show', [
            'user' => UserResource::make($user),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === 1) {
            return back()->with('error', 'El usuario administrador principal no puede ser eliminado.');
        }

        // La lógica de la transacción ahora puede estar en un método del service si se vuelve más compleja
        $user->employee?->delete();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }
}
