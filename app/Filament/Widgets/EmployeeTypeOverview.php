<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmployeeTypeOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Mensualeros', Employee::where('contract_type', 'mensualero')->count())
                ->description('Total de empleados mensualeros')
                ->color('success')
                ->icon('heroicon-o-calendar'),
            Stat::make('Jornaleros', Employee::where('contract_type', 'jornalero')->count())
                ->description('Total de empleados jornaleros')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Total', Employee::count())
                ->description('Total de empleados')
                ->color('primary')
                ->icon('heroicon-o-users')
        ];
    }
}
