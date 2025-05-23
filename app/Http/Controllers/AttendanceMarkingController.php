<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceMarkingController extends Controller
{
    public function showForm()
    {
        return view('attendance.mark');
    }

    public function store(Request $request)
    {
        try {
            // 1) Validación incluyendo 'session'
            $data = $request->validate([
                'type'       => 'required|in:entrada,salida',
                'session'    => 'required|in:jornada,desayuno,almuerzo',
                'employee_id' => 'required|exists:employees,id',
                'location'   => 'nullable|string',
            ]);

            // 2) Busco la última marcación de la MISMA sesión
            $last = Attendance::where('employee_id', $data['employee_id'])
                ->where('session', $data['session'])
                ->latest()
                ->first();

            // 3) Valido que no repitan tipo en la misma sesión
            if ($last && $last->type === $data['type']) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya registraste una {$data['type']} en {$data['session']}, debes marcar la otra opción."
                ], 400);
            }

            // 4) Creo la marcación con sesión
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
            // Devuelve errores de validación en JSON
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            // Log y respuesta JSON en caso de excepción
            Log::error('Error al registrar marcación: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno. Inténtalo de nuevo más tarde.'
            ], 500);
        }
    }
}
