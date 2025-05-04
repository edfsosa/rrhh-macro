<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;

Route::get('/payrolls/{payroll}/pdf', [PayrollController::class, 'exportPayrollPdf'])->name('payroll.pdf');
