<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\HolidayRule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class HolidayService
{
    /**
     * *** AÑADIDO: Mapa para traducir nombres de días en español a su número ISO (1=Lunes, 7=Domingo).
     */
    private const WEEKDAY_MAP = [
        'Lunes' => Carbon::MONDAY,
        'Martes' => Carbon::TUESDAY,
        'Miércoles' => Carbon::WEDNESDAY,
        'Jueves' => Carbon::THURSDAY,
        'Viernes' => Carbon::FRIDAY,
        'Sábado' => Carbon::SATURDAY,
        'Domingo' => Carbon::SUNDAY,
    ];

    /**
     * *** AÑADIDO: Mapa para traducir el orden ordinal a un número.
     */
    private const ORDER_MAP = [
        'Primer' => 1,
        'Segundo' => 2,
        'Tercer' => 3,
        'Cuarto' => 4,
        'Último' => 5, // Usaremos 5 para el último, se manejará de forma especial.
    ];

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
                $query->whereHas('branches', function ($q) use ($employee) {
                    $q->where('branch_id', $employee->branch_id);
                })
                    ->orWhereDoesntHave('branches');
            })
            ->get();

        // Iterar sobre cada día del rango solicitado.
        foreach ($dateRange as $date) {
            // Iterar sobre cada regla aplicable.
            foreach ($applicableRules as $rule) {
                // Se decodifica la definición de la regla una sola vez.
                $definition = $rule->rule_definition;

                if ($this->isDateMatchRule($date, $definition)) {
                    // Si hay una coincidencia, la guardamos y pasamos al siguiente día.
                    $holidays[$date->format('Y-m-d')] = $rule->name;
                    break; // No necesitamos seguir revisando otras reglas para este día.
                }
            }
        }

        return $holidays;
    }

    /**
     * *** MÉTODO AÑADIDO/CORREGIDO: Lógica central para verificar si una fecha coincide con una regla.
     *
     * @param Carbon $date
     * @param array $definition
     * @return bool
     */
    private function isDateMatchRule(Carbon $date, array $definition): bool
    {
        // Si la definición no tiene un tipo, no podemos procesarla.
        if (empty($definition['type'])) {
            return false;
        }

        switch ($definition['type']) {
            case 'fixed':
                // Lógica para fechas fijas (ej. 1 de Enero)
                return $date->month == $definition['month'] && $date->day == $definition['day'];

            case 'dynamic':
                // Lógica para fechas dinámicas (ej. Primer Lunes de Febrero)
                // 1. Validar que la regla tenga los campos necesarios.
                if (!isset($definition['month'], $definition['order'], $definition['weekday'])) {
                    return false;
                }

                // 2. Comprobar si el mes coincide.
                if ($date->month != $definition['month']) {
                    return false;
                }

                // 3. Obtener el número del día de la semana a partir del nombre en español.
                $targetWeekdayNumber = self::WEEKDAY_MAP[$definition['weekday']] ?? null;
                if ($targetWeekdayNumber === null) {
                    return false; // El nombre del día no es válido.
                }

                // 4. Comprobar si el día de la semana de la fecha actual coincide con el de la regla.
                if ($date->dayOfWeekIso != $targetWeekdayNumber) {
                    return false;
                }

                // 5. Comprobar el orden (Primer, Segundo, etc.)
                $targetOrder = self::ORDER_MAP[$definition['order']] ?? null;
                if ($targetOrder === null) {
                    return false; // El 'orden' no es válido.
                }

                // Si es 'Último'
                if ($targetOrder === 5) {
                    // Comprobamos si la próxima semana, en el mismo día, se sale del mes.
                    return $date->copy()->addWeek()->month != $date->month;
                } else {
                    // Calculamos qué ocurrencia del día es en el mes (ej. es el 1er, 2do, 3er lunes?)
                    $currentOrder = (int) ceil($date->day / 7);
                    return $currentOrder === $targetOrder;
                }

            default:
                return false;
        }
    }
}
