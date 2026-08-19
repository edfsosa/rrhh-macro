<?php

namespace App\Filament\Resources\AttendanceMarkFailureResource\Pages;

use App\Filament\Resources\AttendanceMarkFailureResource;
use App\Filament\Resources\AttendanceMarkFailureResource\Widgets\TopFailingEmployeesWidget;
use App\Models\AttendanceMarkFailure;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Página de listado de intentos fallidos de marcación. */
class ListAttendanceMarkFailures extends ListRecords
{
    protected static string $resource = AttendanceMarkFailureResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Widget de diagnóstico: empleados con fallos de marcación recurrentes.
     *
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TopFailingEmployeesWidget::class,
        ];
    }

    /**
     * Tabs para filtrar por modo de marcación.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(AttendanceMarkFailure::count()),

            'terminal' => Tab::make('Terminal')
                ->badge(AttendanceMarkFailure::where('mode', 'terminal')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('mode', 'terminal')),

            'mobile' => Tab::make('Móvil')
                ->badge(AttendanceMarkFailure::where('mode', 'mobile')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('mode', 'mobile')),
        ];
    }
}
