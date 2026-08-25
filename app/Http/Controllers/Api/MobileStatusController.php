<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado de marcación del día en curso para el propio empleado autenticado
 * (a diferencia de `TerminalEmployeeSyncController::status()`, que recibe el
 * `employee_id` por ruta porque un terminal consulta por cualquiera de sus
 * empleados — acá el empleado es implícito, siempre `$request->user()`).
 * El dispositivo prefiere esta consulta en línea; si falla, cae a una
 * resolución local equivalente (ver mobile-offline/queue.js, igual que
 * hace el terminal desde la Fase 4).
 *
 * `today_events` (lista completa del día, no solo el último) alimenta la
 * pantalla "Mis marcaciones" en /marcar — a diferencia del resto de esta
 * respuesta, no tiene resolución local offline equivalente.
 */
class MobileStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $today = now(config('app.timezone'))->toDateString();

        $day = AttendanceDay::where('employee_id', $employee->id)->where('date', $today)->first();
        $events = $day
            ? AttendanceEvent::where('attendance_day_id', $day->id)->orderBy('recorded_at')->get(['event_type', 'recorded_at'])
            : collect();
        $last = $events->last();

        return response()->json([
            'ok' => true,
            'last_event' => $last?->event_type,
            'last_event_time' => $last?->recorded_at?->format('H:i'),
            'allowed_events' => AttendanceEvent::allowedNextEventTypes($last?->event_type),
            'today_events' => $events->map(fn (AttendanceEvent $event): array => [
                'event_type' => $event->event_type,
                'event_type_label' => AttendanceEvent::getEventTypeLabel($event->event_type),
                'time' => $event->recorded_at->format('H:i'),
            ])->values(),
        ]);
    }
}
