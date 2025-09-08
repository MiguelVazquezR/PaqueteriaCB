<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\BonusReport;
use App\Models\Bonus;
use Carbon\Carbon;

class GenerateBonusReport extends Command
{
    protected $signature = 'bonuses:generate {--month=}';
    protected $description = 'Calculates and generates the bonus report for a specific month in a draft state.';

    public function handle()
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month')) : Carbon::now()->subMonth();
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $this->info("Generando reporte de bonos para el periodo: {$periodStart->toDateString()}...");

        $automaticBonuses = Bonus::where('type', 'automatic')->get();
        if ($automaticBonuses->isEmpty()) {
            $this->warn('No se encontraron bonos automáticos configurados en la base de datos. Finalizando.');
            return;
        }

        $report = BonusReport::firstOrCreate(
            ['period' => $periodStart->toDateString()],
            ['status' => 'draft', 'generated_at' => now()]
        );
        $report->details()->delete();

        $employees = Employee::where('is_active', true)->get();
        $bar = $this->output->createProgressBar(count($employees));
        $this->info("Calculando {$automaticBonuses->count()} bono(s) para {$employees->count()} empleados...");
        $bar->start();

        foreach ($employees as $employee) {
            foreach ($automaticBonuses as $bonus) {
                $earned = false;
                $details = [];
                $amount = 0;

                switch ($bonus->rules['type']) {
                    case 'punctuality':
                        $punctualityData = $this->calculatePunctualityBonusFor($employee, $periodStart, $periodEnd, $bonus->rules['threshold_minutes']);
                        $earned = $punctualityData['earned'];
                        $details = ['late_minutes' => $punctualityData['late_minutes']];
                        break;

                    case 'attendance':
                        $attendanceData = $this->calculateAttendanceBonusFor(
                            $employee,
                            $periodStart,
                            $periodEnd,
                            $bonus->rules['threshold_absences'],
                            $bonus->rules['unjustified_absence_type_id']
                        );
                        $earned = $attendanceData['earned'];
                        $details = ['unjustified_absences' => $attendanceData['unjustified_absences']];
                        break;
                }

                if ($earned) {
                    $amount = $bonus->amount;
                }

                $report->details()->create([
                    'employee_id' => $employee->id,
                    'bonus_id' => $bonus->id,
                    'calculated_amount' => $amount,
                    'calculation_details' => $details,
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nReporte de bonos para {$periodStart->toDateString()} generado en modo Borrador.");
        return self::SUCCESS;
    }

    private function calculatePunctualityBonusFor(Employee $employee, Carbon $start, Carbon $end, int $threshold): array
    {
        // --- Se añade la condición para ignorar los retardos marcados.
        $totalLateMinutes = $employee->attendances()
            ->whereBetween('created_at', [$start, $end])
            ->where('late_ignored', false)
            ->sum('late_minutes');

        return [
            'earned' => $totalLateMinutes <= $threshold,
            'late_minutes' => $totalLateMinutes,
        ];
    }

    private function calculateAttendanceBonusFor(Employee $employee, Carbon $start, Carbon $end, int $threshold, int $unjustifiedAbsenceTypeId): array
    {
        $unjustifiedAbsences = $employee->incidents()
            ->where('incident_type_id', $unjustifiedAbsenceTypeId)
            ->whereBetween('start_date', [$start, $end])
            ->count();

        return [
            'earned' => $unjustifiedAbsences <= $threshold,
            'unjustified_absences' => $unjustifiedAbsences,
        ];
    }
}