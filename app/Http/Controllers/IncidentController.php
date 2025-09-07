<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyBreakRequest;
use App\Http\Requests\StoreDayIncidentRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Requests\UpdateBreakRequest;
use App\Http\Traits\HandlesQueryFiltering;
use App\Models\Branch;
use App\Models\IncidentType;
use App\Models\PayrollPeriod;
use App\Services\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class IncidentController extends Controller implements HasMiddleware
{
    use HandlesQueryFiltering;

    public function __construct(protected IncidentService $incidentService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_incidencias', only: ['index', 'show']),
            new Middleware('can:gestionar_incidencias', except: ['index', 'show']),
        ];
    }

    public function index(Request $request)
    {
        $query = $this->applyFilters(
            $request,
            PayrollPeriod::query(),
            searchableColumns: ['week_number'],
            defaultSort: 'week_number',
            sortableColumns: ['week_number', 'payment_date'] // Agregamos más columnas si es necesario
        )->orderBy('week_number', $request->input('sort_direction', 'desc'));

        $periods = $query->paginate($request->input('per_page', 20))->withQueryString();

        return Inertia::render('Incident/Index', [
            'periods' => $periods,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction', 'per_page']),
        ]);
    }

    public function show(PayrollPeriod $period, Request $request)
    {
        $employeesData = $this->incidentService->getEmployeeDataForPeriod($period, $request);

        // Se busca el periodo anterior basándose en la fecha de inicio.
        $previousPeriod = PayrollPeriod::where('start_date', '<', $period->start_date)
            ->orderBy('start_date', 'desc')
            ->first();

        // Se busca el periodo siguiente.
        $nextPeriod = PayrollPeriod::where('start_date', '>', $period->start_date)
            ->orderBy('start_date', 'asc')
            ->first();

        return Inertia::render('Incident/Show', [
            'period' => $period,
            'employeesData' => $employeesData,
            'branches' => Branch::all(['id', 'name']),
            'filters' => $request->only(['branch_id', 'search']),
            'incidentTypes' => IncidentType::all(['id', 'name']),
            'navigation' => [
                'previous_period_id' => $previousPeriod?->id,
                'next_period_id'     => $nextPeriod?->id,
            ],
        ]);
    }

    public function updateComment(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_id'   => 'required|exists:payroll_periods,id',
            'comments'    => 'nullable|string',
        ]);

        $this->incidentService->updateOrCreateComment($validated);

        return back()->with('success', 'Comentario actualizado.');
    }

    public function storeDayIncident(StoreDayIncidentRequest $request)
    {
        $this->incidentService->createDailyIncident($request->validated());
        return back()->with('success', 'Incidencia registrada.');
    }

    public function removeDayIncident(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);
        $this->incidentService->removeDailyIncident($validated);
        return back()->with('success', 'Incidencia eliminada.');
    }

    public function toggleLateStatus(Request $request)
    {
        $validated = $request->validate(['entry_id' => 'required|exists:attendances,id']);
        $this->incidentService->toggleLateStatus($validated['entry_id']);
        return back()->with('success', 'Estatus de retardo actualizado.');
    }

    public function updateAttendance(UpdateAttendanceRequest $request)
    {
        $this->incidentService->updateDailyAttendance($request->validated());
        return back()->with('success', 'Asistencia actualizada.');
    }

    public function prePayroll(PayrollPeriod $period)
    {
        $reportData = $this->incidentService->getPrePayrollData($period);

        return Inertia::render('Incident/PrePayroll', [
            'period' => $period,
            'reportData' => $reportData,
        ]);
    }

    public function printAttendances(PayrollPeriod $period)
    {
        $employeesData = $this->incidentService->getAttendancePrintData($period);

        return Inertia::render('Incident/PrintAttendances', [
            'period' => $period,
            'employeesData' => $employeesData,
        ]);
    }

    public function updateBreak(UpdateBreakRequest $request)
    {
        $this->incidentService->updateBreak($request->validated());
        return back()->with('success', 'Descanso actualizado.');
    }

    public function destroyBreak(DestroyBreakRequest $request)
    {
        $this->incidentService->destroyBreak($request->validated());
        return back()->with('success', 'Descanso eliminado.');
    }
}
