<?php

namespace App\Services;

use App\Models\{
    Employee,
    Payroll,
    PayrollItem,
    EmployeeDeduction,
    EmployeePerception,
};

class PayrollService
{
    // Genera la nómina para todos los empleados
    public function generatePayroll(Payroll $payroll)
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            // Salario base
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'type' => 'salary',
                'description' => 'Salario Base',
                'amount' => $employee->base_salary
            ]);

            // Percepciones
            foreach ($employee->perceptions as $perception) {
                if ($this->isActive($perception->pivot, $payroll)) {
                    $amount = $perception->pivot->custom_amount ?? $perception->calculateFor($employee);

                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'employee_id' => $employee->id,
                        'type' => 'perception',
                        'description' => $perception->name,
                        'amount' => $amount
                    ]);

                    // Actualizar cuotas restantes
                    if ($perception->pivot->installments > 1) {
                        $perception->pivot->remaining_installments -= 1;
                        $perception->pivot->save();
                    }
                }
            }

            // Deducciones (incluyendo IPS)
            $this->processDeductions($employee, $payroll);
        }

        $payroll->update(['status' => 'processed']);
    }

    private function processDeductions(Employee $employee, Payroll $payroll)
    {
        // IPS (deducción global)
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'type' => 'deduction',
            'description' => 'IPS (9%)',
            'amount' => $employee->base_salary * 0.09
        ]);

        // Otras deducciones
        foreach ($employee->deductions as $deduction) {
            if ($deduction->name !== 'IPS' && $this->isActive($deduction->pivot, $payroll)) {
                $amount = $deduction->pivot->custom_amount ?? $deduction->calculateFor($employee);

                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'type' => 'deduction',
                    'description' => $deduction->name,
                    'amount' => $amount
                ]);

                // Actualizar cuotas restantes
                if ($deduction->pivot->installments > 1) {
                    $deduction->pivot->remaining_installments -= 1;
                    $deduction->pivot->save();
                }
            }
        }
    }

    private function isActive($pivot, Payroll $payroll): bool
    {
        // Verificar si está activo en el período de la nómina
        $start = $payroll->start_date;
        $end = $payroll->end_date;

        return (!$pivot->start_date || $pivot->start_date <= $end) &&
            (!$pivot->end_date || $pivot->end_date >= $start) &&
            ($pivot->remaining_installments > 0);
    }
}
