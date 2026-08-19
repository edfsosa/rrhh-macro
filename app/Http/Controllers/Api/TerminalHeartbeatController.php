<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Heartbeat del terminal — se llama periódicamente mientras hay conexión
 * (y desde el botón "Forzar sincronización") para mantener `last_seen_at`/
 * `last_heartbeat_at` vivos y entregar la configuración vigente de
 * reconocimiento facial, así el kiosko no depende de un sync completo de
 * empleados para tener el umbral actualizado. Autenticado vía Sanctum
 * (ability `terminal:sync`).
 *
 * `last_heartbeat_at` (a diferencia de `last_seen_at`, que también se
 * actualiza con cada carga de página vía sesión) solo se actualiza acá —
 * es la señal que usa `Terminal::connectivity_status` para el badge de
 * conectividad en Filament (ver TerminalResource).
 */
class TerminalHeartbeatController extends Controller
{
    public function store(Request $request, GeneralSettings $settings): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();

        $terminal->update(['last_seen_at' => now(), 'last_heartbeat_at' => now()]);

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'config' => [
                'face_threshold' => (float) $settings->face_threshold,
                'face_min_confidence_gap' => (float) $settings->face_min_confidence_gap,
            ],
        ]);
    }
}
