<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationResource\Pages;
use App\Filament\Resources\VacationResource\RelationManagers;
use App\Models\Vacation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VacationResource extends Resource
{
    protected static ?string $model = Vacation::class;
    protected static ?string $navigationLabel = 'Vacaciones';
    protected static ?string $label = 'Vacación';
    protected static ?string $pluralLabel = 'Vacaciones';
    protected static ?string $slug = 'vacaciones';
    protected static ?string $navigationIcon = 'heroicon-o-sun';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'ci')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/aaaa')
                    ->minDate(now())
                    ->maxDate(now()->addYear())
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->displayFormat('d/m/Y')
                    ->placeholder('dd/mm/aaaa')
                    ->minDate(now())
                    ->maxDate(now()->addYear())
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                    ])
                    ->default('pendiente')
                    ->native(false)
                    ->hiddenOn('create')
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
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_days')
                    ->label('Días Totales')
                    ->default(fn(Vacation $record): int => $record->getTotalDaysAttribute())
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'aprobado',
                        'danger' => 'rechazado',
                    ]),
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccionar estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                    ])
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pendiente')
                    ->action(fn($record) => $record->update(['status' => 'aprobado'])),
                Tables\Actions\Action::make('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pendiente')
                    ->action(fn($record) => $record->update(['status' => 'rechazado'])),
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
            'index' => Pages\ManageVacations::route('/'),
        ];
    }
}
