<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Services\HolidayService;
use Carbon\CarbonPeriod;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Cycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:cycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Closes the current payroll period and opens the next one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payroll period cycle...');

        // 1. Encontrar el período abierto actual
        $currentPeriod = PayrollPeriod::where('status', 'open')->first();

        if (!$currentPeriod) {
            $this->error('No open payroll period found. Exiting.');
            Log::warning('Payroll cycle command ran but found no open period.');
            return 1; // Terminar con error
        }

        // Se añade la lógica para consolidar los días festivos antes de cerrar.
        $this->info("Consolidating holiday incidents for the period...");
        $this->consolidateHolidaysAsIncidents($currentPeriod);
        $this->consolidateRestDaysAsIncidents($currentPeriod);
        $this->consolidateAbsencesAsIncidents($currentPeriod);

        // 2. Cerrar el período actual
        $currentPeriod->status = 'closed';
        $currentPeriod->save();
        $this->info("Closed period for week #{$currentPeriod->week_number} (ends {$currentPeriod->end_date->format('Y-m-d')}).");

        // 3. Calcular las fechas para el nuevo período
        $lastEndDate = Carbon::parse($currentPeriod->end_date);
        $newStartDate = $lastEndDate->copy()->addDay()->startOfDay();
        $newEndDate = $newStartDate->copy()->addDays(6)->endOfDay();
        $newPaymentDate = $newEndDate->copy()->addDay();

        // 4. Manejar el reinicio de la semana al cambiar de año
        $newWeekNumber = $newStartDate->weekOfYear;
        if ($currentPeriod->week_number > $newWeekNumber) {
            $this->info("New year detected. Week number reset to {$newWeekNumber}.");
        }

        // 5. Crear el nuevo período abierto
        $newPeriod = PayrollPeriod::create([
            'week_number' => $newWeekNumber,
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'payment_date' => $newPaymentDate,
            'status' => 'open',
        ]);

        $this->info("Successfully opened new period for week #{$newPeriod->week_number} (starts {$newPeriod->start_date->format('Y-m-d')}).");
        Log::info("Payroll period cycled successfully. New open period ID: {$newPeriod->id}");

        $this->info('Payroll period cycle finished.');
        return 0; // Terminar con éxito
    }

    /**
     * Consolida los días festivos como incidencias para los empleados que no trabajaron.
     */
    protected function consolidateHolidaysAsIncidents(PayrollPeriod $period): void
    {
        $holidayService = new HolidayService();
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // Buscar el tipo de incidencia "Día Festivo" para obtener su ID.
        // Se recomienda tener un seeder que garantice su existencia.
        $holidayIncidentType = IncidentType::where('name', 'Día Festivo')->first();

        if (!$holidayIncidentType) {
            $this->error('"Día Festivo" incident type not found. Cannot consolidate holidays.');
            Log::error('Payroll Cycle: Could not find IncidentType named "Día Festivo".');
            return;
        }

        // Obtener todos los empleados que estuvieron activos durante el periodo.
        $employees = Employee::where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->get();

        $this->info("Checking holiday status for {$employees->count()} active employees...");
        $bar = $this->output->createProgressBar(count($employees));
        $bar->start();

        foreach ($employees as $employee) {
            // Obtener los festivos que aplicaron a este empleado en el periodo.
            $holidaysForEmployee = $holidayService->getHolidaysForPeriod($employee, $dateRange);

            foreach ($holidaysForEmployee as $dateString => $holidayName) {
                // Verificar si el empleado tuvo registros de asistencia en el día festivo.
                $hasAttendance = $employee->attendances()->whereDate('created_at', $dateString)->exists();

                // Si NO tuvo asistencia, se crea la incidencia de "Día Festivo" para consolidar el descanso.
                if (!$hasAttendance) {
                    Incident::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'incident_type_id' => $holidayIncidentType->id,
                            'start_date' => $dateString,
                        ],
                        [
                            'end_date' => $dateString,
                            'status' => 'approved',
                            'notes' => $holidayName,
                        ]
                    );
                }
                // Si SÍ tuvo asistencia, no se hace nada. El sistema de nóminas interpretará
                // esto como un "Día Festivo Laborado" y aplicará el pago correspondiente.
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nHoliday incident consolidation complete.");
    }

    /**
     * Consolida los días de descanso programados como incidencias para los empleados que no trabajaron.
     */
    protected function consolidateRestDaysAsIncidents(PayrollPeriod $period): void
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        $restDayIncidentType = IncidentType::where('name', 'Descanso')->first();

        if (!$restDayIncidentType) {
            $this->error('"Descanso" incident type not found. Cannot consolidate rest days.');
            return;
        }

        $employees = Employee::where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->with(['schedules.details']) // Cargar los horarios
            ->get();

        $this->info("Checking rest day status for {$employees->count()} active employees...");

        foreach ($employees as $employee) {
            // Obtener los días de la semana que el empleado SÍ trabaja.
            $workDaysOfWeek = $employee->schedules->flatMap->details->pluck('day_of_week')->toArray();

            foreach ($dateRange as $date) {
                // Si el día de la semana NO está en su lista de días laborales, es un descanso.
                if (!in_array($date->dayOfWeekIso, $workDaysOfWeek)) {
                    $dateString = $date->format('Y-m-d');

                    $hasAttendance = $employee->attendances()->whereDate('created_at', $dateString)->exists();
                    $hasIncident = $employee->incidents()->whereDate('start_date', '<=', $dateString)->whereDate('end_date', '>=', $dateString)->exists();

                    // Si es su día de descanso, no trabajó y no hay otra incidencia registrada, se consolida.
                    if (!$hasAttendance && !$hasIncident) {
                        Incident::firstOrCreate([
                            'employee_id' => $employee->id,
                            'incident_type_id' => $restDayIncidentType->id,
                            'start_date' => $dateString,
                        ], [
                            'end_date' => $dateString,
                            'status' => 'approved',
                            'notes' => 'Descanso semanal programado',
                        ]);
                    }
                }
            }
        }
        $this->info("Rest day incident consolidation complete.");
    }

    /**
     * --- MÉTODO NUEVO: ---
     * Consolida las faltas injustificadas detectadas automáticamente.
     */
    protected function consolidateAbsencesAsIncidents(PayrollPeriod $period): void
    {
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);
        $dateRange = CarbonPeriod::create($startDate, $endDate);

        // Se buscan los dos tipos de incidencia que se van a registrar.
        $absenceIncidentType = IncidentType::where('code', 'F_INJUST')->first();
        $notHiredIncidentType = IncidentType::where('code', 'NO_EMPLEADO')->first();

        if (!$absenceIncidentType) {
            $this->error('"Falta Injustificada" (F_INJUST) incident type not found.');
            return;
        }
        if (!$notHiredIncidentType) {
            $this->error('"No laboraba en empresa aún" (NO_EMPLEADO) incident type not found.');
            return;
        }

        $employees = Employee::where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->with(['schedules.details'])
            ->get();

        $this->info("Checking for auto-detected absences for {$employees->count()} employees...");

        foreach ($employees as $employee) {
            $workDaysOfWeek = $employee->schedules->flatMap->details->pluck('day_of_week')->toArray();
            $holidaysInPeriod = (new HolidayService())->getHolidaysForPeriod($employee, $dateRange);
            $hireDate = Carbon::parse($employee->hire_date);

            foreach ($dateRange as $date) {
                if ($date->isFuture()) continue; // No revisar el futuro

                $isWorkDay = in_array($date->dayOfWeekIso, $workDaysOfWeek);
                $dateString = $date->format('Y-m-d');
                $isHoliday = isset($holidaysInPeriod[$dateString]);

                if ($isWorkDay && !$isHoliday) {
                    $hasAttendance = $employee->attendances()->whereDate('created_at', $dateString)->exists();
                    $hasIncident = $employee->incidents()->whereDate('start_date', '<=', $dateString)->whereDate('end_date', '>=', $dateString)->exists();

                    if (!$hasAttendance && !$hasIncident) {
                        // --- MODIFICADO: --- Se decide qué tipo de incidencia registrar.
                        $notYetHired = $date->isBefore($hireDate);

                        if ($notYetHired) {
                            // Si la fecha es ANTERIOR a su contratación, es "No laboraba".
                            Incident::firstOrCreate([
                                'employee_id' => $employee->id,
                                'start_date' => $dateString,
                            ], [
                                'incident_type_id' => $notHiredIncidentType->id,
                                'end_date' => $dateString,
                                'status' => 'approved',
                                'notes' => 'Día no laborado; previo a fecha de contratación.',
                            ]);
                        } else {
                            // Si la fecha es POSTERIOR o igual a su contratación, es una falta.
                            Incident::firstOrCreate([
                                'employee_id' => $employee->id,
                                'start_date' => $dateString,
                            ], [
                                'incident_type_id' => $absenceIncidentType->id,
                                'end_date' => $dateString,
                                'status' => 'approved',
                                'notes' => 'Falta injustificada detectada automáticamente al cierre de nómina.',
                            ]);
                        }
                    }
                }
            }
        }
        $this->info("Auto-detected absence consolidation complete.");
    }
}
