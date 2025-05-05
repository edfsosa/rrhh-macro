<?php

use App\Http\Controllers\AttendanceMarkingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;
use App\Models\Employee;

Route::get('/payrolls/{payroll}/pdf', [PayrollController::class, 'exportPayrollPdf'])->name('payroll.pdf');

Route::get('/marcar', [AttendanceMarkingController::class, 'showForm'])->name('marcar.form');
Route::post('/marcar', [AttendanceMarkingController::class, 'store'])->name('marcar.store');

Route::get('/api/employees', function () {
    $employees = Employee::where('status', 'activo')
        ->whereNotNull('photo')
        ->select('id', 'first_name', 'last_name', 'ci', 'photo')
        ->get();

    return response()->json($employees);
});
