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
 * reconocimiento facial, así el terminal no depende de un sync completo de
 * empleados para tener el umbral actualizado. Autenticado vía Sanctum
 * (ability `terminal:sync`).
 *
 * `last_heartbeat_at` (a diferencia de `last_seen_at`, que también se
 * actualiza con cada carga de página vía sesión) solo se actualiza acá —
 * es la señal que usa `Terminal::connectivity_status` para el badge de
 * conectividad en Filament (ver TerminalResource).
 *
 * También recibe opcionalmente `pending_events`/`conflict_events` — el
 * tamaño de la cola offline del terminal (`outbound_events` en IndexedDB, ver
 * terminal-offline/queue.js) al momento del heartbeat. Sin esto, un
 * terminal con la cola atascada (ej. eventos en conflicto que requieren
 * revisión) se ve "en línea" igual que uno sano, porque el heartbeat en sí
 * sigue llegando — ver `Terminal::sync_queue_status` para el badge en Filament.
 */
class TerminalHeartbeatController extends Controller
{
    public function store(Request $request, GeneralSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'pending_events' => ['nullable', 'integer', 'min:0'],
            'conflict_events' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var Terminal $terminal */
        $terminal = $request->user();

        $terminal->update([
            'last_seen_at' => now(),
            'last_heartbeat_at' => now(),
            'last_pending_events_count' => $data['pending_events'] ?? null,
            'last_conflict_events_count' => $data['conflict_events'] ?? null,
        ]);

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
