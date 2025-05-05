<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerceptionResource\Pages;
use App\Filament\Resources\PerceptionResource\RelationManagers;
use App\Models\Perception;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerceptionResource extends Resource
{
    protected static ?string $model = Perception::class;
    protected static ?string $navigationLabel = 'Percepciones';
    protected static ?string $label = 'Percepción';
    protected static ?string $pluralLabel = 'Percepciones';
    protected static ?string $slug = 'percepciones';
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'id')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'Bono' => 'Bono',
                        'Comisión' => 'Comisión',
                        'Horas Extra' => 'Horas Extra',
                        'Otro' => 'Otro',
                    ])
                    ->searchable()
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),
                Forms\Components\Select::make('mode')
                    ->label('Modo')
                    ->options([
                        'monto_fijo' => 'Monto Fijo',
                        'porcentaje' => 'Porcentaje',
                    ])
                    ->default('monto_fijo')
                    ->searchable()
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Monto')
                    ->integer()
                    ->minValue(0)
                    ->visible(fn(callable $get) => $get('mode') === 'monto_fijo')
                    ->required(),
                Forms\Components\TextInput::make('percentage')
                    ->label('Porcentaje')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->visible(fn(callable $get) => $get('mode') === 'porcentaje')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->numeric()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mode')
                    ->label('Modo')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state === 'monto_fijo' ? 'Monto Fijo' : 'Porcentaje'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activo'),
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
                SelectFilter::make('employee')
                    ->relationship('employee', 'ci')
                    ->label('Empleado')
                    ->placeholder('Seleccionar empleado')
                    ->options(function (Builder $query) {
                        return $query->pluck('ci', 'id');
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->placeholder('Seleccionar tipo')
                    ->options([
                        'Bono' => 'Bono',
                        'Comisión' => 'Comisión',
                        'Horas Extra' => 'Horas Extra',
                        'Otro' => 'Otro',
                    ])
                    ->searchable()
                    ->native(false),
                SelectFilter::make('mode')
                    ->label('Modo')
                    ->placeholder('Seleccionar modo')
                    ->options([
                        'monto_fijo' => 'Monto Fijo',
                        'porcentaje' => 'Porcentaje',
                    ])
                    ->searchable()
                    ->native(false),
                TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->placeholder('Seleccionar estado')
                    ->trueLabel('Sí')
                    ->falseLabel('No')
                    ->native(false),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Creados desde')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('Creados hasta')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
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
            'index' => Pages\ManagePerceptions::route('/'),
        ];
    }
}
