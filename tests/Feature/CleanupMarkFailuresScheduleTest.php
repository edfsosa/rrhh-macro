<?php

use App\Models\AttendanceMarkFailure;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regresión: la limpieza diaria (routes/console.php, 'cleanup-mark-failures')
 * borraba TODOS los AttendanceMarkFailure de más de 30 días sin importar
 * resolution_status — un fallo 'pending' que nadie llegó a revisar se perdía
 * para siempre junto con la posibilidad de reconstruir esa marcación.
 */
function makeMarkFailure(string $resolutionStatus, Carbon $occurredAt): AttendanceMarkFailure
{
    return AttendanceMarkFailure::create([
        'mode' => 'unknown',
        'failure_type' => 'invalid_event_sequence',
        'failure_message' => 'Secuencia de evento no permitida.',
        'occurred_at' => $occurredAt,
        'resolution_status' => $resolutionStatus,
    ]);
}

/** Ubica y ejecuta el callback registrado como 'cleanup-mark-failures' en routes/console.php. */
function runCleanupMarkFailuresSchedule(): void
{
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($e) => $e instanceof CallbackEvent && $e->description === 'cleanup-mark-failures');

    expect($event)->not->toBeNull('No se encontró el evento programado cleanup-mark-failures — revisar routes/console.php');

    $event->run(app());
}

it('la limpieza diaria borra fallos revisados de más de 30 días, pero conserva los pending', function () {
    $old = now()->subDays(31);
    $recent = now()->subDays(10);

    $oldPending = makeMarkFailure('pending', $old);
    $oldApproved = makeMarkFailure('approved', $old);
    $oldDismissed = makeMarkFailure('dismissed', $old);
    $recentPending = makeMarkFailure('pending', $recent);

    runCleanupMarkFailuresSchedule();

    expect(AttendanceMarkFailure::find($oldPending->id))->not->toBeNull()
        ->and(AttendanceMarkFailure::find($oldApproved->id))->toBeNull()
        ->and(AttendanceMarkFailure::find($oldDismissed->id))->toBeNull()
        ->and(AttendanceMarkFailure::find($recentPending->id))->not->toBeNull();
});
