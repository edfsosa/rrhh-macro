<?php

use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Terminal;
use App\Models\User;
use App\Notifications\MobileDeviceLinkedNotification;
use App\Notifications\MobileDeviceRelinkedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeLinkableEmployee(array $overrides = []): Employee
{
    static $ci = 9500000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Link {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Link {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto Link {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Cargo Link {$n}", 'department_id' => $department->id]);

    $employee = Employee::create(array_merge([
        'first_name' => 'Link',
        'last_name' => 'Test',
        'ci' => (string) $n,
        'birth_date' => '1990-05-15',
        'email' => "link{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
        'face_descriptor' => array_fill(0, 128, 0.1),
    ], $overrides));

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

// ─── Vinculación (/vincular-celular) ───────────────────────────────────────

it('vincula el celular con CI y fecha de nacimiento correctos y emite un token', function () {
    $employee = makeLinkableEmployee();

    $response = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    expect($response->json('token'))->not->toBeNull()
        ->and($response->json('employee.id'))->toBe($employee->id)
        ->and($response->json('employee.face_descriptor'))->toHaveCount(128);

    expect($employee->fresh()->hasMobileLinked())->toBeTrue();
});

it('rechaza CI o fecha de nacimiento incorrectos con un mensaje genérico', function () {
    $employee = makeLinkableEmployee();

    $response = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '2000-01-01',
    ]);

    $response->assertStatus(422)->assertJson(['ok' => false]);
    expect($employee->fresh()->hasMobileLinked())->toBeFalse();
});

it('rechaza la vinculación de un empleado inactivo', function () {
    $employee = makeLinkableEmployee(['status' => 'inactive']);

    $response = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ]);

    $response->assertStatus(422);
});

it('rechaza la vinculación de un empleado sin descriptor facial', function () {
    $employee = makeLinkableEmployee(['face_descriptor' => null]);

    $response = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ]);

    $response->assertStatus(422);
});

it('vincular un celular nuevo revoca el token anterior — un solo dispositivo a la vez', function () {
    $employee = makeLinkableEmployee();

    $first = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ])->json('token');

    $second = $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ])->json('token');

    expect($first)->not->toBe($second);

    // El token viejo ya no autentica.
    $this->withHeader('Authorization', "Bearer {$first}")
        ->getJson('/api/v1/mobile/status')
        ->assertStatus(401);

    // El nuevo sí.
    $this->withHeader('Authorization', "Bearer {$second}")
        ->getJson('/api/v1/mobile/status')
        ->assertOk();
});

// ─── API /api/v1/mobile/* ──────────────────────────────────────────────────

it('el heartbeat devuelve la config vigente y el descriptor facial del empleado', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);

    $response = $this->postJson('/api/v1/mobile/heartbeat');

    $response->assertOk()->assertJson(['ok' => true]);
    expect($response->json('employee.id'))->toBe($employee->id)
        ->and($response->json('config.face_threshold'))->not->toBeNull();
});

it('el heartbeat revoca el token y responde 403 si el empleado ya no está activo', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);
    $employee->update(['status' => 'inactive']);

    $response = $this->postJson('/api/v1/mobile/heartbeat');

    $response->assertStatus(403);
    expect($employee->tokens()->count())->toBe(0);
});

it('status devuelve el último evento y los eventos permitidos para el propio empleado', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);

    $response = $this->getJson('/api/v1/mobile/status');

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'last_event' => null,
            'allowed_events' => ['check_in'],
        ]);
});

it('events/sync crea el evento con origen mobile para el empleado autenticado', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);
    $clientEventId = (string) Str::uuid();

    $response = $this->postJson('/api/v1/mobile/events/sync', [
        'events' => [[
            'client_event_id' => $clientEventId,
            'event_type' => 'check_in',
            'recorded_at' => now()->toDateTimeString(),
            'location' => ['lat' => -25.28, 'lng' => -57.64],
        ]],
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    expect($response->json('results.0.status'))->toBe('synced');

    $event = AttendanceEvent::where('client_event_id', $clientEventId)->first();
    expect($event->source)->toBe('mobile');
});

