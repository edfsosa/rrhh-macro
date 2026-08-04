<?php

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Vacation;
use App\Services\AbsencePenaltyCalculator;
use App\Services\AdvanceCalculator;
use App\Services\DeductionCalculator;
use App\Services\ExtraHourCalculator;
use App\Services\FamilyBonusCalculator;
use App\Services\LoanInstallmentCalculator;
use App\Services\MerchandiseInstallmentCalculator;
use App\Services\PayrollPDFGenerator;
use App\Services\PayrollService;
use App\Services\PerceptionCalculator;
use App\Services\RestDayCalculator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Crea un PayrollService con todos los calculadores mockeados.
 * Los mocks devuelven resultados vacíos por defecto — se pueden sobreescribir en cada test.
 */
function makePayService(array $overrides = []): PayrollService
{
    $perception = $overrides['perception'] ?? Mockery::mock(PerceptionCalculator::class);
    $deduction = $overrides['deduction'] ?? Mockery::mock(DeductionCalculator::class);
    $extra = $overrides['extra'] ?? Mockery::mock(ExtraHourCalculator::class);
    $absence = $overrides['absence'] ?? Mockery::mock(AbsencePenaltyCalculator::class);
    $loan = $overrides['loan'] ?? Mockery::mock(LoanInstallmentCalculator::class);
    $advance = $overrides['advance'] ?? Mockery::mock(AdvanceCalculator::class);
    $merchandise = $overrides['merchandise'] ?? Mockery::mock(MerchandiseInstallmentCalculator::class);
    $family = $overrides['family'] ?? Mockery::mock(FamilyBonusCalculator::class);
    $restDay = $overrides['restDay'] ?? Mockery::mock(RestDayCalculator::class);
    $pdf = $overrides['pdf'] ?? Mockery::mock(PayrollPDFGenerator::class);

    $emptyResult = ['total' => 0, 'ips_total' => 0, 'items' => []];
    $emptyLoanResult = ['installments' => collect()];

    $perception->shouldReceive('calculate')->andReturn($emptyResult)->byDefault();
    $deduction->shouldReceive('calculate')->andReturn($emptyResult)->byDefault();
    $extra->shouldReceive('calculate')->andReturn($emptyResult)->byDefault();
    $absence->shouldReceive('calculate')->andReturn($emptyResult)->byDefault();
    $loan->shouldReceive('calculate')->andReturn($emptyLoanResult)->byDefault();
    $loan->shouldReceive('markInstallmentsAsPaid')->andReturn(0)->byDefault();
    $advance->shouldReceive('calculate')->andReturn(['advances' => collect()])->byDefault();
    $advance->shouldReceive('markAdvancesAsPaid')->andReturn(0)->byDefault();
    $merchandise->shouldReceive('calculate')->andReturn(['installments' => collect()])->byDefault();
    $merchandise->shouldReceive('markInstallmentsAsPaid')->andReturn(0)->byDefault();
    $family->shouldReceive('calculate')->andReturn(['total' => 0, 'items' => []])->byDefault();
    $restDay->shouldReceive('calculate')->andReturn(['total' => 0.0, 'items' => []])->byDefault();
    $pdf->shouldReceive('generate')->andReturn('mock/payroll.pdf')->byDefault();

    return new PayrollService($perception, $deduction, $extra, $absence, $loan, $advance, $merchandise, $family, $restDay, $pdf);
}

function makePayEmployee(
    string $salaryType = 'mensual',
    int $salary = 2_550_000,
    string $payrollType = 'monthly',
    string $status = 'active',
    string $contractStatus = 'active',
): Employee {
    static $ci = 1000000;
    $n = $ci++;

    $company = Company::create(['name' => "Emp {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Suc {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Dep {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Pos {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Pay',
        'ci' => (string) $n,
        'email' => "pay{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => $status,
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => $salaryType,
        'salary' => $salary,
        'payroll_type' => $payrollType,
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => $contractStatus,
    ]);

    return $employee->fresh();
}

function makePayPeriod(string $status = 'draft', string $frequency = 'monthly', int $month = 3): PayrollPeriod
{
    $start = Carbon::create(2026, $month, 1);

    return PayrollPeriod::create([
        'name' => $start->format('F Y'),
        'start_date' => $start->toDateString(),
        'end_date' => $start->endOfMonth()->toDateString(),
        'frequency' => $frequency,
        'status' => $status,
    ]);
}

