<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacationTransactionRequest; // Asumiendo que creas este FormRequest
use App\Models\Employee;
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
}
