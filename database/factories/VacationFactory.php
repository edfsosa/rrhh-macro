<?php

namespace Database\Factories;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vacation>
 */
class VacationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance($this->faker->dateTimeThisYear());
        $end = (clone $start)->addDays(rand(5, 15));

        return [
            'employee_id' => $this->faker->randomElement(Employee::pluck('id')->toArray()),
            'start_date' => $start,
            'end_date' => $end,
            'status' => $this->faker->randomElement(['pendiente', 'aprobado', 'rechazado']),
        ];
    }
}
