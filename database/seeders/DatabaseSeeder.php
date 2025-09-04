<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            BranchSeeder::class,
            ScheduleSeeder::class,
            UserEmployeeSeeder::class,
            IncidentTypeSeeder::class,
            PayrollPeriodSeeder::class,
            AttendanceAndIncidentSeeder::class,
            BonusReportSeeder::class,
        ]);
    }
}
