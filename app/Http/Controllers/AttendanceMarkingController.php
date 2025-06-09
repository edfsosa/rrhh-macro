<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceMarkingController extends Controller
{
    public function showForm()
    {
        // Obtener todas las sucursales
        $branches = Branch::all();
        return view('attendance.mark', compact('branches'));
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type'        => 'required|in:entrada,salida',
                'session'     => 'required|in:jornada,desayuno,almuerzo',
                'employee_id' => 'required|exists:employees,id',
                'location'    => 'string',
            ]);

            $today = now()->toDateString();

            // Traer todas las marcaciones del día del empleado
            $attendances = Attendance::where('employee_id', $data['employee_id'])
                ->whereDate('created_at', $today)
                ->orderBy('created_at')
                ->get();

            // 1. La primera marcación debe ser entrada de jornada
            if ($attendances->isEmpty()) {
                if ($data['type'] !== 'entrada' || $data['session'] !== 'jornada') {
                    return response()->json([
                        'success' => false,
                        'message' => "La primera marcación del día debe ser 'entrada' de 'jornada'."
                    ], 400);
                }
            }

            // 2. Solo una entrada de jornada por día
            $entradaJornada = $attendances->firstWhere(fn($a) => $a->type === 'entrada' && $a->session === 'jornada');
            if ($data['type'] === 'entrada' && $data['session'] === 'jornada' && $entradaJornada) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya registraste la entrada de jornada hoy."
                ], 400);
            }

            // 3. Solo una salida de jornada por día y debe ser la última marcación posible
            if ($data['type'] === 'salida' && $data['session'] === 'jornada') {
                $salidaJornada = $attendances->firstWhere(fn($a) => $a->type === 'salida' && $a->session === 'jornada');
                if ($salidaJornada) {
                    return response()->json([
                        'success' => false,
                        'message' => "Ya registraste la salida de jornada hoy."
                    ], 400);
                }
                if (!$entradaJornada) {
                    return response()->json([
                        'success' => false,
                        'message' => "Debes registrar primero la entrada de jornada."
                    ], 400);
                }
                // No permitir salida de jornada si hay una salida de desayuno o almuerzo sin su respectiva entrada
                foreach (['desayuno', 'almuerzo'] as $sesion) {
                    $lastSalida = $attendances->where('type', 'salida')->where('session', $sesion)->last();
                    $lastEntrada = $attendances->where('type', 'entrada')->where('session', $sesion)->last();
                    if ($lastSalida && (!$lastEntrada || $lastEntrada->created_at < $lastSalida->created_at)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Debes registrar la entrada de $sesion antes de salir de jornada."
                        ], 400);
                    }
                }
            }

            // 4. Solo se puede desayunar y almorzar una vez al día
            foreach (['desayuno', 'almuerzo'] as $sesion) {
                if ($data['session'] === $sesion) {
                    $salida = $attendances->where('type', 'salida')->where('session', $sesion)->count();
                    if ($data['type'] === 'salida' && $salida >= 1) {
                        return response()->json([
                            'success' => false,
                            'message' => "Solo puedes salir a $sesion una vez por día."
                        ], 400);
                    }
                    $entrada = $attendances->where('type', 'entrada')->where('session', $sesion)->count();
                    if ($data['type'] === 'entrada' && $entrada >= 1) {
                        return response()->json([
                            'success' => false,
                            'message' => "Solo puedes registrar una entrada de $sesion por día."
                        ], 400);
                    }
                }
            }

            // 5. Secuencia estricta: no permitir salida de desayuno después de salida de almuerzo
            if ($data['type'] === 'salida' && $data['session'] === 'desayuno') {
                $salidaAlmuerzo = $attendances->firstWhere(fn($a) => $a->type === 'salida' && $a->session === 'almuerzo');
                if ($salidaAlmuerzo) {
                    return response()->json([
                        'success' => false,
                        'message' => "No puedes salir a desayunar después de haber salido a almorzar."
                    ], 400);
                }
            }

            // 6. No permitir dos salidas seguidas de desayuno o almuerzo sin la entrada respectiva
            if (
                $data['type'] === 'salida' &&
                in_array($data['session'], ['desayuno', 'almuerzo'])
            ) {
                $lastSameSession = $attendances->where('session', $data['session'])->last();
                if ($lastSameSession && $lastSameSession->type === 'salida') {
                    return response()->json([
                        'success' => false,
                        'message' => "Debes marcar la entrada de {$data['session']} antes de volver a registrar una salida."
                    ], 400);
                }
            }

            // 7. Secuencia correcta desayuno/almuerzo: no puede haber dos salidas seguidas ni dos entradas seguidas
            if (in_array($data['session'], ['desayuno', 'almuerzo'])) {
                $last = $attendances->last();
                if (
                    $last &&
                    $last->type === 'salida' &&
                    in_array($last->session, ['desayuno', 'almuerzo'])
                ) {
                    // Si intenta marcar cualquier cosa que no sea la entrada de la misma sesión, bloquear
                    if (
                        !($data['type'] === 'entrada' && $data['session'] === $last->session)
                    ) {
                        return response()->json([
                            'success' => false,
                            'message' => "Debes marcar la entrada de {$last->session} antes de registrar otra marcación."
                        ], 400);
                    }
                }
                if ($last && $last->session === $data['session'] && $last->type === $data['type']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Ya registraste una {$data['type']} de {$data['session']}, debes marcar la otra opción."
                    ], 400);
                }
                // Para salida, debe haber entrada de jornada primero
                if ($data['type'] === 'salida' && !$entradaJornada) {
                    return response()->json([
                        'success' => false,
                        'message' => "Debes registrar primero la entrada de jornada antes de salir a {$data['session']}."
                    ], 400);
                }
                // Para entrada, debe haber salida previa de ese mismo
                if ($data['type'] === 'entrada') {
                    $lastSalida = $attendances->where('type', 'salida')->where('session', $data['session'])->last();
                    $lastEntrada = $attendances->where('type', 'entrada')->where('session', $data['session'])->last();
                    if (!$lastSalida || ($lastEntrada && $lastEntrada->created_at > $lastSalida->created_at)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Debes registrar la salida de {$data['session']} antes de marcar la entrada."
                        ], 400);
                    }
                }
            }

            // 8. No permitir marcar desayuno o almuerzo después de la salida de jornada
            if ($data['session'] !== 'jornada') {
                $salidaJornada = $attendances->firstWhere(fn($a) => $a->type === 'salida' && $a->session === 'jornada');
                if ($salidaJornada) {
                    return response()->json([
                        'success' => false,
                        'message' => "No puedes registrar marcaciones de {$data['session']} después de la salida de jornada."
                    ], 400);
                }
            }

            // Registrar la marcación
            Attendance::create([
                'employee_id' => $data['employee_id'],
                'type'        => $data['type'],
                'session'     => $data['session'],
                'location'    => $data['location'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Marcación de {$data['type']} ({$data['session']}) registrada correctamente."
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al registrar marcación: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno. Inténtalo de nuevo más tarde.'
            ], 500);
        }
    }
}
