<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\VacationLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationService
{
    /**
     * Accrues weekly proportional vacation days for all active employees.
     * This method is intended to be called by a scheduled task.
     *
     * @return int The number of employees processed.
     */
    public function accrueWeeklyVacationsForAllEmployees(): int
    {
        // Obtenemos solo los empleados que están marcados como activos
        $activeEmployees = Employee::where('is_active', true)->get();
        $processedCount = 0;
        $weeklyAccrual = 0;

        foreach ($activeEmployees as $employee) {
            DB::transaction(function () use ($employee, &$processedCount, &$weeklyAccrual) {
                // 1. Calcular años de servicio
                $yearsOfService = $employee->hire_date->diffInYears(now());

                // 2. Obtener días anuales según la ley mexicana
                $annualVacationDays = $this->getAnnualVacationDays($yearsOfService);

                // 3. Calcular la proporción semanal
                // Se divide el total anual entre 52.1775 (promedio de semanas en un año)
                $weeklyAccrual = $annualVacationDays / 52.1775;

                // 4. Crear el registro en el historial (ledger)
                $newLedgerEntry = VacationLedger::create([
                    'employee_id' => $employee->id,
                    'date'        => now()->toDateString(),
                    'type'        => 'earned', // Nuevo tipo para devengos automáticos
                    'days'        => $weeklyAccrual,
                    'balance'     => 0, // Se actualizará después
                    'description' => "Días proporcionales por semana (años laborando " . number_format($yearsOfService, 2) . ", {$annualVacationDays} días anuales)",
                ]);

                // 5. Actualizar el saldo total del empleado de forma eficiente
                $employee->increment('vacation_balance', $weeklyAccrual);

                // 6. Actualizar el saldo en el nuevo registro del ledger
                $newLedgerEntry->balance = $employee->vacation_balance;
                $newLedgerEntry->save();

                $processedCount++;
            });
            Log::info("Accrued {$weeklyAccrual} vacation days for employee #{$employee->employee_number}");
        }

        return $processedCount;
    }

    /**
     * Calculates the proportional vacation days earned since the employee's last anniversary.
     * This is intended for a one-time setup of initial balances.
     *
     * @param Employee $employee
     * @return float
     */
    public function calculateProportionalInitialBalance(Employee $employee): float
    {
        $today = Carbon::today();
        $hireDate = $employee->hire_date;

        if ($hireDate->isAfter($today)) {
            return 0; // Contratado en el futuro, no ha ganado nada.
        }

        // --- LÓGICA CORREGIDA Y SIMPLIFICADA ---

        // 1. Calcular los años de servicio COMPLETOS que el empleado tiene a día de hoy.
        // ej. Si fue contratado el 2023-12-15 y hoy es 2025-10-06, ha completado 1 año.
        $completedYearsOfService = $hireDate->diffInYears($today);

        // 2. Determinar la fecha del último aniversario sumando esos años a la fecha de contratación.
        // ej. 2023-12-15 + 1 año = 2024-12-15. Este fue su último aniversario.
        $lastAnniversary = $hireDate->copy()->addYears($completedYearsOfService);

        // 3. Obtener los días anuales que le corresponden para el AÑO DE SERVICIO ACTUAL.
        // Se basa en los años ya completados (1 año completo -> le tocan los días de su 2º año).
        $annualVacationDays = $this->getAnnualVacationDays($completedYearsOfService);

        // 4. Calcular los días transcurridos desde el último aniversario hasta hoy.
        $daysSinceLastAnniversary = $lastAnniversary->diffInDays($today);
        
        // Si el aniversario es hoy, no ha ganado días proporcionales para el siguiente periodo.
        if ($daysSinceLastAnniversary <= 0) {
            return 0;
        }

        // 5. Calcular y devolver los días proporcionales ganados.
        $proportionalDays = ($annualVacationDays / 365) * $daysSinceLastAnniversary;

        return round($proportionalDays, 4);
    }

    /**
     * Calculates the number of vacation days per year based on years of service,
     * according to Mexican Labor Law ("Vacaciones Dignas" reform).
     *
     * @param int $yearsOfService
     * @return int
     */
    private function getAnnualVacationDays(int $yearsOfService): int
    {
        $yearsOfService = $yearsOfService + 1; // Se cuenta el año en curso

        if ($yearsOfService <= 0) return 0;
        if ($yearsOfService == 1) return 12;
        if ($yearsOfService == 2) return 14;
        if ($yearsOfService == 3) return 16;
        if ($yearsOfService == 4) return 18;
        if ($yearsOfService == 5) return 20;
        if ($yearsOfService >= 6 && $yearsOfService <= 10) return 22;
        if ($yearsOfService >= 11 && $yearsOfService <= 15) return 24;
        if ($yearsOfService >= 16 && $yearsOfService <= 20) return 26;
        if ($yearsOfService >= 21 && $yearsOfService <= 25) return 28;
        if ($yearsOfService >= 26 && $yearsOfService <= 30) return 30;
        if ($yearsOfService >= 31 && $yearsOfService <= 35) return 32;

        // Para más de 35 años, la ley no especifica, pero se mantiene la última progresión.
        return 32;
    }

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
                if (isset($data['date'])) {
                    $startDate = Carbon::parse($data['date']);
                    $endDate = Carbon::parse($data['date']);
                } else {
                    $startDate = Carbon::parse($data['start_date']);
                    $endDate = Carbon::parse($data['end_date']);
                }

                $ledgerData['date'] = $startDate;
                $ledgerData['days'] = - ($startDate->diffInDays($endDate) + 1); // Días en negativo

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

    /**
     * Deletes a specific vacation ledger transaction and recalculates the balance.
     *
     * @param VacationLedger $ledgerEntry
     * @return void
     */
    public function deleteTransaction(VacationLedger $ledgerEntry): void
    {
        // Obtenemos el empleado ANTES de eliminar el registro
        $employee = $ledgerEntry->employee;

        DB::transaction(function () use ($ledgerEntry, $employee) {
            $ledgerEntry->delete();
            $this->recalculateLedgerForEmployee($employee);
        });
    }
}
