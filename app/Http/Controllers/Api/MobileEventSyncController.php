<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\MobileEventSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Recepción en lote de eventos de marcación del celular personal de un
 * empleado — usado tanto en línea (lote de 1) como para volcar la cola
 * acumulada tras un período offline. A diferencia del equivalente de
 * terminal, no recibe `employee_id` por evento (el empleado es implícito:
 * el dueño del token Sanctum). Autenticado vía Sanctum (ability `mobile:sync`).
 */
class MobileEventSyncController extends Controller
{
    public function __construct(private readonly MobileEventSyncService $syncService) {}

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'events' => ['required', 'array', 'min:1', 'max:200'],
                'events.*.client_event_id' => ['required', 'string', 'size:36'],
                'events.*.event_type' => ['required', 'string', 'in:check_in,break_start,break_end,check_out'],
                'events.*.recorded_at' => ['required', 'date'],
                'events.*.location' => ['nullable', 'array'],
                'events.*.location.lat' => ['required_with:events.*.location', 'numeric', 'between:-90,90'],
                'events.*.location.lng' => ['required_with:events.*.location', 'numeric', 'between:-180,180'],
            ], [
                'events.*.client_event_id.size' => 'client_event_id debe ser un UUID (36 caracteres).',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        /** @var Employee $employee */
        $employee = $request->user();

        if ($employee->status !== 'active') {
            $employee->revokeMobileToken();

            return response()->json([
                'ok' => false,
                'message' => 'Tu acceso a la marcación por celular fue desactivado. Consultá con RRHH.',
            ], 403);
        }

        $results = $this->syncService->syncBatch($employee, $data['events']);

        return response()->json([
            'ok' => true,
            'results' => $results,
        ]);
    }
}
