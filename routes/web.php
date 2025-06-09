<?php

use App\Http\Controllers\AttendanceMarkingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\Request;

Route::get('/payrolls/{payroll}/pdf', [PayrollController::class, 'exportPayrollPdf'])->name('payroll.pdf');

Route::get('/marcar', [AttendanceMarkingController::class, 'showForm'])->name('marcar.form');
Route::post('/marcar', [AttendanceMarkingController::class, 'store'])->name('marcar.store');

Route::get('/api/employees', function (Request $request) {
    $branch_id = $request->query('branch_id'); // Obtener branch_id del parámetro de consulta

    $employees = Employee::where('status', 'activo')
        ->where('branch_id', $branch_id) // Filtrar por sucursal
        ->whereNotNull('photo')
        ->select('id', 'first_name', 'last_name', 'ci', 'photo')
        ->get();

    return response()->json($employees);
});

Route::get('/api/branches', function () {
    $branches = Branch::select('id', 'name')->get();
    return response()->json($branches);
});
