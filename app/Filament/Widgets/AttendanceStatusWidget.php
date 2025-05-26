<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();

        // Empleados activos
        $totalEmpleados = Employee::where('status', 'activo')->count();

        // Empleados que marcaron entrada de jornada hoy
        $empleadosConEntrada = Attendance::where('session', 'jornada')
            ->where('type', 'entrada')
            ->whereDate('created_at', $today)
            ->distinct('employee_id')
            ->count('employee_id');

        // Empleados que no marcaron
        $empleadosSinEntrada = $totalEmpleados - $empleadosConEntrada;

        return [
            Stat::make('Entraron hoy', $empleadosConEntrada)
                ->description('Empleados que marcaron entrada de jornada')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('No marcaron', $empleadosSinEntrada)
                ->description('Empleados activos sin marcación de entrada')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Total', $totalEmpleados)
                ->description('Total de empleados activos')
                ->color('primary')
                ->icon('heroicon-o-users')
        ];
    }
}
