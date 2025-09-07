<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Branch;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class ScheduleController extends Controller implements HasMiddleware
{
    public function __construct(protected ScheduleService $scheduleService)
    {
    }

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
        $schedules = Schedule::with('branches:id,name')
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Setting/Schedule/Index', [
            'schedules' => $schedules,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Setting/Schedule/Create', [
            'branches' => Branch::select('id', 'name', 'business_hours')->get()
        ]);
    }

    public function store(StoreScheduleRequest $request)
    {
        $this->scheduleService->createSchedule($request->validated());

        return redirect()->route('settings.schedules.index')->with('success', 'Horario creado.');
    }

    public function edit(Schedule $schedule)
    {
        $schedule->load(['details', 'branches:id']);

        return Inertia::render('Setting/Schedule/Edit', [
            'schedule' => $schedule,
            'branches' => Branch::select('id', 'name', 'business_hours')->get(),
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $this->scheduleService->updateSchedule($schedule, $request->validated());

        return redirect()->route('settings.schedules.index')->with('success', 'Horario actualizado.');
    }

    public function destroy(Schedule $schedule)
    {
        $result = $this->scheduleService->deleteSchedule($schedule);

        $flashType = $result['success'] ? 'success' : 'error';

        return redirect()->route('settings.schedules.index')->with($flashType, $result['message']);
    }
}