// ─── generateForPeriod — validaciones ────────────────────────────────────────

it('generateForPeriod lanza excepción si el período no está en draft o processing', function () {
    $period = makePayPeriod('closed');

    expect(fn () => makePayService()->generateForPeriod($period))
        ->toThrow(InvalidArgumentException::class);
});

it('generateForPeriod retorna 0 si no hay empleados activos', function () {
    $period = makePayPeriod('draft');

    $result = makePayService()->generateForPeriod($period);

    expect($result['generated'])->toBe(0)
        ->and($result['skipped'])->toBe([]);
});

it('generateForPeriod solo procesa empleados activos con contrato activo', function () {
    $period = makePayPeriod('draft');
    makePayEmployee(status: 'inactive');
    makePayEmployee(contractStatus: 'suspended'); // empleado activo pero contrato suspendido

    $result = makePayService()->generateForPeriod($period);

    expect($result['generated'])->toBe(0)
        ->and(Payroll::count())->toBe(0);
});

it('generateForPeriod solo procesa empleados cuyo payroll_type coincide con la frecuencia', function () {
    $period = makePayPeriod('draft', 'monthly');

    makePayEmployee(payrollType: 'biweekly'); // no debe procesarse
    makePayEmployee(payrollType: 'monthly');  // sí debe procesarse

    $result = makePayService()->generateForPeriod($period);

    expect($result['generated'])->toBe(1);
});

it('generateForPeriod incluye en skipped al empleado con payroll_type diferente', function () {
    $period = makePayPeriod('draft', 'monthly');

    makePayEmployee(payrollType: 'biweekly');

    $result = makePayService()->generateForPeriod($period);

    expect($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['reason'])->toContain('Tipo de nómina diferente');
});

it('generateForPeriod no duplica nómina si ya existe para el empleado y período', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $service = makePayService();
    $service->generateForPeriod($period);
    $service->generateForPeriod($period); // segunda pasada

    expect(Payroll::count())->toBe(1);
});

// ─── generateForPeriod — cálculo ─────────────────────────────────────────────

it('generateForPeriod crea Payroll con los totales correctos', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('mensual', 2_550_000);

    $perception = Mockery::mock(PerceptionCalculator::class);
    $perception->shouldReceive('calculate')->andReturn(['total' => 200_000, 'ips_total' => 200_000, 'items' => [
        ['description' => 'Bono', 'amount' => 200_000],
    ]]);

    $deduction = Mockery::mock(DeductionCalculator::class);
    $deduction->shouldReceive('calculate')->andReturn(['total' => 100_000, 'items' => [
        ['description' => 'IPS', 'amount' => 100_000],
    ]]);

    makePayService(['perception' => $perception, 'deduction' => $deduction])
        ->generateForPeriod($period);

    $payroll = Payroll::first();

    expect((float) $payroll->base_salary)->toBe(2_550_000.0)
        ->and((float) $payroll->total_perceptions)->toBe(200_000.0)
        ->and((float) $payroll->total_deductions)->toBe(100_000.0)
        ->and((float) $payroll->gross_salary)->toBe(2_750_000.0)
        ->and((float) $payroll->net_salary)->toBe(2_650_000.0);
});

it('generateForPeriod suma horas extra a total_perceptions', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('mensual', 2_550_000);

    $extra = Mockery::mock(ExtraHourCalculator::class);
    $extra->shouldReceive('calculate')->andReturn(['total' => 50_000, 'hours' => 2, 'items' => [
        ['description' => 'Horas Extra Diurnas', 'amount' => 50_000],
    ]]);

    makePayService(['extra' => $extra])->generateForPeriod($period);

    $payroll = Payroll::first();
    expect((float) $payroll->total_perceptions)->toBe(50_000.0);
});

it('generateForPeriod crea PayrollItems de tipo perception y deduction', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $perception = Mockery::mock(PerceptionCalculator::class);
    $perception->shouldReceive('calculate')->andReturn(['total' => 100_000, 'ips_total' => 100_000, 'items' => [
        ['description' => 'Bono A', 'amount' => 60_000],
        ['description' => 'Bono B', 'amount' => 40_000],
    ]]);

    $deduction = Mockery::mock(DeductionCalculator::class);
    $deduction->shouldReceive('calculate')->andReturn(['total' => 50_000, 'items' => [
        ['description' => 'IPS', 'amount' => 50_000],
    ]]);

    makePayService(['perception' => $perception, 'deduction' => $deduction])
        ->generateForPeriod($period);

    expect(PayrollItem::where('type', 'perception')->count())->toBe(2)
        ->and(PayrollItem::where('type', 'deduction')->count())->toBe(1);
});

