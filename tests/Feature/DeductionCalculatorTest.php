<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\DeductionCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = [
        'monthly_hours' => 240,
        'monthly_hours_nocturno' => 210,
        'monthly_hours_mixto' => 225,
        'daily_hours' => 8,
        'daily_hours_nocturno' => 7,
        'daily_hours_mixto' => 7.5,
        'days_per_month' => 30,
        'overtime_multiplier_diurno' => 1.5,
        'overtime_multiplier_nocturno' => 2.6,
        'overtime_multiplier_holiday' => 2.0,
        'overtime_multiplier_nocturno_holiday' => 2.6,
        'overtime_max_daily_hours' => 3,
        'ips_employee_rate' => 9,
        'indemnizacion_days_per_year' => 15,
        'vacation_min_consecutive_days' => 6,
        'vacation_min_years_service' => 1,
        'vacation_business_days' => [1, 2, 3, 4, 5, 6],
        'ips_deduction_code' => 'IPS001',
        'min_salary_monthly' => 2_550_328,
        'min_salary_daily_jornal' => 87_950,
        'family_bonus_percentage' => 5.0,
    ];

    foreach ($settings as $name => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'payroll', 'name' => $name],
            ['payload' => json_encode($value)]
        );
    }
});

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Crea un empleado mensual o jornalero con contrato activo para tests de DeductionCalculator.
 *
 * @param  string  $salaryType  'mensual' | 'jornal'
 * @param  int  $salary  Monto del salario
 */
