<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Consulta 1: Obtiene todos los EMPLEADOS y sus datos de usuario (si existen)
        $employeesQuery = Employee::query()
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            ->select(
                'users.id as user_id',
                'users.email',
                'users.name as user_name',
                'users.profile_photo_path',
                'employees.id as employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.position',
                'employees.is_active',
                'employees.phone',
                'branches.name as branch_name'
            );

        // Consulta 2: Obtiene todos los USUARIOS que NO tienen un empleado asociado
        $usersOnlyQuery = User::query()
            ->whereDoesntHave('employee')
            ->select(
                'users.id as user_id',
                'users.email',
                'users.name as user_name',
                'users.profile_photo_path',
                DB::raw('NULL as employee_id'),
                DB::raw('NULL as first_name'),
                DB::raw('NULL as last_name'),
                DB::raw('NULL as employee_number'),
                DB::raw('"Usuario del Sistema" as position'),
                DB::raw('1 as is_active'),
                DB::raw('NULL as phone'),
                DB::raw('NULL as branch_name')
            );

        $query = $usersOnlyQuery->union($employeesQuery);
        $finalQuery = DB::query()->fromSub($query, 'people');

        // --- LÓGICA DE ORDENAMIENTO CORREGIDA ---
        $sortableColumns = [
            'employee_number' => 'employee_number',
            'name' => 'first_name',
            'position' => 'position',
            'branch' => 'branch_name',
            'status' => 'is_active',
        ];

        $sortBy = $request->input('sort_by');
        $sortDirection = $request->input('sort_direction');

        // Si se especifica una columna de ordenamiento válida, la usamos.
        if ($sortBy && isset($sortableColumns[$sortBy])) {
            $sortColumn = $sortableColumns[$sortBy];

            // Si además se especifica una dirección, la usamos.
            if ($sortDirection) {
                // Ordenamiento principal
                $finalQuery->orderBy(DB::raw("CASE WHEN $sortColumn IS NULL THEN 1 ELSE 0 END"), 'ASC'); // Nulos al final
                $finalQuery->orderBy($sortColumn, $sortDirection);

                // Ordenamiento secundario para el nombre
                if ($sortBy === 'name') {
                    $finalQuery->orderBy('last_name', $sortDirection);
                }
            } else {
                // Si no hay dirección, aplicamos el orden por defecto
                $finalQuery->orderBy('employee_number', 'asc');
            }
        } else {
            // Si no se especifica ninguna columna, aplicamos el orden por defecto
            $finalQuery->orderBy('employee_number', 'asc');
        }

        // --- BÚSQUEDA ---
        $finalQuery->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        // --- PAGINACIÓN Y TRANSFORMACIÓN ---
        $perPage = $request->input('per_page', 20);
        $paginator = $finalQuery->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(function ($person) {
            $name = $person->first_name ? trim($person->first_name . ' ' . $person->last_name) : $person->user_name;

            return [
                'id' => $name,
                'name' => $person->first_name ? trim($person->first_name . ' ' . $person->last_name) : $person->user_name,
                'employee_number' => $person->employee_number,
                'position' => $person->position,
                'branch' => $person->branch_name,
                'phone' => $person->phone,
                'role' => $person->user_id ? ($person->employee_id ? 'Empleado' : 'Solo Usuario') : 'Solo Empleado',
                'status' => (bool)$person->is_active,
                'avatar_url' => $person->profile_photo_path
                    ? Storage::url($person->profile_photo_path)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=700DBC&background=D8BBFC',
                'user_id' => $person->user_id,
                'employee_id' => $person->employee_id,
            ];
        });

        return Inertia::render('User/Index', [
            'users' => $paginator,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('User/Create', [
            'branches' => Branch::where('is_active', true)->get(['id', 'name']),
            'roles' => Role::with('permissions:name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            // Información Personal y Laboral
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'employee_number' => 'required|string|max:50|unique:employees',
            'hire_date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'position' => 'required|string|max:255',
            'curp' => 'nullable|string|max:18|unique:employees',
            'rfc' => 'nullable|string|max:13|unique:employees',
            'nss' => 'nullable|string|max:11|unique:employees',
            'base_salary' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',

            // Contacto de Emergencia
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:255',

            // Acceso al sistema (condicional)
            'create_user_account' => 'required|boolean',
            'email' => ['required_if:create_user_account,true', 'nullable', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required_if:create_user_account,true', 'nullable', 'string', 'min:8'],
            'role_id' => ['required_if:create_user_account,true', 'nullable', 'exists:roles,id'],
            'facial_image' => ['nullable', 'image', 'max:1024'], // 1MB max size
        ]);

        // Crear el empleado primero
        $employee = Employee::create($request->except(['create_user_account', 'email', 'password', 'facial_image']));

        // Si se indicó crear cuenta de usuario, la creamos y la asociamos
        if ($request->input('create_user_account')) {
            $user = User::create([
                'name' => $request->input('first_name') . ' ' . $request->input('last_name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            // Si se subió una imagen, usamos el método de Jetstream para guardarla como foto de perfil.
            if ($request->hasFile('facial_image')) {
                $user->updateProfilePhoto($request->file('facial_image'));
            }

            // ✨ Asignar el rol al nuevo usuario
            $role = Role::findById($request->input('role_id'));
            $user->assignRole($role);

            // Ligamos el usuario al empleado
            $employee->user_id = $user->id;
            $employee->save();
        }

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        // Cargamos ambas relaciones: el empleado y los roles asignados al usuario.
        $user->load(['employee', 'roles:id']);

        return Inertia::render('User/Edit', [
            'user' => $user,
            'branches' => Branch::where('is_active', true)->get(['id', 'name']),
            'roles' => Role::with('permissions:name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $employee = $user->employee;

        $request->validate([
            // Información Personal y Laboral
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($employee?->id)],
            'hire_date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'position' => 'required|string|max:255',
            'curp' => ['nullable', 'string', 'max:18', Rule::unique('employees')->ignore($employee?->id)],
            'rfc' => ['nullable', 'string', 'max:13', Rule::unique('employees')->ignore($employee?->id)],
            'nss' => ['nullable', 'string', 'max:11', Rule::unique('employees')->ignore($employee?->id)],
            'base_salary' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',

            // Contacto de Emergencia
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:50',

            // Acceso al sistema
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'], // La contraseña es opcional al editar
            'role_id' => ['required', 'exists:roles,id'],
            'facial_image' => ['nullable', 'image', 'max:1024'],
            'delete_photo' => 'boolean',
        ]);

        // Actualizar el registro del usuario
        $user->update([
            'name' => $request->input('first_name') . ' ' . $request->input('last_name'),
            'email' => $request->input('email'),
        ]);

        // actualizar o eliminar la foto
        if ($request->hasFile('facial_image')) {
            $user->updateProfilePhoto($request->file('facial_image'));
        } elseif ($request->input('delete_photo')) {
            $user->deleteProfilePhoto();
        }

        // ✨ Sincronizar el rol (quita los anteriores y asigna el nuevo)
        $role = Role::findById($request->input('role_id'));
        $user->syncRoles($role);

        // Si se proporcionó una nueva contraseña, la actualizamos
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
            $user->save();
        }

        // Actualizar el registro del empleado asociado
        if ($employee) {
            $employee->update($request->except(['email', 'password', 'role_id', 'facial_image']));
        }

        return redirect()->route('users.index');
    }

    public function show(User $user)
    {
        // Cargamos todas las relaciones necesarias para la vista de detalle
        $user->load(['employee.branch']);

        // --- LÓGICA PARA EL HISTORIAL DE VACACIONES ---
        $vacationHistory = collect();
        if ($user->employee) {
            $vacationType = IncidentType::where('code', 'VAC')->first();

            if ($vacationType) {
                $vacationsTaken = Incident::where('employee_id', $user->employee->id)
                    ->where('incident_type_id', $vacationType->id)
                    ->orderBy('start_date', 'asc')
                    ->get();

                // Mapear las vacaciones tomadas
                foreach ($vacationsTaken as $vacation) {
                    $days = Carbon::parse($vacation->start_date)->diffInDays(Carbon::parse($vacation->end_date)) + 1;
                    $vacationHistory->push([
                        'date' => $vacation->start_date,
                        'type' => 'Tomadas',
                        'days' => -$days, // Negativo porque se descuentan
                    ]);
                }
            }

            // Simular saldo inicial y días otorgados para el ejemplo
            $vacationHistory->push(['date' => $user->employee->hire_date, 'type' => 'Iniciales', 'days' => 6]);
            $vacationHistory->push(['date' => Carbon::parse($user->employee->hire_date)->addYear(), 'type' => 'Otorgadas', 'days' => 12]);

            // Ordenar por fecha y calcular el saldo corriente
            $balance = 0;
            $vacationHistory = $vacationHistory->sortBy('date')->map(function ($item) use (&$balance) {
                $balance += $item['days'];
                $item['balance'] = $balance;
                return $item;
            })->values();
        }

        // Transformamos los datos del usuario para la vista
        $userData = $user->toArray();
        // Añadimos el historial de vacaciones a los datos
        $userData['vacation_history'] = $vacationHistory;

        return Inertia::render('User/Show', [
            'user' => $userData,
        ]);
    }
}