it('generateForPeriod retorna el conteo de nóminas generadas', function () {
    $period = makePayPeriod('draft');
    makePayEmployee();
    makePayEmployee();

    $result = makePayService()->generateForPeriod($period);

    expect($result['generated'])->toBe(2)
        ->and($result['skipped'])->toBe([]);
});

// ─── generateForPeriod — jornalero ───────────────────────────────────────────

it('generateForPeriod omite jornalero sin días trabajados', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('jornal', 150_000);
    // Sin AttendanceDays → workedDays = 0

    $result = makePayService()->generateForPeriod($period);

    expect($result['generated'])->toBe(0)
        ->and(Payroll::count())->toBe(0)
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['reason'])->toBe('Jornalero sin días trabajados en el período');
});

it('generateForPeriod calcula salario base del jornalero según días trabajados', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('jornal', 150_000);

    // 10 días presentes
    foreach (range(1, 10) as $day) {
        AttendanceDay::create([
            'employee_id' => $employee->id,
            'date' => Carbon::create(2026, 3, $day)->toDateString(),
            'status' => 'present',
        ]);
    }

    makePayService()->generateForPeriod($period);

    $payroll = Payroll::first();
    expect((float) $payroll->base_salary)->toBe(round(150_000 * 10, 2));
});

// ─── generateForEmployee — validaciones ──────────────────────────────────────

it('generateForEmployee lanza excepción si el período no está en draft o processing', function () {
    $period = makePayPeriod('closed');
    $employee = makePayEmployee();

    expect(fn () => makePayService()->generateForEmployee($employee, $period))
        ->toThrow(InvalidArgumentException::class);
});

it('generateForEmployee lanza excepción si ya existe nómina para el empleado y período', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    makePayService()->generateForEmployee($employee, $period);

    expect(fn () => makePayService()->generateForEmployee($employee, $period))
        ->toThrow(InvalidArgumentException::class);
});

it('generateForEmployee lanza excepción si el empleado no tiene contrato activo', function () {
    $period = makePayPeriod('draft');

    // Empleado sin contrato
    static $ci = 2000000;
    $n = $ci++;
    $company = Company::create(['name' => "Empresa Sin Contrato {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Sin Contrato {$n}", 'company_id' => $company->id]);
    $employee = Employee::create([
        'first_name' => 'Sin',
        'last_name' => 'Contrato',
        'ci' => (string) $n,
        'email' => "nocon{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    expect(fn () => makePayService()->generateForEmployee($employee, $period))
        ->toThrow(InvalidArgumentException::class);
});

it('generateForEmployee lanza excepción si el payroll_type no coincide con la frecuencia', function () {
    $period = makePayPeriod('draft', 'monthly');
    $employee = makePayEmployee(payrollType: 'biweekly');

    expect(fn () => makePayService()->generateForEmployee($employee, $period))
        ->toThrow(InvalidArgumentException::class);
});

it('generateForEmployee lanza excepción si jornalero no tiene días trabajados', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('jornal', 150_000);

    expect(fn () => makePayService()->generateForEmployee($employee, $period))
        ->toThrow(InvalidArgumentException::class);
});

// ─── generateForEmployee — cálculo ───────────────────────────────────────────

it('generateForEmployee retorna Payroll con net_salary correcto', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('mensual', 2_550_000);

    $deduction = Mockery::mock(DeductionCalculator::class);
    $deduction->shouldReceive('calculate')->andReturn(['total' => 229_500, 'items' => [
        ['description' => 'IPS 9%', 'amount' => 229_500],
    ]]);

    $payroll = makePayService(['deduction' => $deduction])
        ->generateForEmployee($employee, $period);

    expect((float) $payroll->net_salary)->toBe(2_550_000.0 - 229_500.0)
        ->and($payroll->status)->toBe('draft')
        ->and($payroll->pdf_path)->toBe('mock/payroll.pdf');
});

