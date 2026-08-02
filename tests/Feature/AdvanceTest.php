<?php

use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use App\Services\BankPaymentExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado con contrato activo para tests de Advance.
 */
function makeAdvEmployee(int $salary = 2_550_000): Employee
{
    static $ci = 7000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpAdv {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucAdv {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepAdv {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosAdv {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Advance',
        'ci' => (string) $n,
        'email' => "adv{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => 'mensual',
        'salary' => $salary,
        'payroll_type' => 'monthly',
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    EmployeeBankAccount::create([
        'employee_id' => $employee->id,
        'bank' => 'itau',
        'account_number' => '1234567890',
        'account_type' => 'corriente',
        'holder_name' => 'Test Advance',
        'holder_ci' => (string) $n,
        'is_primary' => true,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/** Crea un empleado jornalero con contrato activo para tests de Advance. */
function makeAdvJornalEmployee(int $dailyRate = 100_000): Employee
{
    static $ci = 7500000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpAdvJ {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucAdvJ {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepAdvJ {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosAdvJ {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Jornal',
        'ci' => (string) $n,
        'email' => "advj{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => 'jornal',
        'salary' => $dailyRate,
        'payroll_type' => 'monthly',
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    EmployeeBankAccount::create([
        'employee_id' => $employee->id,
        'bank' => 'itau',
        'account_number' => '1234567890',
        'account_type' => 'corriente',
        'holder_name' => 'Test Jornal',
        'holder_ci' => (string) $n,
        'is_primary' => true,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Crea el período mensual vigente (contiene "hoy") y marca $presentDays días
 * de asistencia presente dentro de él — usado por getAdvanceReferenceSalary().
 */
function makeAdvCurrentPeriodWithAttendance(Employee $employee, int $presentDays): PayrollPeriod
{
    $today = Carbon::now();

    $period = PayrollPeriod::create([
        'name' => $today->format('F Y'),
        'start_date' => $today->copy()->startOfMonth()->toDateString(),
        'end_date' => $today->copy()->endOfMonth()->toDateString(),
        'frequency' => 'monthly',
        'status' => 'draft',
    ]);

    for ($i = 0; $i < $presentDays; $i++) {
        AttendanceDay::create([
            'employee_id' => $employee->id,
            'date' => $today->copy()->startOfMonth()->addDays($i)->toDateString(),
            'status' => 'present',
        ]);
    }

    return $period;
}

/** Crea un adelanto para el empleado dado. */
function makeAdvance(Employee $employee, float $amount = 500_000, string $status = 'pending', string $paymentMethod = 'transfer'): Advance
{
    return Advance::create([
        'employee_id' => $employee->id,
        'amount' => $amount,
        'status' => $status,
        'payment_method' => $paymentMethod,
    ]);
}

/** Crea un PayrollPeriod mensual de referencia para tests de Advance. */
function makeAdvPeriod(int $month = 3): PayrollPeriod
{
    $start = Carbon::create(2026, $month, 1);

    return PayrollPeriod::create([
        'name' => $start->format('F Y'),
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->endOfMonth()->toDateString(),
        'frequency' => 'monthly',
        'status' => 'draft',
    ]);
}

/** Crea un Payroll mínimo para tests que requieren un payroll_id. */
function makeAdvPayroll(Employee $employee, PayrollPeriod $period): Payroll
{
    return Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'base_salary' => 2_550_000,
        'gross_salary' => 2_550_000,
        'net_salary' => 2_550_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
    ]);
}

/** Obtiene o crea el usuario admin para tests de Advance. */
function getAdvAdmin(): User
{
    return User::firstOrCreate(
        ['email' => 'adv_admin@test.com'],
        ['name' => 'Adv Admin', 'password' => bcrypt('password')],
    );
}

// ─── approve() ───────────────────────────────────────────────────────────────

it('aprueba un adelanto pendiente correctamente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);
    $admin = getAdvAdmin();

    $result = $advance->approve($admin->id);

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('approved')
        ->and($advance->approved_by_id)->toBe($admin->id)
        ->and($advance->approved_at)->not->toBeNull();
});

it('falla al aprobar si el adelanto no está pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Pendiente');
});

it('falla al aprobar si el empleado no tiene contrato activo', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $employee->activeContract->delete();

    $result = $advance->fresh()->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('contrato activo');
});

it('permite aprobar múltiples adelantos para el mismo empleado', function () {
    $employee = makeAdvEmployee();
    $first = makeAdvance($employee);
    $second = makeAdvance($employee);

    $resultFirst = $first->approve(getAdvAdmin()->id);
    $resultSecond = $second->approve(getAdvAdmin()->id);

    expect($resultFirst['success'])->toBeTrue()
        ->and($resultSecond['success'])->toBeTrue();
});

it('falla al aprobar adelanto de jornalero si supera el salario devengado en el período', function () {
    $employee = makeAdvJornalEmployee(dailyRate: 100_000);
    makeAdvCurrentPeriodWithAttendance($employee, presentDays: 5); // devengado: 500,000

    $advance = makeAdvance($employee, amount: 600_000);

    $result = $advance->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('salario devengado del jornalero');
});

it('permite aprobar adelanto de jornalero dentro del salario devengado en el período', function () {
    $employee = makeAdvJornalEmployee(dailyRate: 100_000);
    makeAdvCurrentPeriodWithAttendance($employee, presentDays: 5); // devengado: 500,000

    $advance = makeAdvance($employee, amount: 400_000);

    $result = $advance->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeTrue();
});

it('falla al aprobar adelanto de jornalero si la suma de adelantos activos supera lo devengado', function () {
    $employee = makeAdvJornalEmployee(dailyRate: 100_000);
    makeAdvCurrentPeriodWithAttendance($employee, presentDays: 5); // devengado: 500,000

    $first = makeAdvance($employee, amount: 300_000);
    $first->approve(getAdvAdmin()->id);

    $second = makeAdvance($employee, amount: 300_000);
    $result = $second->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('salario devengado del jornalero');
});

it('permite aprobar adelanto de jornalero sin días trabajados si getAdvanceReferenceSalary es null', function () {
    $employee = makeAdvJornalEmployee(dailyRate: 100_000);
    // Sin PayrollPeriod vigente ni asistencia → getAdvanceReferenceSalary() retorna null,
    // el cap no aplica (comportamiento igual al de antes del fix para este caso).
    $advance = makeAdvance($employee, amount: 5_000_000);

    $result = $advance->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeTrue();
});

// ─── reject() ────────────────────────────────────────────────────────────────

it('rechaza un adelanto pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->reject('No cumple requisitos');

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('rejected')
        ->and($advance->notes)->toContain('No cumple requisitos');
});

it('falla al rechazar si el adelanto no está pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->reject();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Pendiente');
});

it('rechaza sin motivo sin error', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->reject();

    expect($result['success'])->toBeTrue();
    expect($advance->fresh()->status)->toBe('rejected');
});

// ─── cancel() ────────────────────────────────────────────────────────────────

it('cancela un adelanto pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->cancel('Solicitud del empleado');

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('cancelled')
        ->and($advance->notes)->toContain('Solicitud del empleado');
});

it('cancela un adelanto aprobado aún no procesado en nómina', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->cancel();

    expect($result['success'])->toBeTrue();
    expect($advance->fresh()->status)->toBe('cancelled');
});

it('falla al cancelar un adelanto ya pagado', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'paid');

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse();
});

