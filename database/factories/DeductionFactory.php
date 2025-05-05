<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deduction>
 */
class DeductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->randomElement(Employee::pluck('id')->toArray()),
            'type' => $this->faker->randomElement(['Anticipo', 'Multa', 'Préstamo', 'Otro']),
            'description' => $this->faker->optional()->sentence,
            'amount' => $this->faker->numberBetween(50000, 1000000),
            'percentage' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(),
        ];
    }
}
