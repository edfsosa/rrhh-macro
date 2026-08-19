<?php

use App\Models\AttendanceEvent;
use App\Models\AttendanceMarkFailure;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeResolutionEmployee(): Employee
{
    static $ci = 6000000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Res {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Res {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto Res {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Cargo Res {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Res',
        'last_name' => 'Test',
        'ci' => (string) $n,
        'email' => "res{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => now()->subYear(),
        'salary_type' => 'mensual',
        'salary' => 2_550_000,
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

// ─── Tests ──────────────────────────────────────────────────────────────────

it('sin employee_id o attempted_event_type no se puede resolver', function () {
    $failure = AttendanceMarkFailure::record([
        'mode' => 'mobile',
        'failure_type' => 'face_no_match',
        'failure_message' => 'No se pudo identificar el rostro.',
    ]);

    expect($failure->canBeResolved())->toBeFalse();
});

it('con employee_id y attempted_event_type se puede resolver', function () {
    $employee = makeResolutionEmployee();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'attempted_event_type' => 'check_in',
        'failure_message' => 'Secuencia inválida.',
    ]);

    expect($failure->canBeResolved())->toBeTrue();
});

it('approve() crea el AttendanceEvent usando el recorded_at del metadata', function () {
    $admin = User::factory()->create();
    $employee = makeResolutionEmployee();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'attempted_event_type' => 'check_in',
        'failure_message' => 'Secuencia inválida.',
        'metadata' => ['recorded_at' => '2026-08-19 08:00:00'],
    ]);

    $result = $failure->approve($admin->id);

    expect($result['success'])->toBeTrue()
        ->and($result['event']->event_type)->toBe('check_in')
        ->and($result['event']->recorded_at->format('Y-m-d H:i'))->toBe('2026-08-19 08:00');

    $failure->refresh();
    expect($failure->isApproved())->toBeTrue()
        ->and($failure->resolved_by_id)->toBe($admin->id)
        ->and($failure->resolved_event_id)->toBe($result['event']->id);
});

it('approve() revalida la secuencia contra el estado actual, no el de cuando se registró el fallo', function () {
    $admin = User::factory()->create();
    $employee = makeResolutionEmployee();

    // El empleado nunca tuvo un check_in — break_start no es una secuencia válida.
    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'attempted_event_type' => 'break_start',
        'failure_message' => 'Secuencia inválida.',
        'metadata' => ['recorded_at' => '2026-08-19 12:00:00'],
    ]);

    $result = $failure->approve($admin->id);

    expect($result['success'])->toBeFalse();
    expect($failure->fresh()->isPending())->toBeTrue();
});

it('approve() permite editar el tipo de evento y la hora antes de reinsertar', function () {
    $admin = User::factory()->create();
    $employee = makeResolutionEmployee();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'attempted_event_type' => 'break_start',
        'failure_message' => 'Secuencia inválida.',
    ]);

    $result = $failure->approve($admin->id, 'check_in', now()->setTime(8, 0), 'Ajustado: en realidad fue entrada');

    expect($result['success'])->toBeTrue()
        ->and($result['event']->event_type)->toBe('check_in');
    expect($failure->fresh()->resolution_notes)->toBe('Ajustado: en realidad fue entrada');
});

it('dismiss() marca el fallo como revisado sin crear ningún evento', function () {
    $admin = User::factory()->create();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'employee_not_found',
        'failure_message' => 'Empleado no encontrado.',
    ]);

    $result = $failure->dismiss($admin->id, 'Ya se cargó manualmente.');

    expect($result['success'])->toBeTrue();
    expect($failure->fresh()->isDismissed())->toBeTrue();
    expect(AttendanceEvent::count())->toBe(0);
});

it('no se puede aprobar ni descartar un fallo que ya fue revisado', function () {
    $admin = User::factory()->create();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'employee_not_found',
        'failure_message' => 'Empleado no encontrado.',
    ]);
    $failure->dismiss($admin->id);

    $approveResult = $failure->approve($admin->id);
    $dismissResult = $failure->dismiss($admin->id);

    expect($approveResult['success'])->toBeFalse()
        ->and($dismissResult['success'])->toBeFalse();
});
