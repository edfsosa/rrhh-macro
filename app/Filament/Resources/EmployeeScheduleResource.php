<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeScheduleResource\Pages;
use App\Filament\Resources\EmployeeScheduleResource\RelationManagers;
use App\Models\EmployeeSchedule;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeScheduleResource extends Resource
{
    protected static ?string $model = EmployeeSchedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Horarios de Empleados';
    protected static ?string $navigationGroup = 'Horarios';
    protected static ?string $label = 'Horario de Empleado';
    protected static ?string $pluralLabel = 'Horarios de Empleados';
    protected static ?string $slug = 'employee-schedules';

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

                Select::make('schedule_type_id')
                    ->relationship('scheduleType', 'name')
                    ->label('Tipo de Horario')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),

                DatePicker::make('valid_from')
                    ->label('Vigencia desde')
                    ->required(),

                DatePicker::make('valid_to')
                    ->label('Vigencia hasta'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('employee.first_name')
                    ->label('Empleado')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('scheduleType.name')
                    ->label('Tipo de Horario')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('valid_from')
                    ->label('Vigencia desde')
                    ->sortable()
                    ->date('d/m/Y'),
                TextColumn::make('valid_to')
                    ->label('Vigencia hasta')
                    ->sortable()
                    ->date('d/m/Y'),
                TextColumn::make('created_at')
                    ->label('Creado el')
                    ->sortable()
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado el')
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
            'index' => Pages\ManageEmployeeSchedules::route('/'),
        ];
    }
}
