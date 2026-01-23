<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacationTransactionRequest;
use App\Models\Employee;
use App\Models\VacationLedger;
use App\Models\VacationPeriod; // Importar
use App\Services\VacationService;
use Illuminate\Http\Request;

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
        // Validar autorización si es necesario (middleware ya lo hace a nivel general, pero podrías añadir check extra)
        
        $this->vacationService->markPremiumAsPaid($vacationPeriod);

        return back()->with('success', "Prima vacacional del periodo Año {$vacationPeriod->year_number} marcada como pagada.");
    }
}