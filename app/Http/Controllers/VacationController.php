<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacationTransactionRequest;
use App\Models\Employee;
use App\Models\VacationLedger; // Importar el modelo
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

    /**
     * Elimina un registro del historial de vacaciones y recalcula el saldo.
     *
     * @param VacationLedger $vacationLedger
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyTransaction(VacationLedger $vacationLedger)
    {
        // Se utiliza la autorización directamente en el controlador.
        // El middleware 'can:vacaciones_usuarios' ya protege la ruta.
        $this->vacationService->deleteTransaction($vacationLedger);

        return back()->with('success', 'Registro de vacaciones eliminado correctamente.');
    }
}