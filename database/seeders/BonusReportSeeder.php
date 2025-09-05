<?php

namespace Database\Seeders;

use App\Models\BonusReport;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BonusReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::where('is_active', true)->get();

        // Generar reportes de ejemplo para los últimos 3 meses
        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();

            // El mes más reciente estará en borrador, los demás finalizados.
            $status = ($i === 0) ? 'draft' : 'finalized';

            // Crear el reporte principal para el mes
            $report = BonusReport::create([
                'period' => $startOfMonth->toDateString(),
                'status' => $status,
                'generated_at' => now(),
                'finalized_at' => ($status === 'finalized') ? now() : null,
                'finalized_by_user_id' => ($status === 'finalized') ? 1 : null, // Asumimos que el admin (ID 1) lo finalizó
            ]);

            // Crear detalles de ejemplo para cada empleado en este reporte
            foreach ($employees as $employee) {
                // Simular un bono de puntualidad ganado
                $report->details()->create([
                    'employee_id' => $employee->id,
                    'bonus_name' => 'Bono de Puntualidad',
                    'calculated_amount' => 500.00,
                    'calculation_details' => ['message' => 'Total de retardos: 10 min.'],
                ]);

                // Simular un bono de asistencia ganado por la mitad de los empleados
                if ($employee->id % 2 == 0) {
                    $report->details()->create([
                        'employee_id' => $employee->id,
                        'bonus_name' => 'Bono de Asistencia',
                        'calculated_amount' => 800.00,
                        'calculation_details' => ['message' => 'Asistencia perfecta.'],
                    ]);
                }
            }
        }
    }
}
