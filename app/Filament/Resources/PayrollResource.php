<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Resources\PayrollResource\RelationManagers;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $navigationGroup = 'Recursos Humanos';
    protected static ?string $navigationLabel = 'Nóminas';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $slug = 'nominas';
    protected static ?string $pluralModelLabel = 'Nóminas';
    protected static ?string $modelLabel = 'Nómina';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('period')
                    ->label('Período')
                    ->helperText('Formato: YYYY-MM')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->required(),
                Forms\Components\DatePicker::make('pay_date')
                    ->label('Fecha de pago')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'processed' => 'Procesada',
                        'paid' => 'Pagada'
                    ])
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('period')
                    ->label('Período')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de fin')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pay_date')
                    ->label('Fecha de pago')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate')
                ->label('Generar Nómina')
                ->action(function (Payroll $record) {
                    app(PayrollService::class)->generatePayroll($record);
                }),
                Tables\Actions\Action::make('download_payslips')
                ->label('Descargar Recibos')
                ->url(fn (Payroll $record) => route('payrolls.download-payslips', ['payroll' => $record->id]))
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
