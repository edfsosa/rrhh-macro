<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Log;

class PerceptionCalculator
{
    /**
     * Calcula las percepciones del empleado para el período dado.
     *
     * Retorna:
     *   - total:     suma de todas las percepciones (base para salario neto).
     *   - ips_total: suma solo de percepciones con affects_ips = true (base legal para IPS y aguinaldo).
     *   - items:     desglose con descripción, monto y flag affects_ips.
     *
     * @return array{total: int, ips_total: int, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $total = 0;
        $ipsTotal = 0;

        $assignments = $employee->employeePerceptions()
            ->forPeriod($period->start_date, $period->end_date)
            ->with('perception')
            ->get();

        foreach ($assignments as $assignment) {
            $perception = $assignment->perception;
            $customAmount = $assignment->custom_amount;
            $salaryBase = 0;

            if ($perception === null) {
                Log::warning("PerceptionCalculator: percepción ID {$assignment->perception_id} eliminada referenciada por CI {$employee->ci} {$employee->first_name}, omitiendo", [
                    'employee_id' => $employee->id,
                    'employee_perception_id' => $assignment->id,
                    'perception_id' => $assignment->perception_id,
                ]);

                continue;
            }

            if ($customAmount === null && $perception->isPercentage()) {
                $salaryBase = $employee->employment_type === 'day_laborer'
                    ? (float) ($employee->daily_rate ?? 0)
                    : (float) ($employee->base_salary ?? 0);

                if ($salaryBase <= 0) {
                    Log::warning("PerceptionCalculator: CI {$employee->ci} {$employee->first_name} sin salario base válido para percepción '{$perception->name}', aplicando Gs. 0", [
                        'employee_id' => $employee->id,
                        'perception' => $perception->name,
                        'salary_base' => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);

                    $items[] = [
                        'description' => $perception->name,
                        'amount' => 0,
                        'affects_ips' => $perception->affects_ips,
                        'perception_type' => $perception->type,
                    ];

                    continue;
                }
            }

            $amount = $perception->calculateAmount($salaryBase, $customAmount);
            $total += $amount;

            if ($perception->affects_ips) {
                $ipsTotal += $amount;
            }

            $items[] = [
                'description' => $perception->name,
                'amount' => $amount,
                'affects_ips' => $perception->affects_ips,
                'perception_type' => $perception->type,
            ];
        }

        return [
            'total' => $total,
            'ips_total' => $ipsTotal,
            'items' => $items,
        ];
    }
}
