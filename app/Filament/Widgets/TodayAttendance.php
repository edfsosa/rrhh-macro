<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Tables;
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
                Tables\Columns\TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Entrada')
                    ->dateTime('H:i')
                    ->sortable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar')
                    ->color('primary')
                    ->exports([
                        ExcelExport::make()->withFilename('asistencias_'. date('d-m-Y'))
                    ]),
            ]);
    }
}
