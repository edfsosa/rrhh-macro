<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Widgets\ChartWidget;

class AttendanceByBranchChart extends ChartWidget
{
    protected static ?string $heading = 'Estadísticas de Asistencia por Sucursal';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Obtener la fecha actual
        $today = now()->toDateString();

        // Obtener todas las sucursales activas
        $sucursales = Branch::all();

        // Inicializar arreglos para los datos del gráfico
        $labels = [];
        $entraron = [];
        $noEntraron = [];

        foreach ($sucursales as $sucursal) {
            // Total de empleados activos en la sucursal
            $totalEmpleados = Employee::where('branch_id', $sucursal->id)
                ->where('status', 'activo')
                ->count();

            // Empleados que marcaron entrada de jornada hoy en la sucursal
            $empleadosConEntrada = Attendance::where('session', 'jornada')
                ->where('type', 'entrada')
                ->whereDate('created_at', $today)
                ->whereIn('employee_id', Employee::where('branch_id', $sucursal->id)
                    ->pluck('id'))
                ->distinct('employee_id')
                ->count('employee_id');

            // Empleados que no marcaron entrada en la sucursal
            $empleadosSinEntrada = $totalEmpleados - $empleadosConEntrada;

            // Agregar datos al gráfico
            $labels[] = $sucursal->name;
            $entraron[] = $empleadosConEntrada;
            $noEntraron[] = $empleadosSinEntrada;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Entraron',
                    'data' => $entraron,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
                ],
                [
                    'label' => 'No Entraron',
                    'data' => $noEntraron,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    // Define el tipo de gráfico
    protected function getType(): string
    {
        return 'bar';
    }
}
