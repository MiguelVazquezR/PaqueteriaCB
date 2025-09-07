<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ScheduleService
{
    /**
     * Creates a new schedule with its details and branch associations.
     */
    public function createSchedule(array $validatedData): ?Schedule
    {
        try {
            return DB::transaction(function () use ($validatedData) {
                $schedule = Schedule::create(['name' => $validatedData['name']]);

                $this->syncDetails($schedule, $validatedData['details']);
                $this->syncBranches($schedule, $validatedData['branch_ids']);

                return $schedule;
            });
        } catch (Throwable $e) {
            // Log the error
            return null;
        }
    }

    /**
     * Updates an existing schedule.
     */
    public function updateSchedule(Schedule $schedule, array $validatedData): bool
    {
        try {
            DB::transaction(function () use ($schedule, $validatedData) {
                $schedule->update(['name' => $validatedData['name']]);

                // Delete old details and sync new ones
                $schedule->details()->delete();
                $this->syncDetails($schedule, $validatedData['details']);

                // Sync branches, detaching any that are no longer associated
                $this->syncBranches($schedule, $validatedData['branch_ids'] ?? []);
            });
            return true;
        } catch (Throwable $e) {
            // Log the error
            return false;
        }
    }

    /**
     * Deletes a schedule only if it's not assigned to any employees.
     * Returns an array with success status and a message.
     */
    public function deleteSchedule(Schedule $schedule): array
    {
        $employeeCount = $schedule->employees()->count();

        if ($employeeCount > 0) {
            $employeeNames = $schedule->employees()->take(3)->get()->pluck('first_name')->implode(', ');
            $message = "Este horario no puede ser eliminado porque está asignado a {$employeeCount} " . Str::plural('empleado', $employeeCount) . " (Ej: {$employeeNames}).";

            return ['success' => false, 'message' => $message];
        }

        $schedule->delete();

        return ['success' => true, 'message' => 'Horario eliminado correctamente.'];
    }

    private function syncDetails(Schedule $schedule, array $details): void
    {
        $activeDetails = array_filter($details, fn ($detail) => $detail['is_active']);
        if (!empty($activeDetails)) {
            $schedule->details()->createMany($activeDetails);
        }
    }

    private function syncBranches(Schedule $schedule, array $branchIds): void
    {
        $schedule->branches()->sync($branchIds);
    }
}