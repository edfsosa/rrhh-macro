<?php

namespace App\Services;

use App\Models\{
    Employee,
    PayPeriod,
    DeductionType,
    PerceptionType,
    EmployeeDeduction,
    EmployeePerception,
    PaySlip
};
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Genera recibo de sueldo para un empleado en un período.
     */
    public function generatePaySlip(Employee $employee, PayPeriod $period): PaySlip
    {
        DB::transaction(function () use ($employee, $period, &$payslip) {
            // 1. Calcular remuneración base
            $base = $employee->base_salary;

            // 2. Calcular percepciones
            $perceptions = EmployeePerception::where('employee_id', $employee->id)
                ->where('pay_period_id', $period->id)
                ->get()
                ->map(fn($p) => $p->amount)
                ->sum();

            $gross = $base + $perceptions;

            // 3. Calcular deducciones (incluye IPS 9%)
            $deducts = EmployeeDeduction::where('employee_id', $employee->id)
                ->where('pay_period_id', $period->id)
                ->get()
                ->map(fn($d) => $d->amount)
                ->sum();

            // IPS 9% sobre bruto
            $ips = round(($gross - $deducts) * 0.09, 2);

            $totalDeductions = $deducts + $ips;

            // 4. Salario neto
            $net = $gross - $totalDeductions;

            // 5. Crear o actualizar PaySlip
            $payslip = PaySlip::updateOrCreate([
                'employee_id'   => $employee->id,
                'pay_period_id' => $period->id,
            ], [
                'gross_earnings'    => $gross,
                'total_perceptions' => $perceptions,
                'total_deductions'  => $totalDeductions,
                'net_salary'        => $net,
            ]);
        });

        return $payslip;
    }
}
