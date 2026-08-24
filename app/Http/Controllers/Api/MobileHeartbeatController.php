<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Heartbeat del dispositivo personal — a diferencia del heartbeat de terminal
 * (Terminal), acá el propio empleado es el `tokenable`, así que además de
 * la config vigente de reconocimiento facial también devuelve el
 * `face_descriptor` actualizado del empleado: no existe un endpoint
 * separado de "sync de empleados" porque el dispositivo solo necesita cachear
 * un único descriptor, el suyo. Esto además propaga automáticamente una
 * re-inscripción facial (nuevo enrollment) sin que el empleado tenga que
 * re-vincular el dispositivo.
 */
class MobileHeartbeatController extends Controller
{
    public function store(Request $request, GeneralSettings $settings): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ($employee->status !== 'active') {
            // El empleado se desvinculó implícitamente (baja, licencia, etc.) — se revoca acá
            // en vez de esperar a que un admin lo haga manualmente desde Filament.
            $employee->revokeMobileToken();

            return response()->json([
                'ok' => false,
                'message' => 'Tu acceso a la marcación por dispositivo fue desactivado. Consultá con RRHH.',
            ], 403);
        }

        $employee->forceFill(['mobile_last_heartbeat_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'config' => [
                'face_threshold' => (float) $settings->face_threshold,
                'face_min_confidence_gap' => (float) $settings->face_min_confidence_gap,
            ],
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'ci' => $employee->ci,
                'face_descriptor' => $employee->face_descriptor,
            ],
        ]);
    }
}
