<?php

namespace App\Filament\Resources\TerminalResource\RelationManagers;

use App\Models\AttendanceEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Historial de marcaciones registradas por este terminal — solo lectura.
 * `AttendanceEventResource` solo tiene una página `index` (patrón Manage,
 * sin `ViewRecord` propio), así que no hay a dónde enlazar cada fila con
 * `->recordUrl()`; se resuelve como tabla puramente informativa, sin
 * acciones de fila.
 */
class AttendanceEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceEvents';

    protected static ?string $title = 'Marcaciones';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (AttendanceEvent $record) => $record->recorded_at->diffForHumans())
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                TextColumn::make('event_type')
                    ->label('Tipo de evento')
                    ->formatStateUsing(fn (string $state) => AttendanceEvent::getEventTypeLabel($state))
                    ->badge()
                    ->color(fn (string $state) => AttendanceEvent::getEventTypeColor($state))
                    ->icon(fn (string $state) => AttendanceEvent::getEventTypeIcon($state))
                    ->sortable(),

                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->description(fn (AttendanceEvent $record) => $record->employee_ci ? "CI: {$record->employee_ci}" : null)
                    ->searchable()
                    ->placeholder('N/A'),

                IconColumn::make('branch_mismatch')
                    ->label('Sucursal distinta')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->tooltip(fn (AttendanceEvent $record) => $record->branch_mismatch
                        ? 'El empleado pertenece a una sucursal distinta a la del terminal'
                        : null),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Sin marcaciones registradas')
            ->emptyStateDescription('Las marcaciones aparecerán acá cuando algún empleado marque asistencia desde este terminal.')
            ->emptyStateIcon('heroicon-o-hand-raised');
    }
}
