<?php

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Terminal;
use App\Services\AttendanceEventSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Regresión: un turno que cruza medianoche (ej. entrada 17:00 → salida 01:00
 * del día siguiente) no podía cerrarse — AttendanceDay se armaba por fecha
 * calendario del momento de marcar, no por la jornada abierta del empleado,
 * así que la salida caía en un AttendanceDay nuevo sin marcaciones previas
 * y la transición 'check_out' no era válida ahí. Ver AttendanceDay::resolveForEvent().
 */
function makeOvernightEmployee(): Employee
{
    static $ci = 8100000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Overnight {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create([
        'name' => "Sucursal Overnight {$n}",
        'company_id' => $company->id,
        'coordinates' => ['lat' => -25.2867, 'lng' => -57.6478],
    ]);
    $department = Department::create(['name' => "Depto Overnight {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Cargo Overnight {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Overnight',
        'last_name' => 'Test',
        'ci' => (string) $n,
        'birth_date' => '1990-01-01',
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

afterEach(function () {
    Carbon::setTestNow();
});

it('permite marcar salida pasada la medianoche de un turno que empezó el día anterior (flujo HTTP)', function () {
    $employee = makeOvernightEmployee();

    Carbon::setTestNow(Carbon::parse('2026-08-27 17:00:00'));
    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_in', 'source' => 'manual'])
        ->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-08-28 01:00:00'));
    // currentStateFor() es lo que identify() usa para decidir qué botones mostrar
    // en el front-end (la identificación facial en sí no aplica en este test).
    $state = AttendanceDay::currentStateFor($employee->id, Carbon::now());
    expect($state['allowed'])->toContain('check_out');

    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_out', 'source' => 'manual'])
        ->assertOk();

    $day = AttendanceDay::where('employee_id', $employee->id)->where('date', '2026-08-27')->first();
    expect($day)->not->toBeNull()
        ->and($day->events()->count())->toBe(2)
        ->and($day->events()->pluck('event_type')->toArray())->toBe(['check_in', 'check_out']);

    // No debe haberse creado una jornada para el 28 a partir de la salida.
    expect(AttendanceDay::where('employee_id', $employee->id)->where('date', '2026-08-28')->exists())->toBeFalse();
});

it('tras cerrar el turno nocturno, permite una nueva entrada más tarde ese mismo día calendario', function () {
    $employee = makeOvernightEmployee();

    Carbon::setTestNow(Carbon::parse('2026-08-27 17:00:00'));
    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_in', 'source' => 'manual'])->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-08-28 01:00:00'));
    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_out', 'source' => 'manual'])->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-08-28 17:00:00'));
    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_in', 'source' => 'manual'])
        ->assertOk();

    $newDay = AttendanceDay::where('employee_id', $employee->id)->where('date', '2026-08-28')->first();
    expect($newDay)->not->toBeNull()
        ->and($newDay->events()->count())->toBe(1)
        ->and($newDay->events()->first()->event_type)->toBe('check_in');
});

it('una jornada abierta de hace 2+ días no se reabre automáticamente', function () {
    $employee = makeOvernightEmployee();

    Carbon::setTestNow(Carbon::parse('2026-08-20 17:00:00'));
    $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_in', 'source' => 'manual'])->assertOk();

    // 3 días después, sin haber marcado nada en el medio.
    Carbon::setTestNow(Carbon::parse('2026-08-23 01:00:00'));
    $response = $this->postJson('/marcar', ['employee_id' => $employee->id, 'event_type' => 'check_out', 'source' => 'manual']);

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('aún no tiene marcaciones hoy');
});

it('AttendanceEventSyncService también respeta la jornada nocturna abierta al sincronizar offline', function () {
    $employee = makeOvernightEmployee();
    $terminal = Terminal::create(['name' => 'Terminal Overnight', 'branch_id' => $employee->branch_id]);
    $service = new AttendanceEventSyncService;

    $checkIn = [
        'client_event_id' => (string) Str::uuid(),
        'employee_id' => $employee->id,
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-27T20:00:00Z', // 17:00 America/Asuncion (UTC-3)
    ];
    $checkOut = [
        'client_event_id' => (string) Str::uuid(),
        'employee_id' => $employee->id,
        'event_type' => 'check_out',
        'recorded_at' => '2026-08-28T04:00:00Z', // 01:00 America/Asuncion del día siguiente
    ];

    $results = $service->syncBatch($terminal, [$checkIn, $checkOut]);

    expect($results[0]['status'])->toBe('synced')
        ->and($results[1]['status'])->toBe('synced');

    $day = AttendanceDay::where('employee_id', $employee->id)->where('date', '2026-08-27')->first();
    expect($day)->not->toBeNull()
        ->and($day->events()->count())->toBe(2);
});
