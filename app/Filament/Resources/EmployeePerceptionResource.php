<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePerceptionResource\Pages;
use App\Filament\Resources\EmployeePerceptionResource\RelationManagers;
use App\Models\EmployeePerception;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeePerceptionResource extends Resource
{
    protected static ?string $model = EmployeePerception::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Percepciones';
    protected static ?string $label = 'Percepción';
    protected static ?string $pluralLabel = 'Percepciones';
    protected static ?string $slug = 'percepciones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->label('Empleado')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEmployeePerceptions::route('/'),
        ];
    }
}
