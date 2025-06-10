<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    public function downloadPayslip(Payroll $payroll, Employee $employee)
    {
        $items = $payroll->items()
            ->where('employee_id', $employee->id)
            ->get()
            ->groupBy('type');

        $totals = [
            'salary' => $items->get('salary', collect())->sum('amount'),
            'perceptions' => $items->get('perception', collect())->sum('amount'),
            'deductions' => $items->get('deduction', collect())->sum('amount')
        ];

        $totals['gross'] = $totals['salary'] + $totals['perceptions'];
        $totals['net'] = $totals['gross'] - $totals['deductions'];

        $pdf = Pdf::loadView('payslip', [
            'employee' => $employee,
            'payroll' => $payroll,
            'items' => $items,
            'totals' => $totals
        ]);

        return $pdf->download("recibo-{$employee->id}-{$payroll->period}.pdf");
    }
}