<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Attendance::with('employee')
            ->get()
            ->map(function ($attendance) {
                return [
                    'CI' => $attendance->employee->ci,
                    'Nombre' => $attendance->employee->first_name,
                    'Apellido' => $attendance->employee->last_name,
                    'Cargo' => $attendance->employee->position,
                    'Tipo' => $attendance->type,
                    'Ubicación' => $attendance->location,
                    'Marcado en' => $attendance->created_at->format('d/m/Y H:i'),
                ];
            });
    }

    public function headings(): array
    {
        return ['CI', 'Nombre', 'Apellido', 'Cargo', 'Tipo', 'Ubicación', 'Marcado en'];
    }
}
