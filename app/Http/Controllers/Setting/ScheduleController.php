<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
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
        $query = Schedule::query()
            ->with('branches:id,name')
            // Seleccionamos solo las columnas de schedules y usamos distinct para evitar duplicados
            // si un horario pertenece a múltiples sucursales que coinciden con la búsqueda.
            ->select('schedules.*')
            ->distinct();

        // Unimos las tablas para poder buscar por el nombre de la sucursal.
        $query->leftJoin('branch_schedule', 'schedules.id', '=', 'branch_schedule.schedule_id')
            ->leftJoin('branches', 'branch_schedule.branch_id', '=', 'branches.id');

        // Lógica de búsqueda actualizada para incluir el nombre de la sucursal.
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQuery) use ($search) {
                $subQuery->where('schedules.name', 'like', "%{$search}%")
                    ->orWhere('branches.name', 'like', "%{$search}%");
            });
        });

        $schedules = $query->orderBy('schedules.id')
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        return Inertia::render('Setting/Schedule/Index', [
            // Se utiliza un Resource para estandarizar la estructura de datos de paginación.
            'schedules' => ScheduleResource::collection($schedules),
            'filters' => $request->only(['search', 'per_page']),
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
