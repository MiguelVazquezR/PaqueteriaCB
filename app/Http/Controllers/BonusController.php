<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\BonusReport;
use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class BonusController extends Controller
{
    public function index()
    {
        // Obtenemos los últimos 12 meses donde hubo reportes o se espera que haya.
        $periods = BonusReport::orderBy('period', 'desc')->paginate(12);

        // Transformamos los datos para añadir el estatus correcto.
        $periods->through(fn($report) => [
            'period' => $report->period,
            'status' => $report->status,
        ]);

        return Inertia::render('Bonus/Index', [
            'periods' => $periods,
        ]);
    }

    public function show(string $period)
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        // Eager load details y la relación con el empleado para eficiencia
        $report = BonusReport::where('period', $periodDate)
            ->with('details.employee')
            ->firstOrFail();

        // --- CAMBIO CLAVE: --- Se procesan los datos directamente desde los detalles del reporte.
        $employeeBonuses = $report->details
            ->groupBy('employee_id') // Agrupar por empleado
            ->map(function ($detailsForEmployee) {
                $employee = $detailsForEmployee->first()->employee;
                if (!$employee) return null; // Omitir si el empleado fue eliminado

                $punctualityDetail = $detailsForEmployee->firstWhere('bonus_name', 'Bono de Puntualidad');
                $attendanceDetail = $detailsForEmployee->firstWhere('bonus_name', 'Bono de Asistencia');

                return [
                    'id' => $employee->id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_number' => $employee->employee_number,
                    'punctuality_earned' => $punctualityDetail ? $punctualityDetail->calculated_amount > 0 : false,
                    'attendance_earned' => $attendanceDetail ? $attendanceDetail->calculated_amount > 0 : false,
                    'late_minutes' => $punctualityDetail->calculation_details['late_minutes'] ?? 0,
                    'unjustified_absences' => $attendanceDetail->calculation_details['unjustified_absences'] ?? 0,
                ];
            })
            ->filter() // Eliminar nulos si algún empleado fue borrado
            ->values(); // Resetear las llaves del array

        return Inertia::render('Bonus/Show', [
            'report' => $report,
            'employeeBonuses' => $employeeBonuses,
        ]);
    }

    public function print(string $period)
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        $report = BonusReport::where('period', $periodDate)
            ->with('details.employee.branch')
            ->firstOrFail();

        // --- CAMBIO CLAVE: --- La lógica ahora es idéntica a la del método 'show' para consistencia.
        $employeeData = $report->details
            ->groupBy('employee_id')
            ->map(function ($detailsForEmployee) {
                $employee = $detailsForEmployee->first()->employee;
                if (!$employee) return null;

                $punctualityDetail = $detailsForEmployee->firstWhere('bonus_name', 'Bono de Puntualidad');
                $attendanceDetail = $detailsForEmployee->firstWhere('bonus_name', 'Bono de Asistencia');

                return [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'branch_name' => $employee->branch->name,
                    'punctuality_earned' => $punctualityDetail ? $punctualityDetail->calculated_amount > 0 : false,
                    'attendance_earned' => $attendanceDetail ? $attendanceDetail->calculated_amount > 0 : false,
                    'total_late_minutes' => $punctualityDetail->calculation_details['late_minutes'] ?? 0,
                    'total_unjustified_absences' => $attendanceDetail->calculation_details['unjustified_absences'] ?? 0,
                ];
            })
            ->filter()
            ->values();

        $branches = $employeeData->groupBy('branch_name');

        return Inertia::render('Bonus/Print', [
            'report' => $report,
            'branches' => $branches,
        ]);
    }

    public function finalize(string $period)
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $report = BonusReport::where('period', $periodDate)->firstOrFail();

        if ($report->status !== 'draft') {
            return back()->with('error', 'Este reporte ya ha sido finalizado.');
        }

        $report->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('bonuses.show', $period)->with('success', 'El reporte de bonos ha sido finalizado y cerrado.');
    }

    public function recalculate(string $period)
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $report = BonusReport::where('period', $periodDate)->firstOrFail();

        if ($report->status === 'finalized') {
            return back()->with('error', 'No se puede recalcular un reporte que ya ha sido finalizado.');
        }

        // Llama al comando de Artisan para regenerar el reporte en borrador
        Artisan::call('bonuses:generate', ['--month' => $period]);

        return redirect()->route('bonuses.show', $period)->with('success', 'El reporte ha sido recalculado con los datos más recientes.');
    }
}