it('generateForEmployee marca cuotas de préstamo como pagadas', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $loan = Loan::create([
        'employee_id' => $employee->id,
        'type' => 'loan',
        'amount' => 600_000,
        'remaining_debt' => 600_000,
        'installment_amount' => 200_000,
        'status' => 'approved',
    ]);

    $installment = LoanInstallment::create([
        'loan_id' => $loan->id,
        'installment_number' => 1,
        'amount' => 200_000,
        'due_date' => '2026-03-15',
        'status' => 'pending',
    ]);

    $loanMock = Mockery::mock(LoanInstallmentCalculator::class);
    $loanMock->shouldReceive('calculate')->andReturn([
        'installments' => collect([$installment]),
    ]);
    $loanMock->shouldReceive('markInstallmentsAsPaid')
        ->once()
        ->withArgs(fn ($ids, $payrollId) => $ids === [$installment->id] && is_int($payrollId))
        ->andReturn(1);

    makePayService(['loan' => $loanMock])
        ->generateForEmployee($employee, $period);
});

// ─── regenerateForEmployee ────────────────────────────────────────────────────

it('regenerateForEmployee lanza excepción si la nómina está aprobada', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();
    $service = makePayService();

    $payroll = $service->generateForEmployee($employee, $period);
    $payroll->update(['status' => 'approved']);

    expect(fn () => $service->regenerateForEmployee($payroll->fresh()))
        ->toThrow(InvalidArgumentException::class);
});

it('regenerateForEmployee lanza excepción si la nómina está pagada', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();
    $service = makePayService();

    $payroll = $service->generateForEmployee($employee, $period);
    $payroll->update(['status' => 'paid']);

    expect(fn () => $service->regenerateForEmployee($payroll->fresh()))
        ->toThrow(InvalidArgumentException::class);
});

it('regenerateForEmployee elimina items previos y crea nuevos', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();
    $service = makePayService();

    $payroll = $service->generateForEmployee($employee, $period);
    $countBefore = $payroll->items()->count();

    // Segunda generación con una percepción extra
    $perception = Mockery::mock(PerceptionCalculator::class);
    $perception->shouldReceive('calculate')->andReturn(['total' => 100_000, 'ips_total' => 100_000, 'items' => [
        ['description' => 'Bono', 'amount' => 100_000],
    ]]);

    makePayService(['perception' => $perception])
        ->regenerateForEmployee($payroll->fresh());

    $itemsAfter = $payroll->fresh()->items;

    // Solo los items del recalculo existen (los anteriores fueron eliminados)
    expect($itemsAfter->where('type', 'perception')->count())->toBe(1)
        ->and($itemsAfter->first()->description)->toBe('Bono');
});

it('regenerateForEmployee actualiza los montos en el registro existente', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee('mensual', 2_550_000);
    $service = makePayService();

    $payroll = $service->generateForEmployee($employee, $period);

    $deduction = Mockery::mock(DeductionCalculator::class);
    $deduction->shouldReceive('calculate')->andReturn(['total' => 500_000, 'items' => [
        ['description' => 'Nueva deducción', 'amount' => 500_000],
    ]]);

    makePayService(['deduction' => $deduction])
        ->regenerateForEmployee($payroll->fresh());

    expect((float) $payroll->fresh()->total_deductions)->toBe(500_000.0)
        ->and((float) $payroll->fresh()->net_salary)->toBe(2_550_000.0 - 500_000.0);
});

// ─── generateForPeriod — manejo de errores ───────────────────────────────────

it('generateForPeriod incluye el mensaje de la excepción de negocio en skipped', function () {
    $period = makePayPeriod('draft');
    makePayEmployee();

    $perception = Mockery::mock(PerceptionCalculator::class);
    $perception->shouldReceive('calculate')->andThrow(new InvalidArgumentException('Regla de negocio X violada'));

    $result = makePayService(['perception' => $perception])->generateForPeriod($period);

    expect($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['reason'])->toBe('Error al calcular: Regla de negocio X violada');
});

it('generateForPeriod usa mensaje genérico en skipped ante un QueryException', function () {
    $period = makePayPeriod('draft');
    makePayEmployee();

    $perception = Mockery::mock(PerceptionCalculator::class);
    $perception->shouldReceive('calculate')->andThrow(
        new QueryException('mysql', 'select * from `secreto`', [], new Exception('duplicate entry'))
    );

    $result = makePayService(['perception' => $perception])->generateForPeriod($period);

    expect($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['reason'])->toBe('Error al calcular (revisar log del servidor)');
});

