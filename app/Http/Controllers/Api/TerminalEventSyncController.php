<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use App\Services\AttendanceEventSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Recepción en lote de eventos de marcación de un terminal — usado tanto en
 * línea (lote de 1) como para volcar la cola acumulada tras un período
 * offline. Cada evento trae un `client_event_id` (UUID generado en el
 * cliente al capturar) para deduplicar reintentos de forma segura.
 * Autenticado vía Sanctum (ability `terminal:sync`).
 */
class TerminalEventSyncController extends Controller
{
    public function __construct(private readonly AttendanceEventSyncService $syncService) {}

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'events' => ['required', 'array', 'min:1', 'max:200'],
                'events.*.client_event_id' => ['required', 'string', 'size:36'],
                'events.*.employee_id' => ['required', 'integer'],
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

        /** @var Terminal $terminal */
        $terminal = $request->user();

        $results = $this->syncService->syncBatch($terminal, $data['events']);

        $terminal->update(['last_event_sync_at' => now()]);

        return response()->json([
            'ok' => true,
            'results' => $results,
        ]);
    }
}
