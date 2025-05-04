<?php

namespace Database\Factories;

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
    public function definition()
    {
        $contractTypes = ['mensualero', 'jornalero'];
        $paymentMethods = ['debito', 'efectivo', 'cheque'];
        $statuses = ['activo', 'inactivo', 'suspendido'];

        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'ci' => $this->faker->unique()->numerify('########'),
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->safeEmail,
            'hire_date' => $this->faker->date(),
            'contract_type' => $this->faker->randomElement($contractTypes),
            'base_salary' => $this->faker->numberBetween(2500000, 8000000), // Guaraníes
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'position' => $this->faker->jobTitle,
            'department' => $this->faker->randomElement(['Ventas', 'Logística', 'Administración', 'Finanzas', 'RRHH']),
            'status' => $this->faker->randomElement($statuses),
        ];
    }
}
