<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\VacationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetInitialVacationBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vacations:set-initial-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and set the initial vacation balance for all active employees based on their current anniversary year. SHOULD BE RUN ONLY ONCE.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(private VacationService $vacationService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // if (!$this->confirm('This will RESET the initial balance for all active employees based on their current anniversary year. Are you sure you want to continue?')) {
        //     $this->info('Operation cancelled.');
        //     return Command::SUCCESS;
        // }

        $this->info('Starting process to set initial vacation balances...');
        Log::info('Starting process to set initial vacation balances...');

        $employees = Employee::where('is_active', true)->get();
        $progressBar = $this->output->createProgressBar($employees->count());
        $progressBar->start();

        foreach ($employees as $employee) {
            try {
                $proportionalDays = $this->vacationService->calculateProportionalInitialBalance($employee);
                $this->vacationService->setInitialBalance($employee, $proportionalDays);

                Log::info("Set initial balance for {$employee->full_name} ({$employee->employee_number}): {$proportionalDays} days.");
            } catch (\Exception $e) {
                $this->error("\nFailed to process employee {$employee->id}: {$e->getMessage()}");
                Log::error("Failed to process employee {$employee->id} for initial balance.", ['exception' => $e]);
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nInitial vacation balance process finished successfully.");
        Log::info('Initial vacation balance process finished successfully.');

        return Command::SUCCESS;
    }
}