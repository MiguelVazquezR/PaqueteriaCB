<?php

namespace App\Http\Controllers;

use App\Models\BonusReport;
use App\Models\Employee;
use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BonusController extends Controller
{
    public function index()
    {
        // Agrupar los reportes por mes para crear la lista de períodos
        $periods = BonusReport::selectRaw('DATE_FORMAT(period_date, "%Y-%m-01") as period, MIN(created_at) as created_at')
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->paginate(20);

        return Inertia::render('Bonus/Index', [
            'periods' => $periods,
        ]);
    }

    public function show($period) // El período vendrá como 'YYYY-MM'
    {
        $startOfMonth = Carbon::parse($period)->startOfMonth();
        $endOfMonth = Carbon::parse($period)->endOfMonth();

        // Obtener todos los empleados
        $employees = Employee::with('branch')->get();

        $reportData = $employees->map(function ($employee) use ($startOfMonth, $endOfMonth) {
            // Calcular total de minutos de retardo en el mes
            $totalLateMinutes = $employee->attendances()
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('late_minutes');

            // Calcular total de faltas injustificadas
            $unjustifiedAbsenceTypeId = 2; // Asumiendo que el ID de 'Falta injustificada' es 2
            $totalUnjustifiedAbsences = $employee->incidents()
                ->where('incident_type_id', $unjustifiedAbsenceTypeId)
                ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                ->count();

            // Aplicar reglas de negocio
            $punctualityBonus = $totalLateMinutes <= 15;
            $attendanceBonus = $totalUnjustifiedAbsences === 0;

            // Guardar o actualizar el reporte en la base de datos
            BonusReport::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'period_date' => $startOfMonth->toDateString(),
                ],
                [
                    'total_late_minutes' => $totalLateMinutes,
                    'total_unjustified_absences' => $totalUnjustifiedAbsences,
                    'punctuality_bonus_earned' => $punctualityBonus,
                    'attendance_bonus_earned' => $attendanceBonus,
                ]
            );

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'branch_name' => $employee->branch->name,
                'punctuality_bonus' => $punctualityBonus,
                'attendance_bonus' => $attendanceBonus,
                'late_minutes' => $totalLateMinutes,
                'unjustified_absences' => $totalUnjustifiedAbsences,
            ];
        })->groupBy('branch_name');


        return Inertia::render('Bonus/Show', [
            'period' => $startOfMonth,
            'reportData' => $reportData,
        ]);
    }
}
