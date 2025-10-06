<?php

namespace App\Console\Commands;

use App\Services\VacationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AccrueVacationDays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vacations:accrue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Accrue proportional vacation days for all active employees weekly.';

    /**
     * The vacation service instance.
     *
     * @var \App\Services\VacationService
     */
    protected $vacationService;

    /**
     * Create a new command instance.
     *
     * @param \App\Services\VacationService $vacationService
     * @return void
     */
    public function __construct(VacationService $vacationService)
    {
        parent::__construct();
        $this->vacationService = $vacationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting weekly vacation accrual process...');
        Log::info('Starting weekly vacation accrual process...');

        try {
            $processedCount = $this->vacationService->accrueWeeklyVacationsForAllEmployees();
            $this->info("Successfully processed vacation accrual for {$processedCount} employees.");
            Log::info("Successfully processed vacation accrual for {$processedCount} employees.");
        } catch (\Exception $e) {
            $this->error('An error occurred during the vacation accrual process.');
            $this->error($e->getMessage());
            Log::error('Vacation Accrual Failed: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }

        $this->info('Vacation accrual process finished successfully.');
        Log::info('Vacation accrual process finished successfully.');
        return Command::SUCCESS;
    }
}