<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Resources\PayrollResource\RelationManagers;
use App\Models\Payroll;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $navigationLabel = 'Nóminas';
    protected static ?string $label = 'Nómina';
    protected static ?string $pluralLabel = 'Nóminas';
    protected static ?string $slug = 'nominas';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Periodo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Salario Neto')
                    ->money('PYG', true)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
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
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn(Payroll $record) => route('payroll.pdf', $record))
                    ->openUrlInNewTab(),

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
            'index' => Pages\ManagePayrolls::route('/'),
        ];
    }
}
