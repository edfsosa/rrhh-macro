<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Provisión de terminales para la marcación offline vía PWA. El admin genera
 * un enlace de configuración de un solo uso desde TerminalResource; el
 * kiosko lo visita una vez, online, durante la instalación física, y recibe
 * a cambio un token Sanctum (ability `terminal:sync`) que usará contra
 * routes/api.php de ahí en adelante — incluso tras largos períodos offline,
 * ya que no depende de la sesión de Laravel (que expira a los 120 minutos).
 */
class TerminalSetupController extends Controller
{
    /** Muestra la pantalla de vinculación del terminal. */
    public function show(string $code, string $setupToken): View
    {
        $terminal = Terminal::where('code', $code)->first();

        if (! $terminal || ! $terminal->isSetupTokenValid($setupToken)) {
            return view('attendances.terminal-setup-invalid');
        }

        return view('attendances.terminal-setup', compact('terminal', 'setupToken'));
    }

    /** Reclama el enlace y emite el token Sanctum del terminal. Un solo uso. */
    public function claim(Request $request, string $code, string $setupToken): JsonResponse
    {
        $terminal = Terminal::where('code', $code)->first();

        if (! $terminal || ! $terminal->isSetupTokenValid($setupToken)) {
            Log::warning("Intento de reclamo de setup token inválido o expirado para terminal '{$code}'", [
                'code' => $code,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Enlace de configuración inválido o expirado. Solicite uno nuevo desde el panel de administración.',
            ], 422);
        }

        $clientHintModel = $request->validate([
            'device_model_hint' => ['nullable', 'string', 'max:100'],
        ])['device_model_hint'] ?? null;

        $plainTextToken = $terminal->claimSanctumToken($request->userAgent(), $clientHintModel);

        Log::info("Terminal '{$terminal->code}' ({$terminal->name}) provisionado para sincronización offline", [
            'terminal_id' => $terminal->id,
            'branch_id' => $terminal->branch_id,
        ]);

        return response()->json([
            'ok' => true,
            'token' => $plainTextToken,
            'terminal' => [
                'id' => $terminal->id,
                'code' => $terminal->code,
                'name' => $terminal->name,
                'branch_id' => $terminal->branch_id,
            ],
        ]);
    }
}