// ─── vacaciones with_payroll — pago único (no prorrateado) ───────────────────

it('paga la remuneración vacacional completa en el período del start_date, aunque end_date caiga en el siguiente', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    // Vacación que arranca dentro del período pero termina después de su fin.
    $vacation = Vacation::create([
        'employee_id' => $employee->id,
        'start_date' => $period->end_date->copy()->subDays(2),
        'end_date' => $period->end_date->copy()->addDays(5),
        'status' => 'approved',
        'payment_method' => 'with_payroll',
        'payment_amount' => 300_000,
        'payment_status' => 'unpaid',
    ]);

    $payroll = makePayService()->generateForEmployee($employee, $period);

    expect((float) $payroll->total_perceptions)->toBe(300_000.0)
        ->and($vacation->fresh()->payment_status)->toBe('paid');
});

// ─── PDF fuera de la transacción ─────────────────────────────────────────────

it('generateForEmployee crea la nómina aunque la generación de PDF falle', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $pdf = Mockery::mock(PayrollPDFGenerator::class);
    $pdf->shouldReceive('generate')->andThrow(new Exception('Fallo simulado de PDF'));

    $payroll = makePayService(['pdf' => $pdf])->generateForEmployee($employee, $period);

    expect(Payroll::count())->toBe(1)
        ->and($payroll->fresh()->pdf_path)->toBeNull();
});

it('generateForPeriod no genera el PDF si un calculador falla a mitad de pipeline', function () {
    $period = makePayPeriod('draft');
    makePayEmployee();

    $deduction = Mockery::mock(DeductionCalculator::class);
    $deduction->shouldReceive('calculate')->andThrow(new Exception('Fallo simulado de cálculo'));

    $pdf = Mockery::mock(PayrollPDFGenerator::class);
    $pdf->shouldNotReceive('generate');

    makePayService(['deduction' => $deduction, 'pdf' => $pdf])->generateForPeriod($period);

    expect(Payroll::count())->toBe(0);
});

it('regenerateForEmployee conserva el PDF anterior si la nueva generación de PDF falla', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();
    $service = makePayService();

    $payroll = $service->generateForEmployee($employee, $period);
    expect($payroll->fresh()->pdf_path)->toBe('mock/payroll.pdf');

    $pdf = Mockery::mock(PayrollPDFGenerator::class);
    $pdf->shouldReceive('generate')->andThrow(new Exception('Fallo simulado de PDF'));

    makePayService(['pdf' => $pdf])->regenerateForEmployee($payroll->fresh());

    expect($payroll->fresh()->pdf_path)->toBe('mock/payroll.pdf');
});

// ─── eliminación de nómina — reversión de vacaciones ─────────────────────────

it('al eliminar la nómina revierte a unpaid las vacaciones with_payroll pagadas dentro del período', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $payroll = makePayService()->generateForEmployee($employee, $period);

    $vacationInPeriod = Vacation::create([
        'employee_id' => $employee->id,
        'start_date' => $period->start_date,
        'end_date' => $period->start_date->copy()->addDays(2),
        'status' => 'approved',
        'payment_method' => 'with_payroll',
        'payment_amount' => 300_000,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $payroll->delete();

    expect($vacationInPeriod->fresh()->payment_status)->toBe('unpaid')
        ->and($vacationInPeriod->fresh()->paid_at)->toBeNull();
});

it('al eliminar la nómina no toca vacaciones with_payroll pagadas fuera del período', function () {
    $period = makePayPeriod('draft');
    $employee = makePayEmployee();

    $payroll = makePayService()->generateForEmployee($employee, $period);

    $vacationOutsidePeriod = Vacation::create([
        'employee_id' => $employee->id,
        'start_date' => $period->end_date->copy()->addMonth(),
        'end_date' => $period->end_date->copy()->addMonth()->addDays(2),
        'status' => 'approved',
        'payment_method' => 'with_payroll',
        'payment_amount' => 300_000,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $payroll->delete();

    expect($vacationOutsidePeriod->fresh()->payment_status)->toBe('paid')
        ->and($vacationOutsidePeriod->fresh()->paid_at)->not->toBeNull();
});

afterEach(function () {
    Mockery::close();
});
