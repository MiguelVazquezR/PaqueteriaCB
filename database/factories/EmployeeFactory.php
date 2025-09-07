<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::inRandomOrder()->first()->id ?? Branch::factory(),
            'employee_number' => $this->faker->unique()->numerify('EMP-####'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'position' => $this->faker->randomElement(['Repartidor', 'Supervisor', 'Gerente', 'Almacenista']),
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'base_salary' => $this->faker->randomFloat(2, 8000, 25000),
            'is_active' => $this->faker->boolean(90), // 90% de probabilidad de estar activo
            'phone' => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
            'address' => $this->faker->address(),
            'curp' => $this->faker->unique()->regexify('[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}'),
            'rfc' => $this->faker->unique()->regexify('[A-Z]{4}[0-9]{6}[A-Z0-9]{3}'),
            'nss' => $this->faker->unique()->numerify('###########'),
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
        ];
    }
}
