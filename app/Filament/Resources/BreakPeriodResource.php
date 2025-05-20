<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BreakPeriodResource\Pages;
use App\Filament\Resources\BreakPeriodResource\RelationManagers;
use App\Models\BreakPeriod;
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

class BreakPeriodResource extends Resource
{
    protected static ?string $model = BreakPeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?string $navigationLabel = 'Períodos de Descanso';
    protected static ?string $label = 'Período de Descanso';
    protected static ?string $pluralLabel = 'Períodos de Descanso';
    protected static ?string $slug = 'periodos-descanso';
    protected static ?string $navigationGroup = 'Horarios';

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
                TextColumn::make('scheduleType.name')
                    ->label('Tipo de Horario')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start_time')
                    ->label('Inicio')
                    ->datetime('H:i')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('end_time')
                    ->label('Fin')
                    ->datetime('H:i')
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
            'index' => Pages\ManageBreakPeriods::route('/'),
        ];
    }
}
