<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Incident;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Validar el rango de días, con 7 como valor por defecto
        $days = in_array($request->input('range'), [7, 30]) ? $request->input('range') : 7;

        // --- KPIs (se mantienen igual) ---
        $totalEmployees = Employee::where('is_active', true)->count();
        $attendedToday = Attendance::where('type', 'entry')->whereDate('created_at', today())->distinct('employee_id')->count();
        $attendancePercentage = $totalEmployees > 0 ? round(($attendedToday / $totalEmployees) * 100) : 0;
        $startOfMonth = today()->startOfMonth();
        $punctualEntries = Attendance::where('type', 'entry')->whereBetween('created_at', [$startOfMonth, today()->endOfDay()])->where(fn($q) => $q->whereNull('late_minutes')->orWhere('late_minutes', 0))->distinct('employee_id')->count();
        $punctualityPercentage = $totalEmployees > 0 ? round(($punctualEntries / $totalEmployees) * 100) : 0;

        // --- Gráfica: Asistencia por Sucursal (ahora usa el rango dinámico) ---
        $attendanceByBranch = Branch::withCount(['employees as attendance_count' => function ($query) use ($days) {
            $query->select(DB::raw('count(distinct(attendances.employee_id))'))
                ->join('attendances', 'employees.id', '=', 'attendances.employee_id')
                ->where('attendances.type', 'entry')
                ->where('attendances.created_at', '>=', now()->subDays($days));
        }])->get(['id', 'name', 'attendance_count']);

        // --- Gráfica: Tendencia de Ausentismo (ahora usa el rango dinámico y prepara los datos) ---
        $unjustifiedAbsenceTypeId = 1;
        $absenteeismData = Incident::where('incident_type_id', $unjustifiedAbsenceTypeId)
            ->where('start_date', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(start_date) as date'), DB::raw('count(*) as absences'))
            ->groupBy('date')->orderBy('date', 'asc')->get()->pluck('absences', 'date');
        
        $trendLabels = [];
        $trendData = [];
        $period = CarbonPeriod::create(now()->subDays($days - 1), now());
        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $trendLabels[] = $date->isoFormat('ddd DD'); // Formato 'Mié 27'
            $trendData[] = $absenteeismData[$dateString] ?? 0;
        }

        // --- Cumpleaños del Personal (se mantiene igual) ---
        $upcomingBirthdays = Employee::where('is_active', true)
            ->whereRaw('DAYOFYEAR(birth_date) BETWEEN ? AND ?', [now()->dayOfYear, now()->addDays(7)->dayOfYear])
            ->with('branch:id,name')->orderByRaw('DAYOFYEAR(birth_date)')->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_employees' => $totalEmployees,
                'attendance_today' => ['percentage' => $attendancePercentage, 'count' => $attendedToday, 'total' => $totalEmployees],
                'punctuality_month' => ['percentage' => $punctualityPercentage, 'count' => $punctualEntries, 'total' => $totalEmployees],
            ],
            'attendanceByBranch' => $attendanceByBranch,
            'absenteeismTrend' => ['labels' => $trendLabels, 'data' => $trendData],
            'upcomingBirthdays' => $upcomingBirthdays,
            'filters' => ['range' => (int)$days],
        ]);
    }
}