function makeDedEmployee(string $salaryType = 'mensual', int $salary = 2_550_000): Employee
{
    static $ci = 8000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpD {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucD {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepD {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosD {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Ded',
        'ci' => (string) $n,
        'email' => "ded{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => $salaryType,
        'salary' => $salary,
        'payroll_type' => 'monthly',
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Crea un PayrollPeriod mensual para el mes indicado de 2026.
 */
function makeDedPeriod(int $month = 3): PayrollPeriod
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

/**
 * Crea una deducción de monto fijo.
 */
function makeFixedDeduction(string $suffix = '', float $amount = 200_000, string $type = 'legal'): Deduction
{
    static $code = 1;

    return Deduction::create([
        'name' => "Descuento fijo {$suffix}",
        'code' => 'DF'.($code++),
        'type' => $type,
        'calculation' => 'fixed',
        'amount' => $amount,
        'is_active' => true,
    ]);
}

/**
 * Crea una deducción por porcentaje.
 */
function makePercentDeduction(string $suffix = '', float $percent = 9.00, string $type = 'legal'): Deduction
{
    static $code = 1;

    return Deduction::create([
        'name' => "Descuento % {$suffix}",
        'code' => 'DP'.($code++),
        'type' => $type,
        'calculation' => 'percentage',
        'percent' => $percent,
        'is_active' => true,
    ]);
}

/**
 * Asigna una deducción a un empleado en el rango de fechas dado.
 */
function assignDeduction(
    Employee $employee,
    Deduction $deduction,
    string $startDate = '2026-01-01',
    ?string $endDate = null,
    ?float $customAmount = null,
): void {
    EmployeeDeduction::create([
        'employee_id' => $employee->id,
        'deduction_id' => $deduction->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'custom_amount' => $customAmount,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna total 0 e items vacíos si el empleado no tiene deducciones asignadas', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod();

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});

it('calcula correctamente una deducción de monto fijo', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod();
    $deduction = makeFixedDeduction(amount: 200_000);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(200_000.0)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0])->toMatchArray([
            'description' => $deduction->name,
            'amount' => 200_000.0,
        ]);
});

it('calcula deducción por porcentaje usando base_salary para empleado mensual', function () {
    $salary = 2_550_000;
    $percent = 9.00;
    $expected = round($salary * $percent / 100, 2); // 229500.0

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();
    $deduction = makePercentDeduction(percent: $percent);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('calcula deducción por porcentaje usando daily_rate para jornalero', function () {
    $dailyRate = 120_000;
    $percent = 9.00;
    $expected = round($dailyRate * $percent / 100, 2); // 10800.0

    $employee = makeDedEmployee('jornal', $dailyRate);
    $period = makeDedPeriod();
    $deduction = makePercentDeduction(percent: $percent);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('usa custom_amount cuando está definido, ignorando el cálculo normal', function () {
    $customAmount = 150_000.0;

    $employee = makeDedEmployee('mensual', 2_550_000);
    $period = makeDedPeriod();
    $deduction = makePercentDeduction(percent: 9.00);

    assignDeduction($employee, $deduction, customAmount: $customAmount);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($customAmount)
        ->and((float) $result['items'][0]['amount'])->toBe($customAmount);
});

it('retorna amount=0 para deducción porcentual si el empleado no tiene salario base', function () {
    // Empleado jornalero con daily_rate = 0
    $employee = makeDedEmployee('jornal', 0);
    $period = makeDedPeriod();
    $deduction = makePercentDeduction(percent: 9.00);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(0.0)
        ->and($result['items'])->toHaveCount(1)
        ->and((float) $result['items'][0]['amount'])->toBe(0.0);
});

it('suma correctamente múltiples deducciones asignadas', function () {
    $employee = makeDedEmployee('mensual', 2_000_000);
    $period = makeDedPeriod();

    $ded1 = makeFixedDeduction(amount: 100_000);
    $ded2 = makeFixedDeduction(amount: 150_000);

    assignDeduction($employee, $ded1);
    assignDeduction($employee, $ded2);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(250_000.0)
        ->and($result['items'])->toHaveCount(2);
});

it('excluye deducciones cuya end_date es anterior al inicio del período', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // end_date = 2026-02-28: terminó antes de que empiece marzo
    assignDeduction($employee, $deduction, startDate: '2026-01-01', endDate: '2026-02-28');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye deducciones cuya start_date es posterior al fin del período', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // start_date = 2026-04-01: inicia después de que termine marzo
    assignDeduction($employee, $deduction, startDate: '2026-04-01');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});

it('incluye deducción cuya vigencia se superpone parcialmente con el período', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // start_date = 2026-03-15, end_date = 2026-04-15: activa durante parte de marzo
    assignDeduction($employee, $deduction, startDate: '2026-03-15', endDate: '2026-04-15');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(200_000.0)
        ->and($result['items'])->toHaveCount(1);
});

it('incluye deducción sin fecha de fin (vigencia indefinida)', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod();
    $deduction = makeFixedDeduction(amount: 75_000);

    assignDeduction($employee, $deduction, endDate: null);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(75_000.0);
});

// ─── Base IPS correcta ───────────────────────────────────────────────────────

it('calcula IPS sobre ips_base (salario + ips_perceptions) cuando se identifica por código', function () {
    // base_salary = 2,550,000 — ips_perceptions = 300,000 — ips_base = 2,850,000
    // IPS correcto: 9% de 2,850,000 = 256,500
    // IPS incorrecto (bug): 9% de 2,550,000 = 229,500
    $salary = 2_550_000;
    $ipsBase = (float) ($salary + 300_000);

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $ipsDeduction = Deduction::create([
        'name' => 'Aporte Obrero IPS',
        'code' => 'IPS001', // coincide con ips_deduction_code del setting
        'calculation' => 'percentage',
        'percent' => 9.00,
        'is_active' => true,
    ]);
    assignDeduction($employee, $ipsDeduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    $expected = round($ipsBase * 9 / 100, 2); // 256,500.0

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('no trunca los decimales de ips_base al calcular el IPS (extras/descanso fraccionarios)', function () {
    // ips_base incluye horas extra / descanso semanal fraccionarios (ej. jornaleros)
    $salary = 2_550_000;
    $ipsBase = $salary + 73_291.67; // fracción típica de RestDayCalculator

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $ipsDeduction = Deduction::create([
        'name' => 'Aporte Obrero IPS',
        'code' => 'IPS001',
        'calculation' => 'percentage',
        'percent' => 9.00,
        'is_active' => true,
    ]);
    assignDeduction($employee, $ipsDeduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    // Si $ipsBase se truncara a entero antes de multiplicar (bug original),
    // el resultado difeririría del esperado por el redondeo de la fracción perdida.
    $expected = round($ipsBase * 9 / 100, 2);

    expect((float) $result['total'])->toBe($expected)
        ->and($expected)->not->toBe(round((int) round($ipsBase) * 9 / 100, 2));
});

it('usa base_salary para deducción porcentual no-IPS aunque se pase ips_base', function () {
    // Otra deducción porcentual (no IPS) debe seguir usando base_salary
    $salary = 2_000_000;
    $ipsBase = (float) ($salary + 500_000);

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $otherDeduction = makePercentDeduction(percent: 10.0); // code != 'IPS001'
    assignDeduction($employee, $otherDeduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    $expected = round($salary * 10 / 100, 2); // 200,000 — no 250,000

    expect((float) $result['total'])->toBe($expected);
});

// ─── deduction_type en ítems ─────────────────────────────────────────────────

it('cada ítem incluye deduction_type del modelo Deduction', function () {
    $employee = makeDedEmployee();
    $period = makeDedPeriod();

    $legal = makeFixedDeduction('legal', 100_000, 'legal');
    $judicial = makeFixedDeduction('judicial', 150_000, 'judicial');
    $voluntary = makeFixedDeduction('voluntary', 200_000, 'voluntary');

    assignDeduction($employee, $legal);
    assignDeduction($employee, $judicial);
    assignDeduction($employee, $voluntary);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    $byType = collect($result['items'])->keyBy('deduction_type');

    expect($byType->has('legal'))->toBeTrue()
        ->and($byType->has('judicial'))->toBeTrue()
        ->and($byType->has('voluntary'))->toBeTrue()
        ->and((float) $byType['legal']['amount'])->toBe(100_000.0)
        ->and((float) $byType['judicial']['amount'])->toBe(150_000.0)
        ->and((float) $byType['voluntary']['amount'])->toBe(200_000.0);
});

it('sin ips_base (null), IPS usa base_salary como antes', function () {
    // Compatibilidad hacia atrás: si no se pasa ips_base, IPS usa base_salary
    $salary = 2_550_000;

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $ipsDeduction = Deduction::create([
        'name' => 'Aporte Obrero IPS',
        'code' => 'IPS001',
        'calculation' => 'percentage',
        'percent' => 9.00,
        'is_active' => true,
    ]);
    assignDeduction($employee, $ipsDeduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period); // sin ips_base

    $expected = round($salary * 9 / 100, 2); // 229,500.0

    expect((float) $result['total'])->toBe($expected);
});

// ─── Tope judicial Art. 245 CLT ──────────────────────────────────────────────

function makeEmbargo(float $amount): Deduction
{
    static $code = 1;

    return Deduction::create([
        'name' => "Embargo {$code}",
        'code' => 'EMB'.($code++),
        'type' => 'judicial',
        'apply_judicial_limit' => true,
        'calculation' => 'fixed',
        'amount' => $amount,
        'is_active' => true,
    ]);
}

it('embargo se limita al 25% del excedente sobre el salario mínimo', function () {
    // ipsBase=4,000,000 | min=2,550,328 | excedente=1,449,672 | tope=362,418
    $salary = 4_000_000;
    $ipsBase = (float) $salary;

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $embargo = makeEmbargo(600_000); // supera el tope
    assignDeduction($employee, $embargo);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    $expected = round(($salary - 2_550_328) * 0.25, 2); // 362,418.0

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('embargo es 0 cuando el salario no supera el mínimo', function () {
    $salary = 2_550_000; // por debajo del mínimo (2,550,328)
    $ipsBase = (float) $salary;

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $embargo = makeEmbargo(200_000);
    assignDeduction($employee, $embargo);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    expect((float) $result['total'])->toBe(0.0)
        ->and((float) $result['items'][0]['amount'])->toBe(0.0);
});

it('primer embargo cobra, segundo recibe el remanente del tope', function () {
    // tope=362,418 | embargo1=300,000 | embargo2=200,000 → embargo2 recibe 62,418
    $salary = 4_000_000;
    $ipsBase = (float) $salary;

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $embargo1 = makeEmbargo(300_000);
    $embargo2 = makeEmbargo(200_000);

    assignDeduction($employee, $embargo1, startDate: '2025-01-01');
    assignDeduction($employee, $embargo2, startDate: '2025-06-01');

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    $cap = round((4_000_000 - 2_550_328) * 0.25, 2);
    $item1 = (float) collect($result['items'])->firstWhere('description', $embargo1->name)['amount'];
    $item2 = (float) collect($result['items'])->firstWhere('description', $embargo2->name)['amount'];

    expect($item1)->toBe(300_000.0)
        ->and($item2)->toBe(round($cap - 300_000, 2))
        ->and((float) $result['total'])->toBe($cap);
});

it('cuando el tope está agotado el embargo posterior queda en cola con amount=0', function () {
    $salary = 4_000_000;
    $ipsBase = (float) $salary;
    $cap = round((4_000_000 - 2_550_328) * 0.25, 2);

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $embargo1 = makeEmbargo($cap + 100_000); // consume todo el tope
    $embargo2 = makeEmbargo(50_000);

    assignDeduction($employee, $embargo1, startDate: '2025-01-01');
    assignDeduction($employee, $embargo2, startDate: '2025-06-01');

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    $item1 = (float) collect($result['items'])->firstWhere('description', $embargo1->name)['amount'];
    $item2 = (float) collect($result['items'])->firstWhere('description', $embargo2->name)['amount'];

    expect($item1)->toBe($cap)
        ->and($item2)->toBe(0.0);
});

it('alimentaria (apply_judicial_limit=false) no se limita aunque supere el tope', function () {
    $salary = 3_000_000;
    $ipsBase = (float) $salary;

    $employee = makeDedEmployee('mensual', $salary);
    $period = makeDedPeriod();

    $alimentaria = Deduction::create([
        'name' => 'Prestación Alimentaria',
        'code' => 'ALIM01',
        'type' => 'judicial',
        'apply_judicial_limit' => false,
        'calculation' => 'fixed',
        'amount' => 800_000,
        'is_active' => true,
    ]);
    assignDeduction($employee, $alimentaria);

    $result = app(DeductionCalculator::class)->calculate($employee, $period, $ipsBase);

    expect((float) $result['total'])->toBe(800_000.0);
});
