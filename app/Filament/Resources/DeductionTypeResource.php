<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeductionTypeResource\Pages;
use App\Filament\Resources\DeductionTypeResource\RelationManagers;
use App\Models\DeductionType;
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

class DeductionTypeResource extends Resource
{
    protected static ?string $model = DeductionType::class;
    protected static ?string $navigationGroup = 'Definiciones';
    protected static ?string $navigationLabel = 'Tipos de Deducción';
    protected static ?string $label = 'Tipo de Deducción';
    protected static ?string $pluralLabel = 'Tipos de Deducción';
    protected static ?string $slug = 'tipos-deduccion';
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
                            ->maxValue(fn($get) => $get('calculation') === 'fixed' ? 99999999 : 100)
                            ->step(fn($get) => $get('calculation') === 'fixed' ? 1 : 0.1)
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
                    ->formatStateUsing(fn ($state) => $state === 'fixed' ? 'Fijo' : 'Porcentaje')
                    ->badge()
                    ->colors([
                        'success' => 'fixed',
                        'warning' => 'percentage',
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Valor')
                    ->sortable()
                    ->searchable(),
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
            'index' => Pages\ManageDeductionTypes::route('/'),
        ];
    }
}
