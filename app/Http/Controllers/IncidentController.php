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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
            $employee->load([
                'attendances' => fn($q) => $q->whereBetween('created_at', [$dateRange->getStartDate(), $dateRange->getEndDate()->endOfDay()])->orderBy('created_at'),
                'incidents' => fn($q) => $q->whereDate('start_date', '<=', $dateRange->getEndDate())->whereDate('end_date', '>=', $dateRange->getStartDate()),
                'schedules.details'
            ]);

            $dailyData = [];
            foreach ($dateRange as $date) {
                $dayKey = $date->format('Y-m-d');
                $attendancesToday = $employee->attendances->filter(fn($att) => Carbon::parse($att->created_at)->isSameDay($date));
                $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));

                $entry = $attendancesToday->where('type', 'entry')->first();
                $exit = $attendancesToday->where('type', 'exit')->last();

                // --- LÓGICA DE CÁLCULO DE TIEMPOS ---
                $totalWorkMinutes = 0;
                $totalBreakMinutes = 0;
                $extraMinutes = 0;
                $breaksSummary = [];

                if ($entry && $exit) {
                    $breakStarts = $attendancesToday->where('type', 'break_start')->values();
                    $breakEnds = $attendancesToday->where('type', 'break_end')->values();

                    // 1. Calcular tiempo total de descanso y preparar resumen
                    for ($i = 0; $i < $breakStarts->count(); $i++) {
                        if (isset($breakEnds[$i])) {
                            $start = Carbon::parse($breakStarts[$i]->created_at);
                            $end = Carbon::parse($breakEnds[$i]->created_at);
                            // Usamos abs() para asegurar que la duración siempre sea positiva.
                            $duration = abs($end->diffInMinutes($start));
                            $totalBreakMinutes += $duration;
                            $breaksSummary[] = [
                                'start' => $start->format('h:i a'),
                                'end' => $end->format('h:i a'),
                                'duration' => $duration,
                            ];
                        }
                    }

                    // 2. Calcular horas trabajadas netas
                    $grossWorkMinutes = abs(Carbon::parse($exit->created_at)->diffInMinutes(Carbon::parse($entry->created_at)));
                    $totalWorkMinutes = $grossWorkMinutes - $totalBreakMinutes;

                    // 3. Calcular horas extra comparando con el horario
                    $dayOfWeek = $date->dayOfWeekIso;
                    $scheduleDetail = $employee->schedules->flatMap->details->firstWhere('day_of_week', $dayOfWeek);
                    if ($scheduleDetail) {
                        $scheduledStart = Carbon::parse($scheduleDetail->start_time);
                        $scheduledEnd = Carbon::parse($scheduleDetail->end_time);
                        $scheduledWorkMinutes = abs($scheduledEnd->diffInMinutes($scheduledStart)) - ($scheduleDetail->meal_minutes ?? 0);

                        $difference = $totalWorkMinutes - $scheduledWorkMinutes;
                        $extraMinutes = max(0, $difference); // El tiempo extra no puede ser negativo
                    }
                }

                // Función para formatear minutos a "X h Y min"
                $formatMinutes = fn($mins) => floor($mins / 60) . ' h ' . ($mins % 60) . ' min';

                $dailyData[] = [
                    'date_formatted' => $date->isoFormat("dddd, DD [de] MMMM"),
                    'date' => $dayKey,
                    'entry_time' => $entry ? Carbon::parse($entry->created_at)->format('h:i a') : null,
                    'exit_time' => $exit ? Carbon::parse($exit->created_at)->format('h:i a') : null,
                    'entry_time_raw' => $entry ? Carbon::parse($entry->created_at)->format('H:i') : null,
                    'exit_time_raw' => $exit ? Carbon::parse($exit->created_at)->format('H:i') : null,
                    'break_time' => $formatMinutes($totalBreakMinutes),
                    'extra_time' => $formatMinutes($extraMinutes),
                    'total_hours' => $formatMinutes($totalWorkMinutes),
                    'incident' => $incidentToday?->incidentType->name,
                    'late_minutes' => $entry?->late_minutes,
                    'late_ignored' => $entry?->late_ignored,
                    'entry_id' => $entry?->id,
                    'breaks_summary' => $breaksSummary,
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

    public function updateAttendance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'entry_time' => 'nullable|date_format:H:i',
            'exit_time' => [
                'nullable',
                'date_format:H:i',
                Rule::when($request->filled('entry_time'), 'after_or_equal:entry_time')
            ],
        ]);

        $employee = Employee::with('schedules.details')->find($validated['employee_id']);
        $date = Carbon::parse($validated['date']);

        // --- Actualizar/Crear/Eliminar registro de ENTRADA ---
        $entryAttendance = Attendance::where('employee_id', $employee->id)
            ->where('type', 'entry')->whereDate('created_at', $date)->first();

        if ($validated['entry_time']) {
            $newEntryTime = $date->copy()->setTimeFromTimeString($validated['entry_time']);

            // 1. Calcular el retardo ANTES de actualizar
            $dayOfWeek = $date->dayOfWeekIso;
            $scheduleDetail = $employee->schedules->flatMap->details->firstWhere('day_of_week', $dayOfWeek);
            $lateMinutes = null;
            if ($scheduleDetail) {
                $scheduledEntryTime = $date->copy()->setTimeFromTimeString($scheduleDetail->start_time);
                if ($newEntryTime->isAfter($scheduledEntryTime)) {
                    $lateMinutes = $scheduledEntryTime->diffInMinutes($newEntryTime);
                }
            }

            // 2. Preparar todos los datos para una única operación
            $entryData = [
                'created_at' => $newEntryTime,
                'late_minutes' => $lateMinutes,
                'late_ignored' => false, // Siempre se resetea el estado 'ignorado' al modificar
            ];

            // 3. Ejecutar una sola operación de actualización o creación
            if ($entryAttendance) {
                $entryAttendance->update($entryData);
            } else {
                Attendance::create(array_merge(
                    ['employee_id' => $employee->id, 'type' => 'entry'],
                    $entryData
                ));
            }
        } elseif ($entryAttendance) {
            $entryAttendance->delete();
        }

        // --- Actualizar/Crear/Eliminar registro de SALIDA ---
        $exitAttendance = Attendance::where('employee_id', $employee->id)
            ->where('type', 'exit')->whereDate('created_at', $date)->first();

        if ($validated['exit_time']) {
            $newExitTime = $date->copy()->setTimeFromTimeString($validated['exit_time']);
            if ($exitAttendance) {
                $exitAttendance->update(['created_at' => $newExitTime]);
            } else {
                Attendance::create(['employee_id' => $employee->id, 'type' => 'exit', 'created_at' => $newExitTime]);
            }
        } elseif ($exitAttendance) {
            $exitAttendance->delete();
        }

        return back();
    }

    public function prePayroll(PayrollPeriod $period)
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        // Obtener todos los empleados que tuvieron actividad, con sus relaciones
        $employees = Employee::query()
            ->whereHas('attendances', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]);
            })
            ->orWhereHas('incidents', function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate);
            })
            ->with([
                'branch',
                'incidents' => fn($q) => $q->whereDate('start_date', '<=', $endDate)->whereDate('end_date', '>=', $startDate)->with('incidentType'),
                'payrolls' => fn($q) => $q->where('start_date', $startDate)
            ])
            ->get()
            ->groupBy('branch.name'); // Agrupar por nombre de sucursal

        $reportData = $employees->map(function ($branchEmployees) use ($startDate, $endDate) {
            return $branchEmployees->map(function ($employee) use ($startDate, $endDate) {

                $unpaidIncidentTypes = ['Permiso sin goce', 'Falta injustificada'];
                $daysToPay = 0;
                $incidentSummary = [];
                $dateRange = CarbonPeriod::create($startDate, $endDate);

                foreach ($dateRange as $date) {
                    if ($date->isWeekday()) { // Contar solo días laborables
                        $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));

                        if ($incidentToday && in_array($incidentToday->incidentType->name, $unpaidIncidentTypes)) {
                            // No se paga este día
                        } else {
                            $daysToPay++;
                        }

                        if ($incidentToday) {
                            $incidentSummary[] = $incidentToday->incidentType->name . ' (' . $date->isoFormat('dddd, DD [de] MMMM') . ')';
                        }
                    }
                }

                // Añadir comentarios si existen
                if ($employee->payrolls->first()?->comments) {
                    $incidentSummary[] = 'Comentarios: ' . $employee->payrolls->first()->comments;
                }

                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'days_to_pay' => $daysToPay,
                    'incidents' => $incidentSummary,
                ];
            });
        });

        return Inertia::render('Incident/PrePayroll', [
            'period' => $period,
            'reportData' => $reportData,
        ]);
    }
}