it('events/sync revoca el token y responde 403 si el empleado ya no está activo', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);
    $employee->update(['status' => 'inactive']);

    $response = $this->postJson('/api/v1/mobile/events/sync', [
        'events' => [[
            'client_event_id' => (string) Str::uuid(),
            'event_type' => 'check_in',
            'recorded_at' => now()->toDateTimeString(),
        ]],
    ]);

    $response->assertStatus(403);
    expect($employee->tokens()->count())->toBe(0);
});

it('un token de terminal no puede usar las rutas móviles (ability distinta)', function () {
    $employee = makeLinkableEmployee();
    $terminal = Terminal::create([
        'name' => 'Kiosko Test', 'branch_id' => $employee->branch_id,
    ]);
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $this->postJson('/api/v1/mobile/heartbeat')->assertStatus(403);
});

// ─── Hardening (Fase 4) ─────────────────────────────────────────────────────

it('notifica a los admins (sin advertencia) en la primera vinculación', function () {
    Notification::fake();
    $admin = User::create(['name' => 'Admin', 'email' => 'admin-first@test.com', 'password' => bcrypt('secret')]);

    $employee = makeLinkableEmployee();

    $this->postJson('/vincular-celular', [
        'ci' => $employee->ci,
        'birth_date' => '1990-05-15',
    ])->assertOk();

    Notification::assertSentTo(
        $admin,
        MobileDeviceLinkedNotification::class,
        fn ($notification) => $notification->employee->is($employee)
    );
    Notification::assertNotSentTo($admin, MobileDeviceRelinkedNotification::class);
});

it('notifica a todos los admins cuando un celular ya vinculado se re-vincula', function () {
    $employee = makeLinkableEmployee();
    $admin1 = User::create(['name' => 'Admin 1', 'email' => 'admin-relink1@test.com', 'password' => bcrypt('secret')]);
    $admin2 = User::create(['name' => 'Admin 2', 'email' => 'admin-relink2@test.com', 'password' => bcrypt('secret')]);

    // Primera vinculación — sin notificación (no hay nada previo que "reemplazar").
    $this->postJson('/vincular-celular', ['ci' => $employee->ci, 'birth_date' => '1990-05-15'])->assertOk();

    Notification::fake();

    // Segunda vinculación del mismo empleado — el dispositivo anterior existía.
    $this->postJson('/vincular-celular', ['ci' => $employee->ci, 'birth_date' => '1990-05-15'])->assertOk();

    Notification::assertSentTo(
        [$admin1, $admin2],
        MobileDeviceRelinkedNotification::class,
        fn ($notification) => $notification->employee->is($employee)
    );
});

it('el heartbeat actualiza mobile_last_heartbeat_at', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);

    expect($employee->mobile_last_heartbeat_at)->toBeNull();

    $this->postJson('/api/v1/mobile/heartbeat')->assertOk();

    expect($employee->fresh()->mobile_last_heartbeat_at)->not->toBeNull();
});

it('revocar el celular limpia mobile_last_heartbeat_at además de mobile_linked_at', function () {
    $employee = makeLinkableEmployee();
    Sanctum::actingAs($employee, [Employee::MOBILE_SYNC_ABILITY]);
    $this->postJson('/api/v1/mobile/heartbeat')->assertOk();
    expect($employee->fresh()->mobile_last_heartbeat_at)->not->toBeNull();

    $employee->fresh()->revokeMobileToken();

    $fresh = $employee->fresh();
    expect($fresh->mobile_linked_at)->toBeNull()
        ->and($fresh->mobile_last_heartbeat_at)->toBeNull();
});

it('el POST de vincular-celular tiene throttling más estricto que el GET', function () {
    $employee = makeLinkableEmployee();

    // 5 intentos permitidos por minuto (credenciales incorrectas a propósito, no importa el resultado).
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/vincular-celular', ['ci' => $employee->ci, 'birth_date' => '2000-01-01'])
            ->assertStatus(422);
    }

    // El 6º intento en la misma ventana debe ser rechazado por el rate limiter.
    $this->postJson('/vincular-celular', ['ci' => $employee->ci, 'birth_date' => '2000-01-01'])
        ->assertStatus(429);
});
