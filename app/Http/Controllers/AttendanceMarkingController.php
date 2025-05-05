<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AttendanceMarkingController extends Controller
{
    public function showForm()
    {
        return view('attendance.mark');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:entrada,salida',
            'employee_id' => 'required|exists:employees,id',
            'location' => 'nullable|string',
        ]);

        $lastAttendance = Attendance::where('employee_id', $data['employee_id'])
            ->latest()
            ->first();

        // Validar secuencia
        if ($lastAttendance && $lastAttendance->type === $data['type']) {
            return response()->json([
                'success' => false,
                'message' => "Ya registraste una {$data['type']}, debes marcar la otra opción."
            ], 400);
        }

        Attendance::create([
            'employee_id' => $data['employee_id'],
            'type' => $data['type'],
            'location' => $data['location'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Marcación de {$data['type']} registrada correctamente."
        ]);
    }
}
