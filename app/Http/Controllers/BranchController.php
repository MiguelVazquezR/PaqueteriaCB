<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        // Paginar y transformar los datos
        $branches = $query->paginate($perPage)->withQueryString()->through(fn($branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'schedule' => '09:00 am - 18:00 pm', // Placeholder, esto vendrá de la tabla de horarios
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
            'settings.timezone' => 'required|string',
            'schedule' => 'required|array',
            'schedule.*.is_active' => 'boolean',
            'schedule.*.start_time' => 'nullable|required_if:schedule.*.is_active,true|date_format:H:i',
            'schedule.*.end_time' => 'nullable|required_if:schedule.*.is_active,true|date_format:H:i|after:schedule.*.start_time',
        ]);

        // Usamos una transacción para asegurar que todo se cree correctamente
        DB::transaction(function () use ($validated) {
            // 1. Crear la sucursal
            $branch = Branch::create([
                'name' => $validated['name'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'settings' => [
                    'timezone' => $validated['settings']['timezone']
                ],
            ]);

            // 2. Crear el horario principal para la sucursal
            $schedule = Schedule::create([
                'name' => 'Horario General - ' . $branch->name,
            ]);

            // 3. Crear los detalles del horario (días y horas)
            $scheduleDetails = [];
            foreach ($validated['schedule'] as $dayOfWeek => $details) {
                if ($details['is_active']) {
                    $scheduleDetails[] = [
                        'schedule_id' => $schedule->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $details['start_time'],
                        'end_time' => $details['end_time'],
                    ];
                }
            }
            
            // Insertar todos los detalles en una sola consulta
            if (!empty($scheduleDetails)) {
                DB::table('schedule_details')->insert($scheduleDetails);
            }
        });

        return redirect()->route('branches.index');
    }

    public function edit(Branch $branch)
    {
        // Encontrar el primer horario asociado a la sucursal (asumiendo uno por sucursal)
        $scheduleModel = Schedule::where('name', 'like', '%- ' . $branch->name)->first();
        $scheduleDetails = $scheduleModel ? $scheduleModel->details->keyBy('day_of_week') : collect();

        // Reconstruir el objeto de horario para el formulario
        $scheduleForForm = [
            1 => ['day_name' => 'Lunes a viernes', 'is_active' => false, 'start_time' => null, 'end_time' => null],
            6 => ['day_name' => 'Sábado', 'is_active' => false, 'start_time' => null, 'end_time' => null],
            7 => ['day_name' => 'Domingo', 'is_active' => false, 'start_time' => null, 'end_time' => null],
        ];

        foreach ($scheduleForForm as $dayOfWeek => &$dayDetails) {
            if ($scheduleDetails->has($dayOfWeek)) {
                $detail = $scheduleDetails->get($dayOfWeek);
                $dayDetails['is_active'] = true;
                $dayDetails['start_time'] = $detail->start_time;
                $dayDetails['end_time'] = $detail->end_time;
            }
        }

        return Inertia::render('Branch/Edit', [
            'branch' => $branch,
            'schedule' => $scheduleForForm,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches')->ignore($branch->id)],
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'settings.timezone' => 'required|string',
            'schedule' => 'required|array',
            'schedule.*.is_active' => 'boolean',
            'schedule.*.start_time' => 'nullable|required_if:schedule.*.is_active,true|date_format:H:i:s,H:i',
            'schedule.*.end_time' => 'nullable|required_if:schedule.*.is_active,true|date_format:H:i:s,H:i|after:schedule.*.start_time',
        ]);

        DB::transaction(function () use ($validated, $branch) {
            // 1. Actualizar la sucursal
            $branch->update([
                'name' => $validated['name'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'settings' => ['timezone' => $validated['settings']['timezone']],
            ]);

            // 2. Encontrar y actualizar el horario
            $schedule = Schedule::firstOrCreate(
                ['name' => 'Horario General - ' . $branch->name],
                ['name' => 'Horario General - ' . $branch->name]
            );
            
            // Renombrar si el nombre de la sucursal cambió
            if ($branch->wasChanged('name')) {
                $schedule->name = 'Horario General - ' . $branch->name;
                $schedule->save();
            }

            // 3. Borrar los detalles antiguos y crear los nuevos
            $schedule->details()->delete();

            $scheduleDetails = [];
            foreach ($validated['schedule'] as $dayOfWeek => $details) {
                if ($details['is_active']) {
                    $scheduleDetails[] = [
                        'schedule_id' => $schedule->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $details['start_time'],
                        'end_time' => $details['end_time'],
                    ];
                }
            }

            if (!empty($scheduleDetails)) {
                DB::table('schedule_details')->insert($scheduleDetails);
            }
        });

        return redirect()->route('branches.index');
    }

    public function show(Branch $branch)
    {
        // Cargar la sucursal con el número de empleados asociados
        $branch->loadCount('employees');

        // Cargar el horario de la sucursal
        $scheduleModel = Schedule::where('name', 'like', '%- ' . $branch->name)->with('details')->first();

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
            'schedule' => $scheduleModel,
        ]);
    }
}
