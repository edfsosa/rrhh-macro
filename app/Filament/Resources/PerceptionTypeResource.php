<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerceptionTypeResource\Pages;
use App\Filament\Resources\PerceptionTypeResource\RelationManagers;
use App\Models\PerceptionType;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerceptionTypeResource extends Resource
{
    protected static ?string $model = PerceptionType::class;
    protected static ?string $navigationGroup = 'Definiciones';
    protected static ?string $navigationLabel = 'Tipos de Percepción';
    protected static ?string $label = 'Tipo de Percepción';
    protected static ?string $pluralLabel = 'Tipos de Percepción';
    protected static ?string $slug = 'tipos-percepcion';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Select::make('calculation')
                            ->label('Cálculo')
                            ->options([
                                'fixed'      => 'Fijo',
                                'hourly'     => 'Por Hora',
                                'percentage' => 'Porcentaje',
                            ])
                            ->default('fixed')
                            ->native(false)
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Valor')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn($get) => $get('calculation') === 'fixed' ? 99999999 : ($get('calculation') === 'hourly' ? 1000 : 100))
                            ->step(fn($get) => $get('calculation') === 'fixed' ? 1 : ($get('calculation') === 'hourly' ? 0.1 : 0.01))
                            ->default(1)
                            ->reactive()
                            ->required(),
                    ]),
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
                TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('calculation')
                    ->label('Cálculo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'fixed'      => 'Fijo',
                        'hourly'     => 'Por Hora',
                        'percentage' => 'Porcentaje',
                    })
                    ->badge()
                    ->colors([
                        'success' => 'fixed',
                        'warning' => 'hourly',
                        'danger'  => 'percentage',
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Valor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
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
            'index' => Pages\ManagePerceptionTypes::route('/'),
        ];
    }
}
