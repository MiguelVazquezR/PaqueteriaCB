<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str; 

class ScheduleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_horarios', only: ['index']),
            new Middleware('can:crear_horarios', only: ['create', 'store']),
            new Middleware('can:editar_horarios', only: ['edit', 'update']),
            new Middleware('can:eliminar_horarios', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = Schedule::with('branches:id,name');

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        return Inertia::render('Setting/Schedule/Index', [
            'schedules' => $query->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        // incluyendo la columna JSON 'business_hours' para la guía.
        $branches = Branch::select('id', 'name', 'business_hours')->get();

        return Inertia::render('Setting/Schedule/Create', [
            'branches' => $branches
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        // Filtramos el array para quedarnos solo con los días activos.
        $activeDetails = array_filter($validated['details'], fn($detail) => $detail['is_active']);

        DB::transaction(function () use ($validated, $activeDetails) {
            $schedule = Schedule::create(['name' => $validated['name']]);
            // Usamos el array filtrado para crear los registros.
            if (!empty($activeDetails)) {
                $schedule->details()->createMany($activeDetails);
            }
            if (!empty($validated['branch_ids'])) {
                $schedule->branches()->sync($validated['branch_ids']);
            }
        });

        return redirect()->route('settings.schedules.index')->with('success', 'Horario creado.');
    }

    public function edit(Schedule $schedule)
    {
        $schedule->load(['details', 'branches:id']);

        $branches = Branch::select('id', 'name', 'business_hours')->get();

        return Inertia::render('Setting/Schedule/Edit', [
            'schedule' => $schedule,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validateSchedule($request, $schedule->id);

        $activeDetails = array_filter($validated['details'], fn($detail) => $detail['is_active']);

        DB::transaction(function () use ($validated, $activeDetails, $schedule) {
            $schedule->update(['name' => $validated['name']]);
            $schedule->details()->delete(); // Borramos los detalles viejos
            // Creamos solo los nuevos detalles activos
            if (!empty($activeDetails)) {
                $schedule->details()->createMany($activeDetails);
            }
            $schedule->branches()->sync($validated['branch_ids'] ?? []);
        });

        return redirect()->route('settings.schedules.index')->with('success', 'Horario actualizado.');
    }

    public function destroy(Schedule $schedule)
    {
        // 1. Contar cuántos empleados tienen este horario asignado.
        $employeeCount = $schedule->employees()->count();

        // 2. Si el conteo es mayor a cero, prevenir la eliminación.
        if ($employeeCount > 0) {
            // (Opcional pero recomendado) Obtener algunos nombres para que el mensaje sea más útil.
            $employeeNames = $schedule->employees()
                ->take(3) // Tomar hasta 3 nombres como ejemplo
                ->get()
                ->pluck('first_name')
                ->implode(', ');

            $message = "Este horario no puede ser eliminado porque está asignado a {$employeeCount} " . Str::plural('empleado', $employeeCount) . ".";

            if ($employeeNames) {
                $message .= " (Ej: {$employeeNames}). Por favor, reasigna a estos empleados a otro horario antes de intentarlo de nuevo.";
            }

            // Se devuelve un mensaje de error que será capturado por el Toast en AppLayout.vue
            return back()->with('error', $message);
        }

        // 3. Si no hay empleados, proceder con la eliminación.
        $schedule->delete();

        // Se usa redirect() en lugar de back() para asegurar que la lista se refresque correctamente.
        return redirect()->route('settings.schedules.index')->with('success', 'Horario eliminado correctamente.');
    }

    private function validateSchedule(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('schedules')->ignore($ignoreId)],
            'branch_ids' => 'present|array',
            'branch_ids.*' => 'exists:branches,id',
            'details' => 'required|array|size:7',
            'details.*.day_of_week' => 'required|integer|between:1,7',
            'details.*.is_active' => 'required|boolean',
            'details.*.start_time' => 'nullable|required_if:details.*.is_active,true|date_format:H:i',
            'details.*.end_time' => 'nullable|required_if:details.*.is_active,true|date_format:H:i|after:details.*.start_time',
            'details.*.meal_minutes' => 'nullable|required_if:details.*.is_active,true|integer|min:0',
        ], [
            'details.*.start_time.required_if' => 'Llenar campo',
            'details.*.end_time.required_if' => 'Llenar campo',
            'details.*.end_time.after' => 'La hora de finalización debe ser posterior a la hora de inicio.',
        ]);
    }
}
