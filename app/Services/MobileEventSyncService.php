<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\AttendanceMarkFailure;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza en lote los eventos de marcación capturados por el dispositivo
 * personal de UN empleado ya autenticado (a diferencia de
 * `AttendanceEventSyncService`, que sirve a un terminal con N empleados —
 * acá no hace falta `employee_id` por evento ni resolver "a quién
 * pertenece": el dueño del token Sanctum ES el empleado). Misma
 * idempotencia por `client_event_id` y misma re-validación de secuencia
 * contra el estado *actual* del servidor que el flujo de terminal.
 */
class MobileEventSyncService
{
    /**
     * @param  array<int, array{client_event_id: string, event_type: string, recorded_at: string, location?: array|null}>  $events
     * @return array<int, array{client_event_id: string, status: string, event_id?: int, conflict_reason?: string, message?: string}>
     */
    public function syncBatch(Employee $employee, array $events): array
    {
        // Se procesa en orden cronológico de captura, para que varios eventos
        // represados se repliquen en el orden en que realmente ocurrieron.
        $ordered = collect($events)->sortBy('recorded_at')->values();

        return $ordered->map(fn (array $eventData) => $this->syncOne($employee, $eventData))->all();
    }

    /**
     * @param  array{client_event_id: string, event_type: string, recorded_at: string, location?: array|null}  $eventData
     * @return array{client_event_id: string, status: string, event_id?: int, conflict_reason?: string, message?: string}
     */
    private function syncOne(Employee $employee, array $eventData): array
    {
        $clientEventId = $eventData['client_event_id'];

        // Idempotencia: reintentar el mismo lote (ej. la respuesta anterior se perdió
        // en el camino) no debe duplicar el evento ya sincronizado.
        $existing = AttendanceEvent::where('client_event_id', $clientEventId)->first();
        if ($existing) {
            return [
                'client_event_id' => $clientEventId,
                'status' => 'duplicate',
                'event_id' => $existing->id,
            ];
        }

        try {
            // El dispositivo manda recorded_at en UTC (Date.toISOString()) — convertir a la
            // timezone de la app ANTES de persistir, no solo para calcular $date. Sin esto
            // el evento queda guardado con la hora UTC "cruda" (ej. 3 horas adelantado en
            // America/Asuncion), porque el resto de la app asume que recorded_at ya está en
            // hora local.
            $recordedAt = Carbon::parse($eventData['recorded_at'])->timezone(config('app.timezone'));
        } catch (Throwable) {
            return [
                'client_event_id' => $clientEventId,
                'status' => 'rejected',
                'conflict_reason' => 'invalid_timestamp',
                'message' => 'Fecha/hora de marcación inválida.',
            ];
        }

        $date = $recordedAt->toDateString();

        try {
            return DB::transaction(fn () => $this->insertEvent($employee, $eventData, $clientEventId, $recordedAt, $date));
        } catch (UniqueConstraintViolationException) {
            // Carrera entre dos sync concurrentes con el mismo client_event_id — el otro ganó, tratar como duplicado.
            $existing = AttendanceEvent::where('client_event_id', $clientEventId)->first();

            return [
                'client_event_id' => $clientEventId,
                'status' => $existing ? 'duplicate' : 'rejected',
                'event_id' => $existing?->id,
            ];
        }
    }

    /**
     * @param  array{client_event_id: string, event_type: string, recorded_at: string, location?: array|null}  $eventData
     * @return array{client_event_id: string, status: string, event_id?: int, conflict_reason?: string, message?: string}
     */
    private function insertEvent(
        Employee $employee,
        array $eventData,
        string $clientEventId,
        Carbon $recordedAt,
        string $date,
    ): array {
        $day = AttendanceDay::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            ['status' => 'present']
        );

        $last = AttendanceEvent::where('attendance_day_id', $day->id)
            ->lockForUpdate()
            ->latest('recorded_at')
            ->first();

        $allowed = AttendanceEvent::allowedNextEventTypes($last?->event_type);

        if (! in_array($eventData['event_type'], $allowed, true)) {
            Log::warning("Sync de dispositivo — evento '{$eventData['event_type']}' ya no es válido para el empleado {$employee->id} (último registrado: ".($last->event_type ?? 'ninguno').')', [
                'employee_id' => $employee->id,
                'client_event_id' => $clientEventId,
                'attempted_event' => $eventData['event_type'],
                'last_event' => $last?->event_type,
                'allowed_events' => $allowed,
            ]);

            $this->recordSyncFailure(
                $employee,
                'La secuencia de marcación ya no es válida en el servidor al sincronizar (último evento registrado: '.($last->event_type ?? 'ninguno').').',
                [
                    'client_event_id' => $clientEventId,
                    'attempted_event' => $eventData['event_type'],
                    'last_event' => $last?->event_type,
                    'allowed_events' => $allowed,
                    'recorded_at' => $recordedAt->toIso8601String(),
                    'location' => $eventData['location'] ?? null,
                ],
                $eventData['event_type'],
            );

            return [
                'client_event_id' => $clientEventId,
                'status' => 'conflict',
                'conflict_reason' => 'invalid_sequence',
                'message' => 'La secuencia de marcación ya no es válida en el servidor — probablemente otro origen registró un evento mientras el dispositivo estaba offline.',
            ];
        }

        if ($day->status !== 'present') {
            $day->update(['status' => 'present']);
        }

        $event = $day->events()->create([
            'client_event_id' => $clientEventId,
            'event_type' => $eventData['event_type'],
            'recorded_at' => $recordedAt,
            'synced_at' => now(),
            'source' => 'mobile',
            'location' => $eventData['location'] ?? null,
        ]);

        return [
            'client_event_id' => $clientEventId,
            'status' => 'synced',
            'event_id' => $event->id,
        ];
    }

    /**
     * Persiste un intento fallido de sincronización para revisión en Filament
     * (`AttendanceMarkFailureResource` — mismo flujo de aprobar/descartar que
     * ya existe para los conflictos del terminal).
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordSyncFailure(Employee $employee, string $message, array $metadata, string $eventType): void
    {
        try {
            AttendanceMarkFailure::record([
                'mode' => 'mobile',
                'failure_type' => 'sync_conflict',
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'attempted_event_type' => $eventType,
                'failure_message' => $message,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $e) {
            Log::error('No se pudo persistir el fallo de sincronización offline (dispositivo)', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
