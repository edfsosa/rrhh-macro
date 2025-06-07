<?php
namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $stats = [];

        // Obtener todas las sucursales activas
        $sucursales = Branch::all();

        foreach ($sucursales as $sucursal) {
            // Total de empleados activos en la sucursal
            $totalEmpleados = Employee::where('branch_id', $sucursal->id)
                ->where('status', 'activo')
                ->count();

            // Empleados que marcaron entrada de jornada hoy en la sucursal
            $empleadosConEntrada = Employee::where('branch_id', $sucursal->id)
                ->whereHas('attendances', function ($query) use ($today) {
                    $query->whereDate('created_at', $today)
                    ->where('type', 'entrada')
                    ->where('session', 'jornada');
                })
                ->count();

            // Empleados que no marcaron entrada en la sucursal
            $empleadosSinEntrada = $totalEmpleados - $empleadosConEntrada;

            // Agregar estadísticas de la sucursal
            $stats[] = Stat::make("Entraron hoy ({$sucursal->name})", $empleadosConEntrada)
                ->description("Empleados que marcaron entrada en {$sucursal->name}")
                ->icon('heroicon-o-check-circle')
                ->color('success');

            $stats[] = Stat::make("No marcaron ({$sucursal->name})", $empleadosSinEntrada)
                ->description("Empleados activos sin marcación en {$sucursal->name}")
                ->icon('heroicon-o-x-circle')
                ->color('danger');

            $stats[] = Stat::make("Total ({$sucursal->name})", $totalEmpleados)
                ->description("Total de empleados activos en {$sucursal->name}")
                ->icon('heroicon-o-users')
                ->color('primary');
        }

        return $stats;
    }
}