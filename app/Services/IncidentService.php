<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePeriodNote;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class IncidentService
{
    public function __construct(protected VacationService $vacationService, protected HolidayService $holidayService) {}

    public function updateOrCreateComment(array $data): void
    {
        EmployeePeriodNote::updateOrCreate(
            [
                'employee_id'       => $data['employee_id'],
                'payroll_period_id' => $data['period_id'],
            ],
            [
                'comments' => $data['comments'],
            ]
        );
    }

    public function getEmployeeDataForPeriod(PayrollPeriod $period, Request $request)
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        $employees = Employee::activeDuring($startDate, $endDate)
            ->with([
                'branch',
                'user',
                'schedules.details',
                'incidents' => fn($q) => $q->whereBetween('start_date', [$startDate, $endDate])->with('incidentType'),
                'attendances' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]),
                'periodNotes' => fn($q) => $q->where('payroll_period_id', $period->id),
            ])
            ->when($request->input('branch_id'), fn($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn($subQ) => $subQ->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            })
            ->get();

        return $employees->map(fn($employee) => [
            'id' => $employee->id,
            'name' => $employee->first_name . ' ' . $employee->last_name,
            'employee_number' => $employee->employee_number,
            'position' => $employee->position,
            'avatar_url' => $employee->user?->profile_photo_url,
            'branch_name' => $employee->branch->name,
            'comments' => $employee->periodNotes->first()?->comments,
            'daily_data' => $this->getDailyDataForEmployee($employee, CarbonPeriod::create($startDate, $endDate)),
        ]);
    }

    /**
     * Calcula los datos diarios de asistencia e incidencias para un empleado en un rango de fechas.
     */
    public function getDailyDataForEmployee(Employee $employee, CarbonPeriod $dateRange): array
    {
        $employee->load(['schedules.details', 'incidents.incidentType', 'attendances']);

        // --- Se obtienen todos los festivos del periodo de una sola vez.
        $holidaysInPeriod = $this->holidayService->getHolidaysForPeriod($employee, $dateRange);

        $dailyData = [];
        foreach ($dateRange as $date) {


            $dayKey = $date->format('Y-m-d');
            $dayOfWeek = $date->dayOfWeekIso; // 1 (Lunes) a 7 (Domingo)

            // Determinar si es un día laboral o de descanso según el horario del empleado
            $scheduleDetail = $employee->schedules->flatMap->details->firstWhere('day_of_week', $dayOfWeek);
            $isRestDay = is_null($scheduleDetail);

            // Verificar si hay datos para este día
            $holidayName = $holidaysInPeriod[$dayKey] ?? null;
            $attendancesToday = $employee->attendances->filter(fn($att) => Carbon::parse($att->created_at)->isSameDay($date));
            $incidentToday = $employee->incidents->first(fn($inc) => $date->between($inc->start_date, $inc->end_date));
            $entry = $attendancesToday->where('type', 'entry')->first();
            $exit = $attendancesToday->where('type', 'exit')->last();

            // --- CAMBIO CLAVE: --- Lógica para detectar faltas injustificadas automáticamente.
            $isUnjustifiedAbsence = false;
            if (
                !$isRestDay &&                         // 1. Era un día laboral
                !$holidayName &&                       // 2. No era festivo
                !$incidentToday &&                     // 3. No tiene otra incidencia registrada
                !$entry &&                             // 4. No tiene registro de entrada
                $date->isPast() && !$date->isToday()   // 5. El día ya pasó
            ) {
                $isUnjustifiedAbsence = true;
            }

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
                            'start_id' => $breakStarts[$i]->id,
                            'end_id' => $breakEnds[$i]->id,
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
                'is_unjustified_absence' => $isUnjustifiedAbsence,
                'is_rest_day' => $isRestDay,
                'holiday_name' => $holidayName,
                'late_minutes' => $entry?->late_minutes,
                'late_ignored' => $entry?->late_ignored,
                'entry_id' => $entry?->id,
                'breaks_summary' => $breaksSummary,
            ];
        }

        return $dailyData;
    }

    public function createDailyIncident(array $validatedData): void
    {
        $date = Carbon::parse($validatedData['date']);
        $employee = Employee::find($validatedData['employee_id']);
        $vacationType = IncidentType::where('code', 'VAC')->first();

        // 1. Limpiar registros existentes para ese día
        $this->clearRecordsForDay($employee->id, $date);

        // 2. Crear la nueva incidencia
        $incident = Incident::create([
            'employee_id' => $employee->id,
            'incident_type_id' => $validatedData['incident_type_id'],
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'approved',
        ]);

        // 3. Si es vacación, registrar en el ledger
        if ($vacationType && $incident->incident_type_id == $vacationType->id) {
            $this->vacationService->createTransaction($employee, [
                'type' => 'taken',
                'days' => -1,
                'date' => $date,
                'description' => 'Vacaciones registradas desde incidencias.',
            ]);
        }
    }

    public function removeDailyIncident(array $validatedData): void
    {
        $date = Carbon::parse($validatedData['date']);
        $employee = Employee::find($validatedData['employee_id']);

        $incident = Incident::where('employee_id', $employee->id)
            ->whereDate('start_date', $date)
            ->first();

        if ($incident) {
            $isVacation = $incident->incidentType->code === 'VAC';
            $incident->delete();

            if ($isVacation) {
                // Eliminar el registro y recalcular
                $this->vacationService->removeTransactionByDate($employee, $date);
            }
        }
    }

    public function updateDailyAttendance(array $validatedData): void
    {
        $employee = Employee::with('schedules.details')->find($validatedData['employee_id']);
        $date = Carbon::parse($validatedData['date']);

        // Actualizar Entrada
        $this->updateAttendanceRecord('entry', $employee, $date, $validatedData['entry_time'] ?? null);

        // Actualizar Salida
        $this->updateAttendanceRecord('exit', $employee, $date, $validatedData['exit_time'] ?? null);
    }

    private function updateAttendanceRecord(string $type, Employee $employee, Carbon $date, ?string $time): void
    {
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('type', $type)
            ->whereDate('created_at', $date)
            ->first();

        if ($time) {
            $newDateTime = $date->copy()->setTimeFromTimeString($time);
            $data = ['created_at' => $newDateTime];

            if ($type === 'entry') {
                $data['late_minutes'] = $this->calculateLateMinutes($employee, $newDateTime);
                $data['late_ignored'] = false; // Resetear al modificar
            }

            if ($attendance) {
                $attendance->update($data);
            } else {
                Attendance::create(array_merge(
                    ['employee_id' => $employee->id, 'type' => $type],
                    $data
                ));
            }
        } elseif ($attendance) {
            $attendance->delete();
        }
    }

    private function calculateLateMinutes(Employee $employee, Carbon $entryTime): ?int
    {
        $dayOfWeek = $entryTime->dayOfWeekIso;
        $scheduleDetail = $employee->schedules->flatMap->details->firstWhere('day_of_week', $dayOfWeek);

        if (!$scheduleDetail) return null;

        $scheduledEntryTime = $entryTime->copy()->setTimeFromTimeString($scheduleDetail->start_time);

        if ($entryTime->isAfter($scheduledEntryTime)) {
            return $scheduledEntryTime->diffInMinutes($entryTime);
        }

        return null;
    }

    private function clearRecordsForDay(int $employeeId, Carbon $date): void
    {
        Incident::where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->delete();

        Attendance::where('employee_id', $employeeId)
            ->whereDate('created_at', $date)
            ->delete();
    }

    public function toggleLateStatus(int $attendanceId): void
    {
        $attendance = Attendance::find($attendanceId);
        if ($attendance?->type === 'entry') {
            $attendance->update(['late_ignored' => !$attendance->late_ignored]);
        }
    }

    public function getPrePayrollData(PayrollPeriod $period)
    {
        // --- 1. CONFIGURACIÓN INICIAL ---
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // --- 2. CONSULTA DE EMPLEADOS Y SUS RELACIONES ---
        $employees = Employee::query()
            ->where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->with([
                'branch',
                'incidents' => fn($q) => $q->whereBetween('start_date', [$startDate, $endDate])->with('incidentType'),
                'attendances' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]),
                'schedules.details',
                'periodNotes' => fn($q) => $q->where('payroll_period_id', $period->id),
            ])
            ->get()
            ->groupBy('branch.name');

        // --- 3. PROCESAMIENTO DE DATOS Y CÁLCULO DE INCIDENCIAS ---
        return $employees->map(function ($branchEmployees) use ($period, $dateRange) {
            return $branchEmployees->map(function ($employee) use ($period, $dateRange) {

                $unpaidIncidentTypeIds = [1, 4]; // Falta Injustificada, Permiso sin goce
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
                    $holidaysInPeriod = $this->holidayService->getHolidaysForPeriod($employee, $dateRange);
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

                        // Una falta automática solo aplica a días pasados, no hoy ni en el futuro.
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

                if ($employee->periodNotes->first()?->comments) {
                    $incidentSummary[] = 'Comentarios: ' . $employee->periodNotes->first()->comments;
                }

                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->full_name,
                    'days_to_pay' => $totalDaysInPeriod - $unpaidDays,
                    'incidents' => $incidentSummary,
                ];
            });
        });
    }

    // Lógica movida desde `printAttendances`
    public function getAttendancePrintData(PayrollPeriod $period)
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        $employees = Employee::activeDuring($startDate, $endDate)
            ->with(['branch', 'user', 'schedules.details', 'incidents.incidentType', 'attendances'])
            ->get();

        return $employees->map(fn($employee) => [
            'id' => $employee->id,
            'name' => $employee->first_name . ' ' . $employee->last_name,
            'employee_number' => $employee->employee_number,
            'position' => $employee->position,
            'branch_name' => $employee->branch->name,
            'daily_data' => $this->getDailyDataForEmployee($employee, CarbonPeriod::create($startDate, $endDate)),
        ]);
    }

    public function updateBreak(array $validatedData): void
    {
        $date = Carbon::parse($validatedData['date']);
        $breakStart = Attendance::find($validatedData['start_id']);
        $breakEnd = Attendance::find($validatedData['end_id']);

        if ($breakStart && $breakEnd) {
            $breakStart->update(['created_at' => $date->copy()->setTimeFromTimeString($validatedData['start_time'])]);
            $breakEnd->update(['created_at' => $date->copy()->setTimeFromTimeString($validatedData['end_time'])]);
        }
    }

    public function destroyBreak(array $validatedData): void
    {
        Attendance::destroy([$validatedData['start_id'], $validatedData['end_id']]);
    }
}
