<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $baseSalary = $this->faker->numberBetween(2500000, 8000000);
        $bonuses = $this->faker->numberBetween(0, 1000000);
        $gross = $baseSalary + $bonuses;
        $ips = intval($gross * 0.09);
        $otherDeductions = $this->faker->numberBetween(0, 500000);
        $totalDeductions = $ips + $otherDeductions;
        $netSalary = $gross - $totalDeductions;

        return [
            'employee_id' => Employee::factory(),
            'period' => $this->faker->date('Y-m'),
            'base_salary' => $baseSalary,
            'bonuses' => $bonuses,
            'deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ];
    }
}
