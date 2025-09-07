<?php

namespace App\Services;

use App\Models\BonusReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BonusService
{
    public function getReportForPeriod(string $period): ?BonusReport
    {
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        return BonusReport::where('period', $periodDate)->firstOrFail();
    }

    /**
     * Transforms the report details into a structured collection for the 'show' view.
     */
    public function getTransformedReportDetails(BonusReport $report)
    {
        // Eager load necessary relationships for performance
        $report->load('details.employee', 'details.bonus');

        return $report->details
            ->groupBy('employee_id')
            ->map(function ($detailsForEmployee) {
                $employee = $detailsForEmployee->first()->employee;
                if (!$employee) return null;

                $punctualityDetail = $detailsForEmployee->firstWhere('bonus.name', 'Bono de Puntualidad');
                $attendanceDetail = $detailsForEmployee->firstWhere('bonus.name', 'Bono de Asistencia');

                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name, // Using accessor from Employee model
                    'employee_number' => $employee->employee_number,
                    'punctuality_earned' => $punctualityDetail?->calculated_amount > 0,
                    'attendance_earned' => $attendanceDetail?->calculated_amount > 0,
                    'late_minutes' => $punctualityDetail?->calculation_details['late_minutes'] ?? 0,
                    'unjustified_absences' => $attendanceDetail?->calculation_details['unjustified_absences'] ?? 0,
                ];
            })
            ->filter() // Remove null entries if an employee was not found
            ->values();
    }

    /**
     * Prepares the report data grouped by branch for printing.
     */
    public function getPrintableReportData(BonusReport $report)
    {
        $report->load('details.employee.branch', 'details.bonus');
        $employeeData = $this->getTransformedReportDetails($report); // Reuse the same transformation logic

        // We just need to add the branch name and group by it
        return $employeeData->map(function ($data) use ($report) {
            $employee = $report->details->firstWhere('employee_id', $data['id'])->employee;
            $data['branch_name'] = $employee->branch->name;
            return $data;
        })->groupBy('branch_name');
    }

    /**
     * Finalizes a bonus report.
     */
    public function finalizeReport(BonusReport $report): bool
    {
        if ($report->status !== 'draft') {
            return false;
        }
        $report->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by_user_id' => Auth::id(),
        ]);
        return true;
    }

    /**
     * Recalculates a draft bonus report by calling the Artisan command.
     */
    public function recalculateReport(BonusReport $report): bool
    {
        if ($report->status === 'finalized') {
            return false;
        }
        try {
            Artisan::call('bonuses:generate', ['--month' => $report->period->format('Y-m')]);
            return true;
        } catch (\Exception $e) {
            Log::error("Error recalculating bonuses for period {$report->period}: " . $e->getMessage());
            return false;
        }
    }
}