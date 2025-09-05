<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\BonusReport;
use Carbon\Carbon;

class GenerateBonusReport extends Command
{
    protected $signature = 'bonuses:generate {--month=}';
    protected $description = 'Calculates and generates the bonus report for a specific month in a draft state.';

    const PUNCTUALITY_BONUS_AMOUNT = 500.00;
    const PUNCTUALITY_LATE_MINUTES_THRESHOLD = 15;
    const ATTENDANCE_BONUS_AMOUNT = 800.00;
    const UNJUSTIFIED_ABSENCE_TYPE_ID = 1;

    public function handle()
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month')) : Carbon::now()->subMonth();
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $this->info("Generando reporte de bonos para el periodo: {$periodStart->toDateString()}...");

        $existingReport = BonusReport::where('period', $periodStart->toDateString())->first();
        if ($existingReport && $existingReport->status === 'finalized') {
            $this->warn("El reporte para {$periodStart->toDateString()} ya fue finalizado. Omitiendo.");
            return;
        }

        $report = BonusReport::firstOrCreate(
            ['period' => $periodStart->toDateString()],
            ['status' => 'draft', 'generated_at' => now()]
        );

        $report->details()->delete();

        $employees = Employee::where('is_active', true)->get();
        $bar = $this->output->createProgressBar(count($employees));
        $this->info("Calculando bonos para {$employees->count()} empleados...");
        $bar->start();

        foreach ($employees as $employee) {
            $punctualityData = $this->calculatePunctualityBonusFor($employee, $periodStart, $periodEnd);
            $attendanceData = $this->calculateAttendanceBonusFor($employee, $periodStart, $periodEnd);

            // Se guarda un registro para CADA bono y CADA empleado.
            // Esto crea un "snapshot" completo de los datos en el momento de la generación.

            // Guardar detalle de Puntualidad
            $report->details()->create([
                'employee_id' => $employee->id,
                'bonus_name' => 'Bono de Puntualidad',
                'calculated_amount' => $punctualityData['earned'] ? self::PUNCTUALITY_BONUS_AMOUNT : 0,
                'calculation_details' => ['late_minutes' => $punctualityData['late_minutes']],
            ]);

            // Guardar detalle de Asistencia
            $report->details()->create([
                'employee_id' => $employee->id,
                'bonus_name' => 'Bono de Asistencia',
                'calculated_amount' => $attendanceData['earned'] ? self::ATTENDANCE_BONUS_AMOUNT : 0,
                'calculation_details' => ['unjustified_absences' => $attendanceData['unjustified_absences']],
            ]);
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->info("\nReporte de bonos para {$periodStart->toDateString()} generado en modo Borrador.");
        return self::SUCCESS;
    }

    private function calculatePunctualityBonusFor(Employee $employee, Carbon $start, Carbon $end): array
    {
        $totalLateMinutes = $employee->attendances()
            ->whereBetween('created_at', [$start, $end])
            ->sum('late_minutes');

        return [
            'earned' => $totalLateMinutes <= self::PUNCTUALITY_LATE_MINUTES_THRESHOLD,
            'late_minutes' => $totalLateMinutes,
        ];
    }

    private function calculateAttendanceBonusFor(Employee $employee, Carbon $start, Carbon $end): array
    {
        $unjustifiedAbsences = $employee->incidents()
            ->where('incident_type_id', self::UNJUSTIFIED_ABSENCE_TYPE_ID)
            ->whereBetween('start_date', [$start, $end])
            ->count();

        return [
            'earned' => $unjustifiedAbsences === 0,
            'unjustified_absences' => $unjustifiedAbsences,
        ];
    }
}