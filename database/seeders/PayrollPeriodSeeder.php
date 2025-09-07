<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayrollPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Empezamos desde hace 5 semanas
        $date = Carbon::now()->subWeeks(5)->startOfWeek(Carbon::SATURDAY);

        while ($date->lessThan(Carbon::now())) {
            $startDate = $date->copy();
            $endDate = $date->copy()->addDays(6)->endOfDay();
            $paymentDate = $endDate->copy()->addDay();

            PayrollPeriod::create([
                'week_number' => $startDate->weekOfYear,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_date' => $paymentDate,
                'status' => $date->isCurrentWeek() ? 'open' : 'closed',
            ]);

            $date->addWeek();
        }
    }
}
