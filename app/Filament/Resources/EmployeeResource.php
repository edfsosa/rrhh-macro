<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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

    // Formulario de creación y edición
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos Personales')
                    ->description('Información básica del empleado')
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto')
                            ->disk('public')
                            ->directory('employees')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->nullable(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('Nombre(s)')
                                    ->required()
                                    ->maxLength(60),
                                TextInput::make('last_name')
                                    ->label('Apellido(s)')
                                    ->required()
                                    ->maxLength(60),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('ci')
                                    ->label('Cédula de Identidad')
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->maxLength(20),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->minLength(7)
                                    ->maxLength(30),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->required()
                                    ->maxLength(60)
                                    ->unique(Employee::class, 'email', ignoreRecord: true),
                            ]),
                    ]),

                Section::make('Detalles de contratación')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                DatePicker::make('hire_date')
                                    ->label('Fecha de Contratación')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(10))
                                    ->maxDate(now()->addYears(10))
                                    ->default(now())
                                    ->required(),
                                Select::make('contract_type')
                                    ->label('Tipo de Contrato')
                                    ->options([
                                        'mensualero' => 'Mensualero',
                                        'jornalero' => 'Jornalero',
                                    ])
                                    ->native(false)
                                    ->required(),
                                TextInput::make('base_salary')
                                    ->label('Salario Base')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxLength(10)
                                    ->prefix('Gs.')
                                    ->default(0),
                            ]),
                        Grid::make(4)
                            ->schema([
                                Select::make('payment_method')
                                    ->label('Método de Pago')
                                    ->options([
                                        'debito' => 'Tarjeta de Débito',
                                        'efectivo' => 'Efectivo',
                                        'cheque' => 'Cheque',
                                    ])
                                    ->searchable()
                                    ->native(false)
                                    ->required(),
                                TextInput::make('position')
                                    ->label('Cargo')
                                    ->required()
                                    ->maxLength(60),
                                TextInput::make('department')
                                    ->label('Departamento')
                                    ->required()
                                    ->maxLength(60),
                                Select::make('status')
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
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular(),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->numeric()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hire_date')
                    ->label('Contratación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->badge()
                    ->colors([
                        'success' => 'mensualero',
                        'warning' => 'jornalero',
                    ])
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('base_salary')
                    ->label('Salario (Gs.)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->badge()
                    ->colors([
                        'success' => 'debito',
                        'warning' => 'efectivo',
                        'danger' => 'cheque',
                    ])
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'warning' => 'inactivo',
                        'danger' => 'suspendido',
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'suspendido' => 'Suspendido',
                    ])
                    ->placeholder('Seleccionar estado')
                    ->native(false),
                SelectFilter::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->options([
                        'mensualero' => 'Mensualero',
                        'jornalero' => 'Jornalero',
                    ])
                    ->placeholder('Seleccionar tipo de contrato')
                    ->native(false),
                SelectFilter::make('payment_method')
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
