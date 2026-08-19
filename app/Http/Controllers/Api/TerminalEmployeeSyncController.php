<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
