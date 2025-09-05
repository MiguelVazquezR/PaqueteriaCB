<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ScheduleController extends Controller
{
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

        return redirect()->route('settings.schedules.index');
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

        return redirect()->route('settings.schedules.index');
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();
        return back()->with('success', 'Horario eliminado.');
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
