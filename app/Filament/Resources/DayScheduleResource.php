<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DayScheduleResource\Pages;
use App\Filament\Resources\DayScheduleResource\RelationManagers;
use App\Models\DaySchedule;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DayScheduleResource extends Resource
{
    protected static ?string $model = DaySchedule::class;
    protected static ?string $navigationGroup = 'Horarios';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Horarios';
    protected static ?string $label = 'Horario';
    protected static ?string $pluralLabel = 'Horarios';
    protected static ?string $slug = 'horarios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('schedule_type_id')
                    ->relationship('scheduleType', 'name')
                    ->label('Tipo de Horario')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),

                Select::make('day_of_week')
                    ->label('Día de la semana')
                    ->options([
                        0 => 'Domingo',
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('scheduleType.name')
                    ->label('Tipo de Horario')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('day_of_week')
                    ->label('Día de la semana')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start_time')
                    ->label('Hora de inicio')
                    ->sortable()
                    ->searchable()
                    ->dateTime('H:i'),
                TextColumn::make('end_time')
                    ->label('Hora de fin')
                    ->sortable()
                    ->searchable()
                    ->dateTime('H:i'),
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
            'index' => Pages\ManageDaySchedules::route('/'),
        ];
    }
}
