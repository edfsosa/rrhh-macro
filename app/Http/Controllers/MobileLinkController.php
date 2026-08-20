<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Vinculación del celular personal del empleado para la marcación offline
 * vía PWA. A diferencia de `TerminalSetupController` (enlace de un solo uso
 * generado por un admin), acá el propio empleado se identifica con CI +
 * fecha de nacimiento — ese par de datos funciona como credencial para
 * "reclamar" el dispositivo, no como control de acceso completo: la
 * marcación en sí sigue requiriendo un match facial exitoso contra el
 * descriptor cacheado (segundo factor real).
 *
 * Vincular un celular nuevo revoca automáticamente el anterior — un solo
 * dispositivo vinculado a la vez por empleado (`Employee::claimMobileToken()`).
 */
class MobileLinkController extends Controller
{
    /** Muestra el formulario de vinculación (CI + fecha de nacimiento). */
    public function show(): View
    {
        return view('attendances.mobile-link');
    }

    /**
     * Valida CI + fecha de nacimiento y emite el token Sanctum del empleado.
     * El mensaje de error es deliberadamente genérico (no distingue "CI no
     * existe" de "fecha incorrecta") para no facilitar enumeración de CIs
     * válidos — la ruta ya está limitada por `throttle:10,1`.
     */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ci' => ['required', 'string', 'max:20'],
            'birth_date' => ['required', 'date'],
        ]);

        $employee = Employee::query()
            ->where('ci', $data['ci'])
            ->whereDate('birth_date', $data['birth_date'])
            ->where('status', 'active')
            ->whereNotNull('face_descriptor')
            ->first();

        if (! $employee) {
            Log::warning('Intento de vinculación de celular con datos inválidos', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'CI o fecha de nacimiento incorrectos, o el empleado no está habilitado para marcar por celular.',
            ], 422);
        }

        $plainTextToken = $employee->claimMobileToken();

        Log::info("Celular vinculado para el empleado #{$employee->id} ({$employee->first_name} {$employee->last_name})", [
            'employee_id' => $employee->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'token' => $plainTextToken,
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
