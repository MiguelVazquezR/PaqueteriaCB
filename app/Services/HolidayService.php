<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\HolidayRule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class HolidayService
{
    /**
     * Obtiene un array asociativo de días festivos para un empleado en un rango de fechas.
     * Este método es eficiente porque consulta la base de datos solo una vez.
     *
     * @return array ['YYYY-MM-DD' => 'Nombre del Festivo']
     */
    public function getHolidaysForPeriod(Employee $employee, CarbonPeriod $dateRange): array
    {
        $holidays = [];

        // Obtener todas las reglas de festivos activas que apliquen a la sucursal del empleado.
        $applicableRules = HolidayRule::where('is_active', true)
            ->where(function ($query) use ($employee) {
                // La regla aplica a todas las sucursales O...
                $query->whereHas('branches', function ($q) use ($employee) {
                    $q->where('branch_id', $employee->branch_id);
                })
                // ...la regla no tiene ninguna sucursal específica (aplica a todas por defecto).
                ->orWhereDoesntHave('branches');
            })
            ->get();

        // Iterar sobre cada día del rango solicitado.
        foreach ($dateRange as $date) {
            // Iterar sobre cada regla aplicable.
            foreach ($applicableRules as $rule) {
                if ($this->isDateMatchForRule($date, $rule)) {
                    // Si hay una coincidencia, la guardamos y pasamos al siguiente día.
                    $holidays[$date->format('Y-m-d')] = $rule->name;
                    break; // No necesitamos seguir revisando otras reglas para este día.
                }
            }
        }

        return $holidays;
    }

    /**
     * Verifica si una fecha coincide con una regla de día festivo (fija o dinámica).
     */
    private function isDateMatchForRule(Carbon $date, HolidayRule $rule): bool
    {
        $definition = $rule->rule_definition;
        $type = $definition['type'] ?? 'fixed';

        if ($type === 'fixed') {
            // Regla fija: ej. "16 de Septiembre"
            return $date->month == $definition['month'] && $date->day == $definition['day'];
        }

        if ($type === 'dynamic') {
            // Regla dinámica: ej. "Tercer Lunes de Noviembre"
            $nthDayOfMonth = Carbon::create($date->year, $date->month, 1)
                ->nthOfMonth($definition['order'], $definition['weekday']);

            // Comprobamos si la fecha calculada es la misma que la fecha que estamos revisando.
            return $nthDayOfMonth && $nthDayOfMonth->isSameDay($date);
        }

        return false;
    }
}