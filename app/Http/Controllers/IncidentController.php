<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollPeriod::query();

        // Aplicar búsqueda (por número de semana)
        $query->when($request->input('search'), function ($q, $search) {
            $q->where('week_number', 'like', "%{$search}%");
        });

        // Aplicar ordenamiento
        $query->orderBy($request->input('sort_by', 'week_number'), $request->input('sort_direction', 'desc'));

        // Paginar
        $perPage = $request->input('per_page', 20);
        $periods = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Incident/Index', [
            'periods' => $periods,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction', 'per_page']),
        ]);
    }

    public function show(PayrollPeriod $period, Request $request)
    {
        // Esto soluciona el desfase de un día.
        $startDate = Carbon::parse($period->start_date)->startOfDay();
        $endDate = Carbon::parse($period->end_date)->startOfDay();

        // Obtener IDs del período anterior y siguiente para la navegación
        $previousPeriod = PayrollPeriod::where('end_date', '<', $startDate)->orderBy('end_date', 'desc')->first();
        $nextPeriod = PayrollPeriod::where('start_date', '>', $endDate)->orderBy('start_date', 'asc')->first();

        $employeesQuery = Employee::query()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereHas('attendances', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]);
                })
                    ->orWhereHas('incidents', function ($q) use ($startDate, $endDate) {
                        $q->whereDate('start_date', '<=', $endDate)
                            ->whereDate('end_date', '>=', $startDate);
                    });
            })
            ->with(['branch', 'user']);

        // Aplicar filtros de la UI (ahora funcionarán correctamente)
        $employeesQuery->when($request->input('branch_id'), function ($q, $branchId) {
            $q->where('branch_id', $branchId);
        });
        $employeesQuery->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        });

        $employees = $employeesQuery->get();

        // Usamos endOfDay() aquí para asegurar que el último día del rango sea incluido completamente.
        $dateRange = CarbonPeriod::create($startDate, $endDate->copy()->endOfDay());

        // Transformar los datos para cada empleado
        $employeesData = $employees->map(function ($employee) use ($dateRange, $period) {
            // Cargar las relaciones necesarias solo para este empleado y este periodo
            $employee->load([
                'attendances' => fn($q) => $q->whereBetween('created_at', [$dateRange->getStartDate(), $dateRange->getEndDate()->endOfDay()]),
                'incidents' => fn($q) => $q->whereDate('start_date', '<=', $dateRange->getEndDate())->whereDate('end_date', '>=', $dateRange->getStartDate()),
                // Cargar el horario activo del empleado
                'schedules.details'
            ]);

            $dailyData = [];
            foreach ($dateRange as $date) {
                $dayKey = $date->format('Y-m-d');
                $attendancesToday = $employee->attendances->where('created_at', '>=', $dayKey)->where('created_at', '<', $date->copy()->addDay()->format('Y-m-d'));
                $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));

                $entry = $attendancesToday->where('type', 'entry')->first();
                $exit = $attendancesToday->where('type', 'exit')->last();

                $dailyData[] = [
                    'date_formatted' => $date->isoFormat("dddd, DD [de] MMMM"), // Formato completo
                    'date' => $dayKey,
                    'entry_time' => $entry ? Carbon::parse($entry->created_at)->format('h:i a') : null,
                    'exit_time' => $exit ? Carbon::parse($exit->created_at)->format('h:i a') : null,
                    'break_time' => '0 h 30 min', // Lógica de cálculo de descanso iría aquí
                    'extra_time' => '0 h 0 min',
                    'total_hours' => '7 h 30 min', // Lógica de cálculo de horas totales iría aquí
                    'incident' => $incidentToday ? $incidentToday->incidentType->name : null, // Asumiendo relación incidentType
                    'entry_time' => $entry ? Carbon::parse($entry->created_at)->format('h:i a') : null,
                    'exit_time' => $exit ? Carbon::parse($exit->created_at)->format('h:i a') : null,
                    'late_minutes' => $entry?->late_minutes,
                    'late_ignored' => $entry?->late_ignored,
                    'entry_id' => $entry?->id, // Necesitamos el ID para la acción
                ];
            }

            $payrollRecord = Payroll::where('employee_id', $employee->id)
                // Asumiendo que se puede vincular por fechas si no hay ID de periodo
                ->where('start_date', $period->start_date)
                ->first();

            return [
                'id' => $employee->id,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'avatar_url' => $employee->user->profile_photo_url ?? null,
                'branch_name' => $employee->branch->name,
                'daily_data' => $dailyData,
                'comments' => $payrollRecord?->comments,
            ];
        });

        $periodData = $period->toArray();
        $periodData['start_date_formatted_short'] = Carbon::parse($period->start_date)->isoFormat('DD MMM');
        $periodData['end_date_formatted_full'] = Carbon::parse($period->end_date)->isoFormat('DD MMM YYYY');

        return Inertia::render('Incident/Show', [
            'period' => $periodData,
            'employeesData' => $employeesData,
            'branches' => Branch::all(['id', 'name']),
            'filters' => $request->only(['branch_id', 'search']),
            'navigation' => [
                'previous_period_id' => $previousPeriod?->id,
                'next_period_id' => $nextPeriod?->id,
            ],
            'incidentTypes' => IncidentType::all(['id', 'name']),
        ]);
    }

    public function storeDayIncident(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'incident_type_id' => 'required|exists:incident_types,id',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);

        // 1. Borrar incidencias o asistencias existentes para ese día para evitar duplicados
        Incident::where('employee_id', $validated['employee_id'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->delete();

        Attendance::where('employee_id', $validated['employee_id'])
            ->whereDate('created_at', $date)
            ->delete();

        // 2. Crear la nueva incidencia
        Incident::create([
            'employee_id' => $validated['employee_id'],
            'incident_type_id' => $validated['incident_type_id'],
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'approved', // O 'pending' si requiere aprobación
        ]);

        return back()->with('success', 'Incidencia registrada.');
    }

    public function removeDayIncident(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);
        $date = Carbon::parse($validated['date']);

        Incident::where('employee_id', $validated['employee_id'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->delete();

        return back()->with('success', 'Incidencia eliminada.');
    }

    public function updateComment(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_id' => 'required|exists:payroll_periods,id',
            'comments' => 'nullable|string',
        ]);

        $period = PayrollPeriod::find($validated['period_id']);

        // Busca o crea un registro de nómina para este empleado y período
        $payroll = Payroll::firstOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'start_date' => $period->start_date, // Usamos fechas para vincular
            ],
            [
                'end_date' => $period->end_date,
                // Puedes pre-rellenar otros campos si es necesario
                'gross_pay' => 0,
                'deductions' => 0,
                'net_pay' => 0,
            ]
        );

        // Actualiza el comentario
        $payroll->comments = $validated['comments'];
        $payroll->save();

        return back()->with('success', 'Comentario guardado.');
    }

    public function toggleLateStatus(Request $request)
    {
        $validated = $request->validate([
            'entry_id' => 'required|exists:attendances,id',
        ]);

        $attendance = Attendance::find($validated['entry_id']);

        if ($attendance && $attendance->type === 'entry') {
            $attendance->update(['late_ignored' => !$attendance->late_ignored]);
        }

        return back()->with('success', 'Estatus de retardo actualizado.');
    }
}
