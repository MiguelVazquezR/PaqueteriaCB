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
        // Cargar sucursales con sus horarios para la guía
        $branches = Branch::with(['schedules.details'])->get();
        return Inertia::render('Setting/Schedule/Create', [
            'branches' => $branches
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        DB::transaction(function () use ($validated) {
            $schedule = Schedule::create(['name' => $validated['name']]);
            $schedule->details()->createMany($validated['details']);
            if (!empty($validated['branch_ids'])) {
                $schedule->branches()->sync($validated['branch_ids']);
            }
        });

        return redirect()->route('settings.schedules.index')->with('success', 'Horario creado.');
    }

    public function edit(Schedule $schedule)
    {
        $schedule->load(['details', 'branches:id']);
        $branches = Branch::with(['schedules.details'])->get();

        return Inertia::render('Setting/Schedule/Edit', [
            'schedule' => $schedule,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validateSchedule($request, $schedule->id);

        DB::transaction(function () use ($validated, $schedule) {
            $schedule->update(['name' => $validated['name']]);
            $schedule->details()->delete();
            $schedule->details()->createMany($validated['details']);
            $schedule->branches()->sync($validated['branch_ids'] ?? []);
        });

        return redirect()->route('settings.schedules.index')->with('success', 'Horario actualizado.');
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
        ]);
    }
}
