<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\VacationLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacationService
{
    /**
     * Sets or updates the initial vacation balance for an employee.
     * This creates a special 'initial' ledger entry.
     *
     * @param Employee $employee
     * @param float $initialBalance
     * @return void
     */
    public function setInitialBalance(Employee $employee, float $initialBalance): void
    {
        DB::transaction(function () use ($employee, $initialBalance) {
            // Use updateOrCreate to handle both new and existing initial balances
            VacationLedger::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'type'        => 'initial',
                ],
                [
                    'date'        => $employee->hire_date,
                    'days'        => $initialBalance,
                    'balance'     => 0, // Will be recalculated
                    'description' => 'Ajuste de saldo inicial',
                ]
            );

            $this->recalculateLedgerForEmployee($employee);
        });
    }

    /**
     * Creates a new vacation ledger transaction and recalculates the balance.
     * Handles special logic for 'taken' transactions, creating an Incident as well.
     *
     * @param Employee $employee
     * @param array $data Validated data from StoreVacationTransactionRequest
     * @return void
     */
    public function createTransaction(Employee $employee, array $data): void
    {
        DB::transaction(function () use ($employee, $data) {
            $ledgerData = [
                'type'        => $data['type'],
                'description' => $data['description'] ?? null,
            ];

            if ($data['type'] === 'taken') {
                // Se verifica si se proveyó una fecha única (desde el registro de incidencias)
                // o un rango de fechas (desde una solicitud de vacaciones).
                if (isset($data['date'])) {
                    $startDate = Carbon::parse($data['date']);
                    $endDate = Carbon::parse($data['date']);
                } else {
                    $startDate = Carbon::parse($data['start_date']);
                    $endDate = Carbon::parse($data['end_date']);
                }

                $ledgerData['date'] = $startDate;
                $ledgerData['days'] = - ($startDate->diffInDays($endDate) + 1); // Días en negativo

                // Se añade un parámetro opcional 'create_incident' para controlar este comportamiento.
                // Si no se especifica, por defecto crea la incidencia para mantener la compatibilidad.
                if ($data['create_incident'] ?? true) {
                    $this->createVacationIncident($employee, $startDate, $endDate, $data['description'] ?? null);
                }
            } else {
                $ledgerData['date'] = Carbon::now();
                $ledgerData['days'] = $data['days'];
            }

            VacationLedger::create(
                ['employee_id' => $employee->id, 'balance' => 0] + $ledgerData
            );

            $this->recalculateLedgerForEmployee($employee);
        });
    }

    /**
     * Creates an 'approved' vacation incident.
     *
     * @param Employee $employee
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string|null $notes
     * @return void
     */
    private function createVacationIncident(Employee $employee, Carbon $startDate, Carbon $endDate, ?string $notes): void
    {
        $vacationType = IncidentType::where('code', 'VAC')->first();
        if ($vacationType) {
            Incident::create([
                'employee_id'      => $employee->id,
                'incident_type_id' => $vacationType->id,
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'status'           => 'approved',
                'notes'            => $notes,
            ]);
        }
    }

    /**
     * Removes a 'taken' vacation transaction for a specific date and recalculates.
     *
     * @param Employee $employee
     * @param Carbon $date
     * @return void
     */
    public function removeTransactionByDate(Employee $employee, Carbon $date): void
    {
        DB::transaction(function () use ($employee, $date) {
            $deleted = VacationLedger::where('employee_id', $employee->id)
                ->where('type', 'taken')
                ->whereDate('date', $date)
                ->delete();

            // Only recalculate if a record was actually deleted
            if ($deleted) {
                $this->recalculateLedgerForEmployee($employee);
            }
        });
    }

    /**
     * Recalculates the entire vacation ledger for a given employee.
     * It's the source of truth for the employee's vacation balance.
     *
     * @param Employee $employee
     * @return void
     */
    public function recalculateLedgerForEmployee(Employee $employee): void
    {
        $balance = 0;
        $ledgers = VacationLedger::where('employee_id', $employee->id)
            ->orderBy('date')
            ->orderBy('id') // Secondary sort to maintain order on the same day
            ->get();

        foreach ($ledgers as $ledger) {
            $balance += $ledger->days;
            $ledger->balance = $balance;
            $ledger->save();
        }

        $employee->update(['vacation_balance' => $balance]);
    }
}
