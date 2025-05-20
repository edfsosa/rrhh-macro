<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleTypeResource\Pages;
use App\Filament\Resources\ScheduleTypeResource\RelationManagers;
use App\Models\ScheduleType;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScheduleTypeResource extends Resource
{
    protected static ?string $model = ScheduleType::class;
    protected static ?string $navigationGroup = 'Horarios';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Tipos de Horario';
    protected static ?string $label = 'Tipo de Horario';
    protected static ?string $pluralLabel = 'Tipos de Horario';
    protected static ?string $slug = 'tipos-horario';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60),
                Repeater::make('daySchedules')
                    ->relationship()
                    ->label('Horarios de Día')
                    ->schema([
                        Select::make('day_of_week')
                            ->label('Día de la semana')
                            ->options([
                                '0' => 'Domingo',
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                            ])
                            ->native(false)
                            ->required(),
                        TimePicker::make('start_time')
                            ->label('Hora de inicio')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Hora de fin')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                    ])
                    ->columns(3)
                    ->required()
                    ->minItems(6)
                    ->maxItems(7)
                    ->cloneable()
                    ->addActionLabel('Agregar')
                    ->deletable()
                    ->reorderable(),
                Repeater::make('breakPeriods')
                    ->relationship()
                    ->label('Períodos de Descanso')
                    ->schema([
                        TimePicker::make('start_time')
                            ->label('Inicio descanso')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Fin descanso')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                    ])
                    ->columns(2)
                    ->required()
                    ->minItems(1)
                    ->maxItems(6)
                    ->cloneable()
                    ->addActionLabel('Agregar')
                    ->deletable()
                    ->reorderable(),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
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
            'index' => Pages\ManageScheduleTypes::route('/'),
        ];
    }
}
