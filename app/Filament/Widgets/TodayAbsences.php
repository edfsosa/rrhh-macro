<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                Employee::query()
                    ->where('status', 'activo')
                    ->whereDoesntHave('attendances', function ($query) {
                        $query->where('session', 'jornada')
                            ->where('type', 'entrada')
                            ->whereDate('created_at', now()->toDateString());
                    })
                    ->with('position')
            )
            ->columns([
                TextColumn::make('ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // Para filtrar por sucursal
                SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
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
                            ->withFilename('ausencias_' . date('d-m-Y'))
                    ]),
            ]);
    }
}
