<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Illuminate\Support\Facades\Log;

class DeductionCalculator
{
    /**
     * Calcula el total de deducciones activas del empleado en el período.
     *
     * Los embargos judiciales con `apply_judicial_limit = true` se procesan
     * por orden de antigüedad (start_date ASC) y se acumulan hasta el tope
     * legal del 25% del excedente sobre el salario mínimo (Art. 245 CLT).
     * Los embargos que no caben en el tope quedan en cola con monto 0.
     *
     * @param  float|null  $ipsBase  Base cotizable IPS (base_salary + ips_perceptions).
     *                               Se usa como base del tope de embargo y del cálculo IPS.
     *                               Si es null se usa el salario contractual del empleado.
     * @return array{total: float, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period, ?float $ipsBase = null): array
    {
        $settings = app(PayrollSettings::class);
        $ipsCode = $ipsBase !== null ? $settings->ips_deduction_code : null;

        $assignments = $employee->employeeDeductions()
            ->forPeriod($period->start_date, $period->end_date)
            ->with('deduction')
            ->orderBy('start_date') // prioridad por orden de llegada para embargos
            ->get()
            ->filter(function ($a) use ($employee) {
                if ($a->deduction !== null) {
                    return true;
                }
                Log::warning("DeductionCalculator: deducción ID {$a->deduction_id} eliminada referenciada por CI {$employee->ci} {$employee->first_name}, omitiendo", [
                    'employee_id' => $employee->id,
                    'employee_deduction_id' => $a->id,
                    'deduction_id' => $a->deduction_id,
                ]);

                return false;
            });

        // Separar embargos con tope legal del resto para procesarlos en orden
        $limitedEmbargos = $assignments->filter(
            fn ($a) => $a->deduction->type === 'judicial' && $a->deduction->apply_judicial_limit
        );
        $regularAssignments = $assignments->filter(
            fn ($a) => ! ($a->deduction->type === 'judicial' && $a->deduction->apply_judicial_limit)
        );

        // Calcular tope de embargo: 25% del excedente sobre el salario mínimo (Art. 245 CLT)
        // Base: ipsBase (salario + percepciones salariales + HE) — excluye viaticos, subsidios
        // y bonificación familiar, que viajan en vía separada y no son embargables.
        $effectiveBase = $ipsBase ?? (float) ($employee->base_salary ?? 0);
        $minSalary = (float) $settings->min_salary_monthly;
        $embargableBase = max(0.0, $effectiveBase - $minSalary);
        $judicialCap = round($embargableBase * 0.25, 2);
        $judicialUsed = 0.0;

        $items = [];
        $total = 0.0;

        // ── Deducciones sin tope judicial ──────────────────────────────────
        foreach ($regularAssignments as $assignment) {
            $deduction = $assignment->deduction;
            $customAmount = $assignment->custom_amount;
            $salaryBase = 0;

            if ($customAmount === null && $deduction->isPercentage()) {
                if ($ipsCode !== null && $deduction->code === $ipsCode) {
                    // No truncar a entero: extras/descanso semanal pueden aportar guaraníes fraccionarios.
                    $salaryBase = (float) $ipsBase;
                } else {
                    $salaryBase = $employee->employment_type === 'day_laborer'
                        ? (float) ($employee->daily_rate ?? 0)
                        : (float) ($employee->base_salary ?? 0);
                }

                if ($salaryBase <= 0) {
                    Log::warning("DeductionCalculator: CI {$employee->ci} {$employee->first_name} sin salario base válido para deducción '{$deduction->name}', aplicando Gs. 0", [
                        'employee_id' => $employee->id,
                        'deduction' => $deduction->name,
                        'salary_base' => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);

                    $items[] = ['description' => $deduction->name, 'amount' => 0, 'deduction_type' => $deduction->type];

                    continue;
                }
            }

            $amount = $deduction->calculateAmount($salaryBase, $customAmount);
            $description = $deduction->name;
            $total += $amount;
            $items[] = ['description' => $description, 'amount' => $amount, 'deduction_type' => $deduction->type];
        }

        // ── Embargos con tope legal — en orden de start_date ───────────────
        foreach ($limitedEmbargos as $assignment) {
            $deduction = $assignment->deduction;
            $customAmount = $assignment->custom_amount;
            $salaryBase = $employee->employment_type === 'day_laborer'
                ? (float) ($employee->daily_rate ?? 0)
                : (float) ($employee->base_salary ?? 0);

            $calculated = $deduction->calculateAmount($salaryBase, $customAmount);

            $remaining = max(0.0, $judicialCap - $judicialUsed);

            if ($remaining <= 0) {
                // Tope agotado: embargo en cola, no se descuenta este período
                Log::info("DeductionCalculator: embargo '{$deduction->name}' en cola — CI {$employee->ci} {$employee->first_name}: tope Art. 245 agotado (Gs. {$judicialCap})", [
                    'employee_id' => $employee->id,
                    'deduction' => $deduction->name,
                    'calculated' => $calculated,
                    'judicial_cap' => $judicialCap,
                ]);
                $items[] = ['description' => $deduction->name, 'amount' => 0.0, 'deduction_type' => $deduction->type];

                continue;
            }

            $amount = min($calculated, $remaining);

            if ($amount < $calculated) {
                Log::info("DeductionCalculator: embargo '{$deduction->name}' recortado al tope Art. 245 — CI {$employee->ci} {$employee->first_name}: Gs. {$calculated} → Gs. {$amount}", [
                    'employee_id' => $employee->id,
                    'deduction' => $deduction->name,
                    'calculated' => $calculated,
                    'applied' => $amount,
                    'judicial_cap' => $judicialCap,
                ]);
            }

            $judicialUsed += $amount;
            $total += $amount;
            $items[] = ['description' => $deduction->name, 'amount' => $amount, 'deduction_type' => $deduction->type];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
