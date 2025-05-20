<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeDeductionResource\Pages;
use App\Filament\Resources\EmployeeDeductionResource\RelationManagers;
use App\Models\EmployeeDeduction;
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

class EmployeeDeductionResource extends Resource
{
    protected static ?string $model = EmployeeDeduction::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Deducciones';
    protected static ?string $label = 'Deducción';
    protected static ?string $pluralLabel = 'Deducciones';
    protected static ?string $slug = 'deducciones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->relationship('employee', 'first_name')
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
                Select::make('deduction_type_id')
                    ->relationship('type', 'name')
                    ->label('Tipo de Deducción')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->minValue(1)
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
                    ->label('Deducción')
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
            'index' => Pages\ManageEmployeeDeductions::route('/'),
        ];
    }
}
