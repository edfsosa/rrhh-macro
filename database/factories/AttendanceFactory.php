<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
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
            'type' => $this->faker->randomElement(['entrada', 'salida']),
            'location' => $this->faker->latitude() . ',' . $this->faker->longitude(),
        ];
    }
}
