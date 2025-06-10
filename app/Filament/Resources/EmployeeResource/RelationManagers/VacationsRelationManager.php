<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VacationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vacations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/aaaa')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/aaaa')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                    ])
                    ->default('pendiente')
                    ->native(false)
                    ->hiddenOn('create')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'aprobado',
                        'danger' => 'rechazado',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pendiente')
                    ->action(fn($record) => $record->update(['status' => 'aprobado'])),
                Tables\Actions\Action::make('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pendiente')
                    ->action(fn($record) => $record->update(['status' => 'rechazado'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