it('falla al cancelar un adelanto ya rechazado', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'rejected');

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse();
});

it('falla al cancelar si el adelanto está en estado disbursed', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'disbursed');

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse();
});

// ─── markAsPaid() ─────────────────────────────────────────────────────────────

it('marca el adelanto como pagado con el payroll_id correcto', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');
    $period = makeAdvPeriod(month: 4);
    $payroll = makeAdvPayroll($employee, $period);

    $advance->markAsPaid($payroll->id);

    $advance->refresh();
    expect($advance->status)->toBe('paid')
        ->and($advance->payroll_id)->toBe($payroll->id);
});

// ─── getActiveForEmployee() ───────────────────────────────────────────────────

it('getActiveForEmployee retorna adelanto pending', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'pending');

    expect(Advance::getActiveForEmployee($employee->id)?->id)->toBe($advance->id);
});

it('getActiveForEmployee retorna adelanto approved', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    expect(Advance::getActiveForEmployee($employee->id)?->id)->toBe($advance->id);
});

it('getActiveForEmployee retorna null si solo hay adelantos en estado final', function () {
    $employee = makeAdvEmployee();
    makeAdvance($employee, status: 'paid');
    makeAdvance($employee, status: 'rejected');
    makeAdvance($employee, status: 'cancelled');

    expect(Advance::getActiveForEmployee($employee->id))->toBeNull();
});

it('getActiveForEmployee retorna null si no hay adelantos', function () {
    $employee = makeAdvEmployee();

    expect(Advance::getActiveForEmployee($employee->id))->toBeNull();
});

// ─── getPendingCount() ───────────────────────────────────────────────────────

it('getPendingCount retorna el número de adelantos pendientes', function () {
    $emp1 = makeAdvEmployee();
    $emp2 = makeAdvEmployee();

    makeAdvance($emp1, status: 'pending');
    makeAdvance($emp2, status: 'pending');
    makeAdvance($emp1, status: 'approved');

    expect(Advance::getPendingCount())->toBe(2);
});

