<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Perception>
 */
class PerceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mode = $this->faker->randomElement(['monto_fijo', 'porcentaje']);

        return [
            'employee_id' => $this->faker->randomElement(Employee::pluck('id')->toArray()),
            'type' => $this->faker->randomElement(['Bono', 'Comisión', 'Horas Extra', 'Otro']),
            'description' => $this->faker->optional()->sentence,
            'amount' => $mode === 'monto_fijo' ? $this->faker->numberBetween(50000, 1000000) : null,
            'percentage' => $mode === 'porcentaje' ? $this->faker->numberBetween(1, 30) : null,
            'mode' => $mode,
            'is_active' => $this->faker->boolean(),
        ];
    }
}
