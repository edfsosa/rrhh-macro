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
 * El celular prefiere esta consulta en línea; si falla, cae a una
 * resolución local equivalente (ver mobile-offline/queue.js, igual que
 * hace el kiosko desde la Fase 4).
 */
class MobileStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $today = now(config('app.timezone'))->toDateString();

        $day = AttendanceDay::where('employee_id', $employee->id)->where('date', $today)->first();
        $last = $day
            ? AttendanceEvent::where('attendance_day_id', $day->id)->latest('recorded_at')->first()
            : null;

        return response()->json([
            'ok' => true,
            'last_event' => $last?->event_type,
            'last_event_time' => $last?->recorded_at?->format('H:i'),
            'allowed_events' => AttendanceEvent::allowedNextEventTypes($last?->event_type),
        ]);
    }
}
