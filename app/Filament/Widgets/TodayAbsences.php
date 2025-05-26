<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class TodayAbsences extends BaseWidget
{
    protected static ?string $heading = 'Ausencias del día';
    public function table(Table $table): Table
    {
        return $table
            ->query(
                // consulta que obtiene los empleados activos que no marcaron su entrada de jornada
                \App\Models\Employee::query()
                    ->where('status', 'activo')
                    ->whereDoesntHave('attendances', function ($query) {
                        $query->where('session', 'jornada')
                            ->where('type', 'entrada')
                            ->whereDate('created_at', now()->toDateString());
                    })
                    ->with('position')
            )
            ->columns([
                Tables\Columns\TextColumn::make('ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar')
                    ->color('primary')
                    ->exports([
                        ExcelExport::make()->withFilename('ausencias_' . date('d-m-Y'))
                    ]),
            ]);
    }
}
