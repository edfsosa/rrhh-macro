<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'perceptions';
    protected static ?string $title = 'Percepciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('perception_type_id')
                    ->label('Tipo de Percepción')
                    ->relationship('type', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->closeOnDateSelection(true)
                    ->required()
                    ->default(now()),
                DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->closeOnDateSelection(true)
                    ->default(now()->addMonth()),
                TextInput::make('installments')
                    ->label('Cuotas')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                TextInput::make('remaining_installments')
                    ->label('Cuotas Restantes')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->required(),
                TextInput::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type.name')
                    ->label('Tipo de Percepción')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('start_date')
                    ->label('Fecha de Inicio')
                    ->sortable()
                    ->date('d/m/Y'),
                TextColumn::make('end_date')
                    ->label('Fecha de Fin')
                    ->sortable()
                    ->date('d/m/Y'),
                TextColumn::make('installments')
                    ->label('Cuotas')
                    ->sortable(),
                TextColumn::make('remaining_installments')
                    ->label('Cuotas Restantes')
                    ->sortable(),
                TextColumn::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->sortable()
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->sortable()
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
