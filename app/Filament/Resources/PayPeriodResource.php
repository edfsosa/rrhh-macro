<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Filament\Resources\PayPeriodResource\RelationManagers;
use App\Models\PayPeriod;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Periodos de Pago';
    protected static ?string $label = 'Período de Pago';
    protected static ?string $pluralLabel = 'Periodos de Pago';
    protected static ?string $slug = 'periodos-pago';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/yyyy')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/yyyy')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required()
                    ->rules(['after_or_equal:start_date']),
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
                TextColumn::make('start_date')
                    ->label('Fecha de inicio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fecha de fin')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayPeriods::route('/'),
            'create' => Pages\CreatePayPeriod::route('/create'),
            'edit' => Pages\EditPayPeriod::route('/{record}/edit'),
        ];
    }
}
