<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class TodayAttendance extends BaseWidget
{
    protected static ?string $heading = 'Asistencias del día';
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Attendance::query()
                    ->whereDate('created_at', now()->toDateString())
                    ->where('session', 'jornada')
                    ->where('type', 'entrada')
                    ->with('employee')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                 ->sortable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Entrada')
                    ->dateTime('H:i')
                    ->sortable(),
            ])
            ->filters([
                // Para filtrar por sucursal, se obtiene de la relacion de las marcaciones con los empleados
                SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('employee.branch', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false)
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar')
                    ->color('primary')
                    ->exports([
                        ExcelExport::make('table')
                        ->fromTable()
                        ->withFilename('asistencias_'. date('d-m-Y'))
                    ]),
            ]);
    }
}
