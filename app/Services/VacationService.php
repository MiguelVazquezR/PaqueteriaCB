<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\VacationLedger;
use App\Models\VacationPeriod; // Nuevo modelo
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationService
{
    /**
     * Procesa el devengo semanal de vacaciones.
     * Ahora actualiza tanto el historial global como el acumulado del periodo (año) actual.
     */
    public function accrueWeeklyVacationsForAllEmployees(): int
    {
        $activeEmployees = Employee::where('is_active', true)->get();
        $processedCount = 0;

        foreach ($activeEmployees as $employee) {
            DB::transaction(function () use ($employee, &$processedCount) {
                // 1. Asegurar que existan los periodos para este empleado
                $this->ensureVacationPeriodsExist($employee);

                // 2. Calcular datos del momento actual
                $yearsOfService = $employee->hire_date->diffInYears(now());
                $currentYearNumber = floor($yearsOfService) + 1; // Ej: 0.5 años -> Año 1. 1.1 años -> Año 2.

                // 3. Obtener días anuales para este año específico
                $annualVacationDays = $this->getAnnualVacationDays($currentYearNumber);
                $weeklyAccrual = $annualVacationDays / 52.1775;

                // 4. Actualizar el periodo actual (La "bolsa" específica del año)
                $currentPeriod = $employee->vacationPeriods()
                    ->where('year_number', $currentYearNumber)
                    ->first();

                if ($currentPeriod) {
                    // Solo acumulamos si no ha superado su límite anual (protección contra sobre-acumulación por redondeo)
                    if ($currentPeriod->days_accrued < $currentPeriod->days_entitled) {
                        $currentPeriod->increment('days_accrued', $weeklyAccrual);
                    }
                }

                // 5. Crear registro en el Ledger GLOBAL (Para historial y compatibilidad)
                $newLedgerEntry = VacationLedger::create([
                    'employee_id' => $employee->id,
                    'date'        => now()->toDateString(),
                    'type'        => 'earned',
                    'days'        => $weeklyAccrual,
                    'balance'     => 0, 
                    'description' => "Devengo semanal (Año {$currentYearNumber}, {$annualVacationDays} días anuales)",
                ]);

                // 6. Actualizar saldo global del empleado
                $employee->increment('vacation_balance', $weeklyAccrual);

                // 7. Actualizar balance del ledger entry
                $newLedgerEntry->balance = $employee->vacation_balance;
                $newLedgerEntry->save();

                $processedCount++;
            });
        }

        return $processedCount;
    }

    /**
     * Asegura que existan registros de VacationPeriod para cada año de antigüedad del empleado.
     * Crea periodos pasados y el actual si faltan.
     */
    public function ensureVacationPeriodsExist(Employee $employee): void
    {
        $hireDate = $employee->hire_date;
        $now = Carbon::now();
        $yearsSinceHire = $hireDate->diffInYears($now);
        
        // Iteramos desde el año 1 hasta el año actual (n + 1)
        for ($i = 0; $i <= $yearsSinceHire; $i++) {
            $yearNumber = $i + 1;
            
            // Definir fechas de inicio y fin de este "año laboral"
            $periodStart = $hireDate->copy()->addYears($i);
            $periodEnd = $hireDate->copy()->addYears($i + 1)->subDay();

            // Buscamos si ya existe, si no, lo creamos
            $period = VacationPeriod::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year_number' => $yearNumber
                ],
                [
                    'period_start'  => $periodStart,
                    'period_end'    => $periodEnd,
                    'days_entitled' => $this->getAnnualVacationDays($yearNumber), // Días por ley para ese año
                    'days_accrued'  => 0, // Se asumirá 0 para años nuevos, o lleno para años pasados (ver abajo)
                    'days_taken'    => 0,
                ]
            );

            // LOGICA DE AUTO-LLENADO PARA AÑOS PASADOS (MIGRACIÓN):
            // Si creamos un periodo de un año que YA PASÓ completamente, 
            // asumimos que los días ya se devengaron (ganaron) totalmente.
            if ($period->wasRecentlyCreated && $periodEnd->isPast()) {
                $period->days_accrued = $period->days_entitled;
                $period->save();
            }
        }
    }

    /**
     * Crea una transacción. Si es 'taken' (tomar vacaciones), distribuye el consumo
     * entre los periodos usando lógica FIFO (Primero entra, primero sale).
     */
    public function createTransaction(Employee $employee, array $data): void
    {
        DB::transaction(function () use ($employee, $data) {
            // 1. Asegurar consistencia de periodos antes de operar
            $this->ensureVacationPeriodsExist($employee);

            $ledgerData = [
                'type'        => $data['type'],
                'description' => $data['description'] ?? null,
            ];

            $daysToProcess = 0;

            if ($data['type'] === 'taken') {
                // Calcular días solicitados
                if (isset($data['date'])) {
                    $startDate = Carbon::parse($data['date']);
                    $endDate = Carbon::parse($data['date']);
                } else {
                    $startDate = Carbon::parse($data['start_date']);
                    $endDate = Carbon::parse($data['end_date']);
                }

                $daysRequested = $startDate->diffInDays($endDate) + 1;
                $ledgerData['date'] = $startDate;
                $ledgerData['days'] = - $daysRequested; // En negativo para el ledger global
                
                // --- LÓGICA FIFO PARA PERIODOS ---
                $this->distributeVacationConsumption($employee, $daysRequested);

                if ($data['create_incident'] ?? true) {
                    $this->createVacationIncident($employee, $startDate, $endDate, $data['description'] ?? null);
                }

                $daysToProcess = -$daysRequested;

            } else {
                // Ajustes manuales o días ganados manualmente
                $ledgerData['date'] = Carbon::now();
                $ledgerData['days'] = $data['days'];
                $daysToProcess = $data['days'];

                // Si es un ajuste positivo manual, podríamos necesitar lógica para sumarlo a un periodo específico,
                // por ahora lo dejamos simple afectando solo el global o el periodo actual.
                if ($data['type'] === 'adjustment' && $data['days'] > 0) {
                     // Opcional: Sumar al periodo actual si es un ajuste positivo
                     $currentPeriod = $employee->vacationPeriods()->latest('year_number')->first();
                     if ($currentPeriod) {
                         $currentPeriod->increment('days_accrued', $data['days']);
                     }
                }
            }

            // Crear registro global
            VacationLedger::create(
                ['employee_id' => $employee->id, 'balance' => 0] + $ledgerData
            );

            $this->recalculateLedgerForEmployee($employee);
        });
    }

    /**
     * Consume días de vacaciones de los periodos disponibles, del más antiguo al más reciente.
     */
    private function distributeVacationConsumption(Employee $employee, float $daysToConsume): void
    {
        // Obtener periodos ordenados por antigüedad
        $periods = $employee->vacationPeriods()->orderBy('year_number', 'asc')->get();

        foreach ($periods as $period) {
            if ($daysToConsume <= 0) break;

            // Calcular disponibilidad real en este periodo (devengado - tomado)
            // Nota: Podríamos usar days_entitled si permitimos tomar adelantado, 
            // pero days_accrued es más seguro. Usaremos accrued para ser estrictos, 
            // o entitled si la política permite "adelantar" del año en curso.
            // ASUMIMOS: Se puede tomar lo que corresponde al año (entitled) aunque no se haya devengado la semana exacta,
            // O ajusta a $period->days_accrued si quieres ser estricto.
            $availableInPeriod = $period->days_entitled - $period->days_taken;

            if ($availableInPeriod > 0) {
                $daysToTakeFromHere = min($daysToConsume, $availableInPeriod);
                
                $period->increment('days_taken', $daysToTakeFromHere);
                $daysToConsume -= $daysToTakeFromHere;
            }
        }

        // Si todavía quedan días por consumir (el empleado pidió más de lo que tiene en total histórico),
        // se los cargamos al ÚLTIMO periodo disponible (el año actual), dejándolo en negativo o sobregirado localmente.
        if ($daysToConsume > 0) {
            $lastPeriod = $periods->last();
            if ($lastPeriod) {
                $lastPeriod->increment('days_taken', $daysToConsume);
            }
        }
    }

    /**
     * Marca la prima vacacional de un periodo específico como pagada.
     */
    public function markPremiumAsPaid(VacationPeriod $period): void
    {
        $period->update([
            'is_premium_paid' => true,
            'premium_paid_at' => now(),
        ]);
    }

    // --- MÉTODOS EXISTENTES SIN CAMBIOS MAYORES (Solo Helpers) ---

    private function getAnnualVacationDays(int $yearsOfService): int
    {
        // Misma lógica de la ley
        // Ajuste: si yearsOfService es el año en curso (ej. Año 1), devuelve 12.
        if ($yearsOfService <= 0) return 12; // Default primer año
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
        return 32;
    }

    public function recalculateLedgerForEmployee(Employee $employee): void
    {
        $balance = 0;
        // Si hay un saldo inicial migrado en el ledger, debe considerarse
        // Nota: Con el nuevo sistema de periodos, el saldo inicial debería distribuirse en periodos pasados
        // idealmente, pero mantenemos esto para el "Total Global".
        
        $ledgers = VacationLedger::where('employee_id', $employee->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($ledgers as $ledger) {
            $balance += $ledger->days;
            $ledger->balance = $balance;
            $ledger->save();
        }

        $employee->update(['vacation_balance' => $balance]);
    }

    public function setInitialBalance(Employee $employee, float $initialBalance): void
    {
        // Método Legacy/Migración:
        // Además de crear el ledger, intentamos llenar periodos pasados si es posible,
        // pero es complejo adivinar. Por ahora mantenemos el ledger global.
        DB::transaction(function () use ($employee, $initialBalance) {
            VacationLedger::updateOrCreate(
                ['employee_id' => $employee->id, 'type' => 'initial'],
                [
                    'date'        => $employee->hire_date,
                    'days'        => $initialBalance,
                    'balance'     => 0,
                    'description' => 'Ajuste de saldo inicial',
                ]
            );
            $this->recalculateLedgerForEmployee($employee);
            
            // Aseguramos que existan las estructuras nuevas
            $this->ensureVacationPeriodsExist($employee);
        });
    }

    public function deleteTransaction(VacationLedger $ledgerEntry): void
    {
        $employee = $ledgerEntry->employee;
        DB::transaction(function () use ($ledgerEntry, $employee) {
            // Si eliminamos una vacación tomada, debemos "devolver" los días a los periodos.
            // Esto es complejo porque no guardamos de qué periodo exacto se restó cada día en el ledger.
            // SOLUCIÓN SIMPLIFICADA: Al borrar, revertimos del ÚLTIMO periodo afectado hacia atrás.
            // O más fácil: Recalcular TODO el consumo desde cero basado en el historial restante.
            // Para esta iteración, simplemente borramos el ledger y avisamos que los periodos pueden desincronizarse
            // si se edita historial antiguo. 
            // MEJORA IDEAL: Reconstruir distribution de periodos basado en todos los 'taken' activos.
            
            $type = $ledgerEntry->type;
            $days = abs($ledgerEntry->days); // Días positivos

            $ledgerEntry->delete();
            $this->recalculateLedgerForEmployee($employee);
            
            if ($type === 'taken') {
                // Revertir consumo: Devolver días a los periodos más recientes que tengan consumo
                $this->revertVacationConsumption($employee, $days);
            }
        });
    }

    private function revertVacationConsumption(Employee $employee, float $daysToReturn): void
    {
        // Buscamos periodos con días tomados, del más reciente al más antiguo (LIFO para devolución)
        $periods = $employee->vacationPeriods()
            ->where('days_taken', '>', 0)
            ->orderBy('year_number', 'desc')
            ->get();

        foreach ($periods as $period) {
            if ($daysToReturn <= 0) break;

            $canReturn = $period->days_taken;
            $toReturn = min($daysToReturn, $canReturn);

            $period->decrement('days_taken', $toReturn);
            $daysToReturn -= $toReturn;
        }
    }

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
}