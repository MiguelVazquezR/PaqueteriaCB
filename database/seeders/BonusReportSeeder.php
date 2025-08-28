<?php

namespace Database\Seeders;

use App\Models\BonusReport;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BonusReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::where('is_active', true)->get();
        $unjustifiedAbsenceTypeId = 1; // Asegúrate que este sea el ID correcto para 'Falta injustificada'

        // Generar reportes para los últimos 12 meses
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            foreach ($employees as $employee) {
                // Calcular total de minutos de retardo en el mes
                $totalLateMinutes = $employee->attendances()
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('late_minutes');

                // Calcular total de faltas injustificadas
                $totalUnjustifiedAbsences = $employee->incidents()
                    ->where('incident_type_id', $unjustifiedAbsenceTypeId)
                    ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->count();

                // Aplicar reglas de negocio
                $punctualityBonus = $totalLateMinutes <= 15;
                $attendanceBonus = $totalUnjustifiedAbsences === 0;

                // Crear el registro del reporte de bono
                BonusReport::create([
                    'employee_id' => $employee->id,
                    'period_date' => $startOfMonth->toDateString(),
                    'total_late_minutes' => $totalLateMinutes,
                    'total_unjustified_absences' => $totalUnjustifiedAbsences,
                    'punctuality_bonus_earned' => $punctualityBonus,
                    'attendance_bonus_earned' => $attendanceBonus,
                ]);
            }
        }
    }
}
