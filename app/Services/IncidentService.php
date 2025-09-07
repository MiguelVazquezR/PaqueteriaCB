<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class IncidentService
{
    protected HolidayService $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
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
}
