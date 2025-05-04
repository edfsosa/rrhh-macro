<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    public function exportPayrollPdf(Payroll $payroll)
    {
        $pdf = Pdf::loadView('pdf.payroll', ['payroll' => $payroll]);
        $filename = 'payroll_' . $payroll->id . '.pdf';

        return $pdf->stream($filename);
    }
}
