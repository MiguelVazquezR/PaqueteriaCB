<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacationTransactionRequest;
use App\Models\Employee;
use App\Models\VacationLedger;
use App\Models\VacationPeriod; // Importar
use App\Services\VacationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacationController extends Controller
{
    public function __construct(private VacationService $vacationService)
    {
    }

    public function updateInitialBalance(Request $request, Employee $employee)
    {
        $validated = $request->validate(['initial_balance' => 'required|numeric|min:0']);
        
        $this->vacationService->setInitialBalance($employee, $validated['initial_balance']);

        return back()->with('success', 'Saldo inicial actualizado.');
    }

    public function storeTransaction(StoreVacationTransactionRequest $request, Employee $employee)
    {
        $this->vacationService->createTransaction($employee, $request->validated());

        return back()->with('success', 'Movimiento de vacaciones registrado.');
    }

    public function destroyTransaction(VacationLedger $vacationLedger)
    {
        $this->vacationService->deleteTransaction($vacationLedger);

        return back()->with('success', 'Registro de vacaciones eliminado correctamente.');
    }

    /**
     * Marca la prima vacacional de un periodo como pagada.
     */
    public function markPremiumAsPaid(VacationPeriod $vacationPeriod)
    {
        $this->vacationService->markPremiumAsPaid($vacationPeriod);
        return back()->with('success', "Prima vacacional del periodo Año {$vacationPeriod->year_number} marcada como pagada.");
    }

    // --- NUEVOS MÉTODOS DE GESTIÓN DE PERIODOS ---

    public function storePeriod(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'year_number'     => [
                'required', 
                'integer', 
                'min:1', 
                Rule::unique('vacation_periods')->where(function ($query) use ($employee) {
                    return $query->where('employee_id', $employee->id);
                })
            ],
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after:period_start',
            'days_entitled'   => 'required|numeric|min:0',
            'days_accrued'    => 'required|numeric|min:0',
            'days_taken'      => 'required|numeric|min:0',
            'is_premium_paid' => 'boolean',
        ]);

        $this->vacationService->createPeriod($employee, $validated);

        return back()->with('success', 'Periodo vacacional creado correctamente.');
    }

    public function updatePeriod(Request $request, VacationPeriod $vacationPeriod)
    {
        $validated = $request->validate([
            'year_number'     => [
                'required', 
                'integer', 
                'min:1', 
                Rule::unique('vacation_periods')->where(function ($query) use ($vacationPeriod) {
                    return $query->where('employee_id', $vacationPeriod->employee_id);
                })->ignore($vacationPeriod->id)
            ],
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after:period_start',
            'days_entitled'   => 'required|numeric|min:0',
            'days_accrued'    => 'required|numeric|min:0',
            'days_taken'      => 'required|numeric|min:0',
            'is_premium_paid' => 'boolean',
        ]);

        $this->vacationService->updatePeriod($vacationPeriod, $validated);

        return back()->with('success', 'Periodo vacacional actualizado.');
    }

    public function destroyPeriod(VacationPeriod $vacationPeriod)
    {
        $this->vacationService->deletePeriod($vacationPeriod);
        return back()->with('success', 'Periodo vacacional eliminado.');
    }
}