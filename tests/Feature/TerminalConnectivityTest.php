<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Terminal;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeConnectivityTerminal(): Terminal
{
    static $n = 7000000;
    $n++;

    $company = Company::create(['name' => "Empresa Term {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Term {$n}", 'company_id' => $company->id]);

    return Terminal::create(['name' => 'Kiosko Test', 'branch_id' => $branch->id]);
}

// ─── Tests ──────────────────────────────────────────────────────────────────

it('nunca conectado cuando last_heartbeat_at es null', function () {
    $terminal = makeConnectivityTerminal();

    expect($terminal->connectivity_status)->toBe('never_connected');
});

it('en línea cuando el último heartbeat está dentro del umbral configurado', function () {
    $settings = app(GeneralSettings::class);
    $settings->terminal_stale_threshold_hours = 2;
    $settings->save();

    $terminal = makeConnectivityTerminal();
    $terminal->update(['last_heartbeat_at' => now()->subHour()]);

    expect($terminal->connectivity_status)->toBe('online');
});

it('desconectado cuando el último heartbeat superó el umbral configurado', function () {
    $settings = app(GeneralSettings::class);
    $settings->terminal_stale_threshold_hours = 2;
    $settings->save();

    $terminal = makeConnectivityTerminal();
    $terminal->update(['last_heartbeat_at' => now()->subHours(3)]);

    expect($terminal->connectivity_status)->toBe('stale');
});

it('el heartbeat de la API actualiza last_seen_at y last_heartbeat_at', function () {
    $terminal = makeConnectivityTerminal();
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $response = $this->postJson('/api/v1/terminal/heartbeat');

    $response->assertOk()->assertJson(['ok' => true]);
    $terminal->refresh();
    expect($terminal->last_heartbeat_at)->not->toBeNull()
        ->and($terminal->last_seen_at)->not->toBeNull();
});

it('el sync de empleados actualiza last_employee_sync_at', function () {
    $terminal = makeConnectivityTerminal();
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $response = $this->getJson('/api/v1/terminal/employees/sync');

    $response->assertOk()->assertJson(['ok' => true]);
    $terminal->refresh();
    expect($terminal->last_employee_sync_at)->not->toBeNull();
});

it('el sync de eventos actualiza last_event_sync_at', function () {
    $terminal = makeConnectivityTerminal();
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $response = $this->postJson('/api/v1/terminal/events/sync', [
        'events' => [[
            'client_event_id' => (string) Str::uuid(),
            'employee_id' => 999999,
            'event_type' => 'check_in',
            'recorded_at' => now()->toDateTimeString(),
        ]],
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    $terminal->refresh();
    expect($terminal->last_event_sync_at)->not->toBeNull();
});

it('sin pendientes ni conflictos reportados, la cola de sync es ok', function () {
    $terminal = makeConnectivityTerminal();

    expect($terminal->sync_queue_status)->toBe('ok');
});

it('con pendientes y sin conflictos, la cola de sync es pending', function () {
    $terminal = makeConnectivityTerminal();
    $terminal->update(['last_pending_events_count' => 3, 'last_conflict_events_count' => 0]);

    expect($terminal->fresh()->sync_queue_status)->toBe('pending');
});

it('con al menos un conflicto, la cola de sync es conflict aunque también haya pendientes', function () {
    $terminal = makeConnectivityTerminal();
    $terminal->update(['last_pending_events_count' => 3, 'last_conflict_events_count' => 1]);

    expect($terminal->fresh()->sync_queue_status)->toBe('conflict');
});

it('el heartbeat guarda los contadores de cola que reporta el kiosko', function () {
    $terminal = makeConnectivityTerminal();
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $response = $this->postJson('/api/v1/terminal/heartbeat', [
        'pending_events' => 5,
        'conflict_events' => 2,
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    $terminal->refresh();
    expect($terminal->last_pending_events_count)->toBe(5)
        ->and($terminal->last_conflict_events_count)->toBe(2)
        ->and($terminal->sync_queue_status)->toBe('conflict');
});

it('el heartbeat sin contadores no rompe y deja los contadores en null', function () {
    $terminal = makeConnectivityTerminal();
    Sanctum::actingAs($terminal, [Terminal::SYNC_ABILITY]);

    $response = $this->postJson('/api/v1/terminal/heartbeat');

    $response->assertOk()->assertJson(['ok' => true]);
    $terminal->refresh();
    expect($terminal->last_pending_events_count)->toBeNull()
        ->and($terminal->last_conflict_events_count)->toBeNull();
});
