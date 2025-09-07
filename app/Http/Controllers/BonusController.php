<?php

namespace App\Http\Controllers;

use App\Http\Resources\BonusReportResource;
use App\Models\BonusReport;
use App\Services\BonusService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class BonusController extends Controller implements HasMiddleware
{
    public function __construct(protected BonusService $bonusService)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_bonos', only: ['index', 'show', 'print']),
            new Middleware('can:finalizar_bonos', only: ['finalize', 'recalculate']),
        ];
    }

    public function index()
    {
        $reports = BonusReport::orderBy('period', 'desc')->paginate(12);

        return Inertia::render('Bonus/Index', [
            'periods' => BonusReportResource::collection($reports),
        ]);
    }

    public function show(string $period)
    {
        $report = $this->bonusService->getReportForPeriod($period);
        $employeeBonuses = $this->bonusService->getTransformedReportDetails($report);

        return Inertia::render('Bonus/Show', [
            'report' => new BonusReportResource($report),
            'employeeBonuses' => $employeeBonuses,
        ]);
    }

    public function print(string $period)
    {
        $report = $this->bonusService->getReportForPeriod($period);
        $branches = $this->bonusService->getPrintableReportData($report);

        return Inertia::render('Bonus/Print', [
            'report' => new BonusReportResource($report),
            'branches' => $branches,
        ]);
    }

    public function finalize(string $period)
    {
        $report = $this->bonusService->getReportForPeriod($period);

        if (!$this->bonusService->finalizeReport($report)) {
            return back()->with('error', 'Este reporte ya ha sido finalizado.');
        }

        return redirect()->route('bonuses.show', $period)
            ->with('success', 'El reporte de bonos ha sido finalizado y cerrado.');
    }

    public function recalculate(string $period)
    {
        $report = $this->bonusService->getReportForPeriod($period);

        if (!$this->bonusService->recalculateReport($report)) {
            return back()->with('error', 'No se puede recalcular un reporte finalizado.');
        }

        return redirect()->route('bonuses.show', $period)
            ->with('success', 'El reporte ha sido recalculado con los datos más recientes.');
    }
}