// ─── bank account validation ──────────────────────────────────────────────────

it('falla al aprobar si el empleado no tiene cuenta bancaria principal activa', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $employee->bankAccounts()->delete();

    $result = $advance->fresh()->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('cuenta bancaria');
});

// ─── markAsDisbursed() ───────────────────────────────────────────────────────

it('markAsDisbursed marca el adelanto como disbursed y sella la fecha', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $advance->markAsDisbursed('2026-05-15');

    $advance->refresh();
    expect($advance->status)->toBe('disbursed')
        ->and($advance->disbursed_at?->format('Y-m-d'))->toBe('2026-05-15')
        ->and($advance->payroll_id)->toBeNull();
});

it('markAsDisbursed sin fecha sella disbursed_at con hoy', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $advance->markAsDisbursed();

    $advance->refresh();
    expect($advance->status)->toBe('disbursed')
        ->and($advance->disbursed_at?->format('Y-m-d'))->toBe(now()->toDateString());
});

// ─── BankPaymentExportService::generateTxt() ─────────────────────────────────

it('generateTxt genera líneas D01 y C01 en formato fijo', function () {
    $employee = makeAdvEmployee(2_000_000);
    $advance = makeAdvance($employee, amount: 500_000, status: 'approved');
    $advance->load('employee.bankAccounts');

    $params = [
        'id_empresa' => '12345',
        'cuenta_debito' => '9876543210',
        'moneda' => 'Guaraní',
        'tipo' => 'Crédito',
        'fecha_credito' => '2026-05-15',
    ];

    $content = app(BankPaymentExportService::class)->generateTxt(
        $params,
        collect([$advance])
    );

    $lines = explode("\r\n", trim($content));

    expect($lines)->toHaveCount(2);
    expect($lines[0])->toStartWith('D01');
    expect($lines[1])->toStartWith('C01');
});

it('generateTxt sella disbursed_at sin cambiar el estado', function () {
    $employee = makeAdvEmployee(2_000_000);
    $advance = makeAdvance($employee, amount: 500_000, status: 'approved');
    $advance->load('employee.bankAccounts');

    $params = [
        'id_empresa' => '12345',
        'cuenta_debito' => '9876543210',
        'moneda' => 'Guaraní',
        'tipo' => 'Crédito',
        'fecha_credito' => '2026-05-15',
    ];

    app(BankPaymentExportService::class)->generateTxt($params, collect([$advance]));

    $fresh = $advance->fresh();
    expect($fresh->disbursed_at?->format('Y-m-d'))->toBe('2026-05-15')
        ->and($fresh->status)->toBe('approved')
        ->and($fresh->payroll_id)->toBeNull();
});

it('generateTxt incluye id_empresa y cuenta_debito en las líneas', function () {
    $employee = makeAdvEmployee(2_000_000);
    $advance = makeAdvance($employee, amount: 1_000_000, status: 'approved');
    $advance->load('employee.bankAccounts');

    $params = [
        'id_empresa' => '99888',
        'cuenta_debito' => '1111111111',
        'moneda' => 'Guaraní',
        'tipo' => 'Crédito',
        'fecha_credito' => '2026-05-15',
    ];

    $content = app(BankPaymentExportService::class)->generateTxt($params, collect([$advance]));

    $lines = explode("\r\n", trim($content));

    expect($lines[0])->toContain('99888')->toContain('1111111111');
    expect($lines[1])->toContain('99888')->toContain('1111111111');
});

// ─── bank_rejection_reason se limpia al entregar ─────────────────────────────

it('markAsDisbursed limpia bank_rejection_reason', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');
    $advance->update(['bank_rejection_reason' => 'cuenta_inexistente']);

    $advance->markAsDisbursed();

    $advance->refresh();
    expect($advance->bank_rejection_reason)->toBeNull();
});

it('DisbursementBatch::confirm limpia bank_rejection_reason en adelantos acreditados', function () {
    $user = User::factory()->create();
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');
    $advance->update(['bank_rejection_reason' => 'cuenta_bloqueada']);

    $batch = DisbursementBatch::create([
        'company_id' => $employee->branch->company_id,
        'type' => 'advances',
        'fecha_credito' => now()->toDateString(),
        'status' => 'pending',
        'created_by_id' => $user->id,
    ]);
    $advance->update(['disbursement_batch_id' => $batch->id]);

    $batch->confirm(confirmedById: $user->id, bankConfirmationPath: 'test/confirmacion.pdf');

    $advance->refresh();
    expect($advance->status)->toBe('disbursed')
        ->and($advance->bank_rejection_reason)->toBeNull();
});
