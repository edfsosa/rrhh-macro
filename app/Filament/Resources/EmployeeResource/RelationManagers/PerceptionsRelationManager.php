<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
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
                Select::make('pay_period_id')
                    ->relationship('period', 'start_date')
                    ->label('Período')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('perception_type_id')
                    ->relationship('type', 'name')
                    ->label('Tipo de Percepción')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('period.start_date')
                    ->label('Período')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type.name')
                    ->label('Percepción')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->searchable()
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
