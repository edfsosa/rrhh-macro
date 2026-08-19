<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Terminal;
use App\Services\EmployeeDescriptorSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Sincronización incremental de empleados/descriptores faciales para la
 * caché offline del kiosko. Autenticado vía Sanctum (ability `terminal:sync`).
 */
class TerminalEmployeeSyncController extends Controller
{
    public function __construct(private readonly EmployeeDescriptorSyncService $syncService) {}

    /**
     * GET /api/v1/terminal/employees/sync?since=<ISO8601>
     *
     * Sin `since`, retorna la carga completa (primera sincronización del kiosko).
     * Con `since`, retorna solo los empleados modificados después de esa fecha,
     * más los `tombstones` (empleados que dejaron de calificar) a eliminar de la caché.
     */
    public function index(Request $request): JsonResponse
    {
        $since = null;

        if ($request->filled('since')) {
            try {
                $since = Carbon::parse($request->query('since'));
            } catch (Throwable) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Parámetro since inválido — debe ser una fecha en formato ISO 8601.',
                ], 422);
            }
        }

        /** @var Terminal $terminal */
        $terminal = $request->user();

        $delta = $this->syncService->deltaSince($terminal, $since);

        return response()->json(array_merge(['ok' => true], $delta));
    }

    /**
     * GET /api/v1/terminal/employees/{employee}/status
     *
     * Estado de marcación del día en curso para un empleado ya identificado
     * localmente por el kiosko (matching client-side, ver terminal-offline/matcher.js).
     * El kiosko todavía depende de esta consulta en línea para saber qué
     * eventos son válidos a continuación — la resolución 100% offline de este
     * estado es responsabilidad de una fase posterior (cola de eventos).
     */
    public function status(Request $request, Employee $employee): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();

        if ($employee->status !== 'active' || $employee->branch_id !== $terminal->branch_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Empleado no disponible para este terminal.',
            ], 404);
        }

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
