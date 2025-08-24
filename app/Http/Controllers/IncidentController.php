<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
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
        // Aseguramos que las fechas sean objetos Carbon para evitar errores con valores nulos o mal formados.
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        // Obtener empleados que tuvieron actividad en este periodo
        $employeesQuery = Employee::query()
            ->whereHas('attendances', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]);
            })
            ->orWhereHas('incidents', function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate);
            })
            ->with(['branch', 'user']);

        // Aplicar filtros de la UI
        $employeesQuery->when($request->input('branch_id'), function ($q, $branchId) {
            $q->where('branch_id', $branchId);
        });

        $employees = $employeesQuery->get();

        // Crear el rango de fechas para el periodo
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // Transformar los datos para cada empleado
        $employeesData = $employees->map(function ($employee) use ($dateRange) {
            // Cargar las relaciones necesarias solo para este empleado y este periodo
            $employee->load([
                'attendances' => fn($q) => $q->whereBetween('created_at', [$dateRange->getStartDate(), $dateRange->getEndDate()->endOfDay()]),
                'incidents' => fn($q) => $q->whereDate('start_date', '<=', $dateRange->getEndDate())->whereDate('end_date', '>=', $dateRange->getStartDate())
            ]);

            $dailyData = [];
            foreach ($dateRange as $date) {
                $dayKey = $date->format('Y-m-d');
                $attendancesToday = $employee->attendances->where('created_at', '>=', $dayKey)->where('created_at', '<', $date->copy()->addDay()->format('Y-m-d'));
                $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));

                $entry = $attendancesToday->where('type', 'entry')->first();
                $exit = $attendancesToday->where('type', 'exit')->last();

                $dailyData[] = [
                    'date' => $dayKey,
                    'entry_time' => $entry ? Carbon::parse($entry->created_at)->format('h:i a') : null,
                    'exit_time' => $exit ? Carbon::parse($exit->created_at)->format('h:i a') : null,
                    'break_time' => '0 h 30 min', // Lógica de cálculo de descanso iría aquí
                    'extra_time' => '0 h 0 min',
                    'total_hours' => '7 h 30 min', // Lógica de cálculo de horas totales iría aquí
                    'incident' => $incidentToday ? $incidentToday->incidentType->name : null, // Asumiendo relación incidentType
                ];
            }

            return [
                'id' => $employee->id,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'avatar_url' => $employee->user->profile_photo_url ?? null,
                'branch_name' => $employee->branch->name,
                'daily_data' => $dailyData,
                'comments' => 'Incapacidad pagada al 60% del día.', // Cargar de la tabla payrolls
            ];
        });

        return Inertia::render('Incident/Show', [
            'period' => $period,
            'employeesData' => $employeesData,
            'branches' => Branch::all(['id', 'name']),
            'filters' => $request->only(['branch_id', 'search']),
        ]);
    }
}
