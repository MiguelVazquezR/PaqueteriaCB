<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\VacationLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\VacationService;

class VacationController extends Controller
{
    public function __construct(private VacationService $vacationService) {}

    public function updateInitialBalance(Request $request, Employee $employee)
    {
        $validated = $request->validate(['initial_balance' => 'required|numeric|min:0']);

        DB::transaction(function () use ($employee, $validated) {
            // Busca o crea el registro de saldo inicial
            VacationLedger::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'type' => 'initial',
                ],
                [
                    'date' => $employee->hire_date,
                    'days' => $validated['initial_balance'],
                    'balance' => 0, // Se recalculará después
                    'description' => 'Ajuste de saldo inicial',
                ]
            );

            // Recalcular todo el historial para este empleado
            $this->vacationService->recalculateLedgerForEmployee($employee);
        });

        return back()->with('success', 'Saldo inicial actualizado.');
    }

    public function storeTransaction(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'type' => 'required|in:earned,taken,adjustment',
            'days' => ['required_unless:type,taken', 'nullable', 'numeric'],
            'start_date' => ['required_if:type,taken', 'nullable', 'date'],
            'end_date' => ['required_if:type,taken', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($employee, $validated, $request) {
            $days = 0;
            $date = now();

            if ($validated['type'] === 'taken') {
                $startDate = Carbon::parse($validated['start_date']);
                $endDate = Carbon::parse($validated['end_date']);
                $days = - ($startDate->diffInDays($endDate) + 1);
                $date = $startDate;

                $vacationType = IncidentType::where('code', 'VAC')->first();
                if ($vacationType) {
                    Incident::create([
                        'employee_id' => $employee->id,
                        'incident_type_id' => $vacationType->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'approved',
                        'notes' => $validated['description'],
                    ]);
                }
            } else {
                $days = $validated['days'];
            }

            VacationLedger::create([
                'employee_id' => $employee->id,
                'type' => $validated['type'],
                'date' => $date,
                'days' => $days,
                'balance' => 0,
                'description' => $validated['description'],
            ]);

            $this->vacationService->recalculateLedgerForEmployee($employee);
        });

        return back()->with('success', 'Movimiento de vacaciones registrado.');
    }
}
