<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auto-desvinculación del dispositivo personal — a diferencia de "Revocar sesión
 * móvil" en EmployeeResource (accionada por un admin desde Filament), acá es
 * el propio empleado quien decide desvincular su dispositivo (ej. antes de
 * venderlo o prestarlo) desde /marcar. Autenticado vía Sanctum (ability
 * `mobile:sync`) — el token usado para autenticar esta misma petición queda
 * revocado al responder.
 */
class MobileUnlinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $employee->revokeMobileToken();

        return response()->json(['ok' => true]);
    }
}
