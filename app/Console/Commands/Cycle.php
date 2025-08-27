<?php

namespace App\Console\Commands;

use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Cycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:cycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Closes the current payroll period and opens the next one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payroll period cycle...');

        // 1. Encontrar el período abierto actual
        $currentPeriod = PayrollPeriod::where('status', 'open')->first();

        if (!$currentPeriod) {
            $this->error('No open payroll period found. Exiting.');
            Log::warning('Payroll cycle command ran but found no open period.');
            return 1; // Terminar con error
        }

        // 2. Cerrar el período actual
        $currentPeriod->status = 'closed';
        $currentPeriod->save();
        $this->info("Closed period for week #{$currentPeriod->week_number} (ends {$currentPeriod->end_date->format('Y-m-d')}).");

        // 3. Calcular las fechas para el nuevo período
        $lastEndDate = Carbon::parse($currentPeriod->end_date);
        $newStartDate = $lastEndDate->copy()->addDay()->startOfDay();
        $newEndDate = $newStartDate->copy()->addDays(6)->endOfDay();
        $newPaymentDate = $newEndDate->copy()->addDay();

        // 4. Manejar el reinicio de la semana al cambiar de año
        $newWeekNumber = $newStartDate->weekOfYear;
        // Si la semana del año anterior es mayor (ej. 52) y la nueva es 1, es un nuevo año.
        if ($currentPeriod->week_number > $newWeekNumber) {
            $this->info("New year detected. Week number reset to {$newWeekNumber}.");
        }

        // 5. Crear el nuevo período abierto
        $newPeriod = PayrollPeriod::create([
            'week_number' => $newWeekNumber,
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'payment_date' => $newPaymentDate,
            'status' => 'open',
        ]);

        $this->info("Successfully opened new period for week #{$newPeriod->week_number} (starts {$newPeriod->start_date->format('Y-m-d')}).");
        Log::info("Payroll period cycled successfully. New open period ID: {$newPeriod->id}");
        
        $this->info('Payroll period cycle finished.');
        return 0; // Terminar con éxito
    }
}
