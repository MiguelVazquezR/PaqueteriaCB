<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\VacationLedger;
use App\Services\HolidayService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Services\IncidentService;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Services\VacationService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class IncidentController extends Controller implements HasMiddleware
{
    public function __construct(
        protected IncidentService $incidentService,
        protected VacationService $vacationService,
        protected HolidayService $holidayService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_incidencias', only: ['index', 'show']),
            new Middleware('can:gestionar_incidencias', except: ['index', 'show']),
        ];
    }

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
        $startDate = Carbon::parse($period->start_date)->startOfDay();
        $endDate = Carbon::parse($period->end_date)->endOfDay(); // Usar endOfDay para un rango inclusivo

        $previousPeriod = PayrollPeriod::where('end_date', '<', $startDate)->orderBy('end_date', 'desc')->first();
        $nextPeriod = PayrollPeriod::where('start_date', '>', $endDate)->orderBy('start_date', 'asc')->first();

        // --- CAMBIO CLAVE: --- Se construye una consulta explícita para obtener todos los empleados
        // activos durante el período, independientemente de si tienen registros de asistencia o no.
        $employeesQuery = Employee::query()
            ->where('is_active', true)
            ->where('hire_date', '<=', $endDate) // Contratados antes o durante el período
            ->where(function ($query) use ($startDate) {
                // Que no hayan sido despedidos antes de que inicie el período
                $query->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $startDate);
            });

        // Se cargan las relaciones y se aplican los filtros sobre esta nueva consulta base.
        $employees = $employeesQuery
            ->with([
                'branch',
                'user',
                'schedules.details',
                // Se optimiza la carga de relaciones para que solo traiga datos del período actual
                'incidents' => fn($q) => $q->whereBetween('start_date', [$startDate, $endDate])->with('incidentType'),
                'attendances' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]),
                'payrolls' => fn($q) => $q->where('start_date', $startDate)
            ])
            ->when($request->input('branch_id'), fn($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn($subQ) => $subQ->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('employee_number', 'like', "%{$search}%"));
            })
            ->get();

        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // La transformación sigue funcionando igual gracias al IncidentService
        $employeesData = $employees->map(function ($employee) use ($dateRange) {
            return [
                'id' => $employee->id,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'avatar_url' => $employee->user->profile_photo_url ?? null,
                'branch_name' => $employee->branch->name,
                'comments' => $employee->payrolls->first()?->comments,
                'daily_data' => $this->incidentService->getDailyDataForEmployee($employee, $dateRange),
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
            'navigation' => ['previous_period_id' => $previousPeriod?->id, 'next_period_id' => $nextPeriod?->id],
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
        $employee = Employee::find($validated['employee_id']);
        $vacationType = IncidentType::where('code', 'VAC')->first();

        // 1. Borrar incidencias o asistencias existentes para ese día para evitar duplicados
        Incident::where('employee_id', $validated['employee_id'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->delete();

        Attendance::where('employee_id', $validated['employee_id'])
            ->whereDate('created_at', $date)
            ->delete();

        // 2. Crear la nueva incidencia
        $incident = Incident::create([
            'employee_id' => $validated['employee_id'],
            'incident_type_id' => $validated['incident_type_id'],
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'approved', // O 'pending' si requiere aprobación
        ]);

        if ($vacationType && $incident->incident_type_id == $vacationType->id) {
            VacationLedger::create([
                'employee_id' => $employee->id,
                'type' => 'taken',
                'date' => $date,
                'days' => -1, // Un día tomado
                'balance' => 0, // Se recalculará
                'description' => 'Vacaciones registradas desde incidencias.',
            ]);
            $this->vacationService->recalculateLedgerForEmployee($employee);
        }

        return back();
    }

    public function removeDayIncident(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);
        $employee = Employee::find($validated['employee_id']);
        $vacationType = IncidentType::where('code', 'VAC')->first();

        // --- CAMBIO CLAVE: --- Primero buscamos la incidencia con ->first() en lugar de ->delete().
        $incident = Incident::where('employee_id', $validated['employee_id'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first(); // Esto devuelve el objeto del modelo o null.

        if ($incident) {
            // Ahora $incident es un objeto del modelo y podemos acceder a sus propiedades.
            $isVacation = $vacationType && $incident->incident_type_id == $vacationType->id;

            // Eliminamos la incidencia.
            $incident->delete();

            // Si era una vacación, actualizamos el historial.
            if ($isVacation) {
                VacationLedger::where('employee_id', $employee->id)
                    ->where('type', 'taken')
                    ->whereDate('date', $date)
                    ->delete();

                // Asegúrate de que tu controlador tiene acceso al VacationService.
                $this->vacationService->recalculateLedgerForEmployee($employee);
            }
        }

        return back();
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

        return back();
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

    public function prePayroll(PayrollPeriod $period, HolidayService $holidayService)
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        $employees = Employee::query()
            ->where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->with([
                'branch',
                'incidents' => fn($q) => $q->whereBetween('start_date', [$startDate, $endDate])->with('incidentType'),
                'attendances' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]),
                'schedules.details',
                'payrolls' => fn($q) => $q->where('start_date', $startDate)
            ])
            ->get()
            ->groupBy('branch.name');

        $reportData = $employees->map(function ($branchEmployees) use ($period, $dateRange, $holidayService) {
            return $branchEmployees->map(function ($employee) use ($period, $dateRange, $holidayService) {

                $unpaidIncidentTypeIds = [1, 4]; // Basado en tu imagen: Falta Injustificada, Permiso sin goce
                $totalDaysInPeriod = $dateRange->count();
                $unpaidDays = 0;
                $incidentSummary = [];

                if ($period->status === 'closed') {
                    // --- LÓGICA PARA PERIODOS CERRADOS: Leer solo de la BD ---
                    foreach ($employee->incidents as $incident) {
                        if (in_array($incident->incident_type_id, $unpaidIncidentTypeIds)) {
                            $unpaidDays += Carbon::parse($incident->start_date)->diffInDays(Carbon::parse($incident->end_date)) + 1;
                        }
                        $incidentSummary[] = $incident->incidentType->name . ' (' . Carbon::parse($incident->start_date)->isoFormat('dddd, DD MMM') . ')';
                    }
                } else {
                    // --- LÓGICA PARA PERIODOS ABIERTOS: Cálculo dinámico ---
                    $holidaysInPeriod = $holidayService->getHolidaysForPeriod($employee, $dateRange);
                    $workDaysOfWeek = $employee->schedules->flatMap->details->pluck('day_of_week')->toArray();

                    foreach ($dateRange as $date) {
                        $dateString = $date->format('Y-m-d');
                        $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));

                        if ($incidentToday) {
                            $incidentSummary[] = $incidentToday->incidentType->name . ' (' . $date->isoFormat('dddd, DD MMM') . ')';
                            if (in_array($incidentToday->incident_type_id, $unpaidIncidentTypeIds)) $unpaidDays++;
                            continue;
                        }

                        $isRestDay = !in_array($date->dayOfWeekIso, $workDaysOfWeek);
                        $isHoliday = isset($holidaysInPeriod[$dateString]);
                        $hasAttendance = $employee->attendances->contains(fn($att) => Carbon::parse($att->created_at)->isSameDay($date));

                        $isAutoAbsence = !$isRestDay && !$isHoliday && !$hasAttendance && $date->isPast() && !$date->isToday();

                        if ($isAutoAbsence) {
                            $incidentSummary[] = 'Falta Injustificada (auto-detectada) (' . $date->isoFormat('dddd, DD MMM') . ')';
                            $unpaidDays++;
                        } elseif ($isHoliday && $hasAttendance) {
                            $incidentSummary[] = 'Día Festivo Laborado (' . $date->isoFormat('dddd, DD MMM') . ')';
                        } elseif ($isHoliday && !$hasAttendance) {
                            $incidentSummary[] = 'Día Festivo (' . $date->isoFormat('dddd, DD MMM') . ')';
                        } elseif ($isRestDay && !$hasAttendance) {
                            $incidentSummary[] = 'Descanso (' . $date->isoFormat('dddd, DD MMM') . ')';
                        }
                    }
                }

                if ($employee->payrolls->first()?->comments) {
                    $incidentSummary[] = 'Comentarios: ' . $employee->payrolls->first()->comments;
                }

                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'days_to_pay' => $totalDaysInPeriod - $unpaidDays,
                    'incidents' => $incidentSummary,
                ];
            });
        });

        return Inertia::render('Incident/PrePayroll', [
            'period' => $period,
            'reportData' => $reportData,
        ]);
    }

    public function printAttendances(PayrollPeriod $period)
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // --- CAMBIO: --- Se optimiza la consulta para ser más eficiente.
        $employees = Employee::query()
            ->where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->with(['branch', 'user', 'schedules.details', 'incidents.incidentType', 'attendances'])
            ->get();

        // El IncidentService ya contiene toda la lógica de cálculo dinámico.
        // Simplemente lo llamamos para cada empleado.
        $employeesData = $employees->map(function ($employee) use ($dateRange) {
            return [
                'id' => $employee->id,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'branch_name' => $employee->branch->name,
                'daily_data' => $this->incidentService->getDailyDataForEmployee($employee, $dateRange),
            ];
        });

        return Inertia::render('Incident/PrintAttendances', [
            'period' => $period,
            'employeesData' => $employeesData,
        ]);
    }

    public function updateBreak(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'start_id' => 'required|exists:attendances,id',
            'end_id' => 'required|exists:attendances,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $date = Carbon::parse($validated['date']);

        $breakStart = Attendance::find($validated['start_id']);
        $breakEnd = Attendance::find($validated['end_id']);

        if ($breakStart && $breakEnd) {
            $breakStart->update(['created_at' => $date->copy()->setTimeFromTimeString($validated['start_time'])]);
            $breakEnd->update(['created_at' => $date->copy()->setTimeFromTimeString($validated['end_time'])]);
        }

        return back()->with('success', 'Descanso actualizado.');
    }

    public function destroyBreak(Request $request)
    {
        $validated = $request->validate([
            'start_id' => 'required|exists:attendances,id',
            'end_id' => 'required|exists:attendances,id',
        ]);

        Attendance::destroy([$validated['start_id'], $validated['end_id']]);

        return back()->with('success', 'Descanso eliminado.');
    }
}
