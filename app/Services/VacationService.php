<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\VacationLedger;

class VacationService
{
    /**
     * Recalculates the entire vacation ledger for a given employee.
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

        // Update the final balance on the employee record
        $employee->vacation_balance = $balance;
        $employee->save();
    }
}
