<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationLabel = 'Empleados';
    protected static ?string $label = 'Empleado';
    protected static ?string $pluralLabel = 'Empleados';
    protected static ?string $slug = 'empleados';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto')
                    ->disk('public')
                    ->directory('employees')
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->circleCropper()
                    ->imageCropAspectRatio('1:1')
                    ->downloadable()
                    ->openable()
                    ->nullable(),
                Forms\Components\TextInput::make('first_name')
                    ->label('Nombre(s)')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('last_name')
                    ->label('Apellido(s)')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('ci')
                    ->label('Cédula de Identidad')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->prefix('+595')
                    ->minLength(7)
                    ->maxLength(30),
                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->maxLength(60)
                    ->unique(Employee::class, 'email', ignoreRecord: true),
                Forms\Components\DatePicker::make('hire_date')
                    ->label('Fecha de Contratación')
                    ->native(false)
                    ->placeholder('dd/mm/yyyy')
                    ->displayFormat('d/m/Y')
                    ->minDate(now()->subYears(10))
                    ->maxDate(now()->addYears(10))
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->options([
                        'mensualero' => 'Mensualero',
                        'jornalero' => 'Jornalero',
                    ])
                    ->searchable()
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('base_salary')
                    ->label('Salario Base')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxLength(10)
                    ->prefix('Gs.')
                    ->default(0),
                Forms\Components\Select::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'debito' => 'Tarjeta de Débito',
                        'efectivo' => 'Efectivo',
                        'cheque' => 'Cheque',
                    ])
                    ->searchable()
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('position')
                    ->label('Cargo')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('department')
                    ->label('Departamento')
                    ->required()
                    ->maxLength(60),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'suspendido' => 'Suspendido',
                    ])
                    ->searchable()
                    ->native(false)
                    ->hiddenOn('create')
                    ->default('activo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular(),
                Tables\Columns\TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->numeric()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('hire_date')
                    ->label('Contratación')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->badge()
                    ->colors([
                        'success' => 'mensualero',
                        'warning' => 'jornalero',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Salario (Gs.)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->badge()
                    ->colors([
                        'success' => 'debito',
                        'warning' => 'efectivo',
                        'danger' => 'cheque',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('department')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'warning' => 'inactivo',
                        'danger' => 'suspendido',
                    ])
                    ->sortable()
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'suspendido' => 'Suspendido',
                    ])
                    ->placeholder('Seleccionar estado')
                    ->native(false),
                Tables\Filters\SelectFilter::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->options([
                        'mensualero' => 'Mensualero',
                        'jornalero' => 'Jornalero',
                    ])
                    ->placeholder('Seleccionar tipo de contrato')
                    ->native(false),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'debito' => 'Tarjeta de Débito',
                        'efectivo' => 'Efectivo',
                        'cheque' => 'Cheque',
                    ])
                    ->placeholder('Seleccionar método de pago')
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
