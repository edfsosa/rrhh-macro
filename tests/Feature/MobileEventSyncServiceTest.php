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
use App\Services\MobileEventSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeMobileSyncEmployee(): Employee
{
    static $ci = 9000000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Mobile {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Mobile {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto Mobile {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Cargo Mobile {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Mobile',
        'last_name' => 'Test',
        'ci' => (string) $n,
        'birth_date' => '1990-05-15',
        'email' => "mobile{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
        'face_descriptor' => array_fill(0, 128, 0.1),
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

it('sincroniza un evento nuevo y lo marca con origen mobile', function () {
    $employee = makeMobileSyncEmployee();
    $clientEventId = (string) Str::uuid();

    $results = app(MobileEventSyncService::class)->syncBatch($employee, [[
        'client_event_id' => $clientEventId,
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-19 08:00:00',
        'location' => ['lat' => -25.28, 'lng' => -57.64],
    ]]);

    expect($results[0]['status'])->toBe('synced');

    $event = AttendanceEvent::where('client_event_id', $clientEventId)->first();
    expect($event)->not->toBeNull()
        ->and($event->source)->toBe('mobile')
        ->and($event->synced_at)->not->toBeNull()
        ->and($event->terminal_id)->toBeNull();
});

/**
 * Regresión: el celular manda recorded_at en UTC (Date.toISOString(), ej.
 * "...T22:30:00.000Z"). Antes del fix, ese valor se persistía tal cual (sin
 * convertir a la timezone de la app), guardando la hora UTC "cruda" — un
 * empleado que marcó a las 19:30 en Paraguay (UTC-3) quedaba con recorded_at
 * en 22:30, 3 horas adelantado.
 */
it('convierte recorded_at de UTC a la timezone de la app antes de persistir', function () {
    // Se fija explícitamente (en vez de asumir el app.timezone ambiente, que en
    // CI es UTC) para que el test ejercite realmente la conversión y no solo
    // coincida "por casualidad" cuando origen y destino son el mismo UTC.
    config(['app.timezone' => 'America/Asuncion']);

    $employee = makeMobileSyncEmployee();
    $clientEventId = (string) Str::uuid();

    app(MobileEventSyncService::class)->syncBatch($employee, [[
        'client_event_id' => $clientEventId,
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-23T22:30:00.000Z',
    ]]);

    // El valor leído de vuelta se re-hidrata con el timezone default de PHP a
    // nivel de proceso (fijado una sola vez al boot vía date_default_timezone_set()),
    // no con el config() recién sobreescrito — por eso solo se verifica el
    // valor de reloj persistido, no el nombre de la timezone del objeto leído.
    $event = AttendanceEvent::where('client_event_id', $clientEventId)->first();
    expect($event->recorded_at->format('Y-m-d H:i:s'))->toBe('2026-08-23 19:30:00');
});

it('es idempotente — reenviar el mismo client_event_id no duplica el evento', function () {
    $employee = makeMobileSyncEmployee();
    $clientEventId = (string) Str::uuid();
    $payload = [[
        'client_event_id' => $clientEventId,
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-19 08:00:00',
    ]];

    $service = app(MobileEventSyncService::class);
    $service->syncBatch($employee, $payload);
    $results = $service->syncBatch($employee, $payload);

    expect($results[0]['status'])->toBe('duplicate')
        ->and(AttendanceEvent::where('client_event_id', $clientEventId)->count())->toBe(1);
});

it('rechaza como conflict un evento cuya secuencia ya no es válida en el servidor y lo registra para revisión', function () {
    $employee = makeMobileSyncEmployee();
    $service = app(MobileEventSyncService::class);

    // El check_in y el check_out ya llegaron al servidor (ej. registrados manualmente
    // mientras el celular estaba offline) — un check_out sin check_in previo también
    // sería una secuencia inválida, por eso se establece el estado con ambos.
    $service->syncBatch($employee, [[
        'client_event_id' => (string) Str::uuid(),
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-19 08:00:00',
    ]]);
    $service->syncBatch($employee, [[
        'client_event_id' => (string) Str::uuid(),
        'event_type' => 'check_out',
        'recorded_at' => '2026-08-19 17:00:00',
    ]]);

    // El celular recién ahora sincroniza un break_start que había capturado offline, antes del check_out.
    $conflictingClientEventId = (string) Str::uuid();
    $results = $service->syncBatch($employee, [[
        'client_event_id' => $conflictingClientEventId,
        'event_type' => 'break_start',
        'recorded_at' => '2026-08-19 12:00:00',
    ]]);

    expect($results[0]['status'])->toBe('conflict')
        ->and($results[0]['conflict_reason'])->toBe('invalid_sequence')
        ->and(AttendanceEvent::where('client_event_id', $conflictingClientEventId)->exists())->toBeFalse();

    $failure = AttendanceMarkFailure::where('failure_type', 'sync_conflict')
        ->where('mode', 'mobile')
        ->where('employee_id', $employee->id)
        ->first();
    expect($failure)->not->toBeNull()
        ->and($failure->branch_id)->toBe($employee->branch_id)
        ->and($failure->attempted_event_type)->toBe('break_start')
        ->and($failure->canBeResolved())->toBeTrue();
});

it('aprobar un conflicto mobile reconstruye el evento con source mobile', function () {
    $employee = makeMobileSyncEmployee();
    $service = app(MobileEventSyncService::class);

    // El check_in ya está en el servidor.
    $service->syncBatch($employee, [[
        'client_event_id' => (string) Str::uuid(),
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-19 08:00:00',
    ]]);

    // El celular sincroniza offline un segundo check_in (ej. capturado dos veces por
    // un reintento de red) — ya no es válido, el servidor lo rechaza como conflicto.
    $service->syncBatch($employee, [[
        'client_event_id' => (string) Str::uuid(),
        'event_type' => 'check_in',
        'recorded_at' => '2026-08-19 08:05:00',
    ]]);

    $failure = AttendanceMarkFailure::where('failure_type', 'sync_conflict')
        ->where('mode', 'mobile')
        ->where('employee_id', $employee->id)
        ->first();

    // El admin revisa el conflicto y determina que en realidad correspondía un break_start
    // (sigue siendo válido contra el estado actual: último evento = check_in).

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin-mobile@test.com', 'password' => bcrypt('secret'),
    ]);

    $result = $failure->approve($admin->id, 'break_start');

    expect($result['success'])->toBeTrue()
        ->and($result['event']->source)->toBe('mobile')
        ->and($result['event']->synced_at)->not->toBeNull();
});
