<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers\EmployeesRelationManager;
use App\Models\Branch;
use App\Models\Company;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Recurso Filament para la gestión de sucursales. */
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Sucursales';

    protected static ?string $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Sucursal';

    protected static ?string $pluralModelLabel = 'Sucursales';

    protected static ?string $slug = 'sucursales';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Define el formulario de creación y edición de sucursales.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Información general')
                ->description('Datos básicos de la sucursal.')
                ->schema([
                    Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'name', fn ($query) => $query->active())
                        ->getOptionLabelFromRecordUsing(
                            fn (Company $record) => $record->name.($record->trade_name ? ' ('.$record->trade_name.')' : '')
                        )
                        ->searchable(['name', 'trade_name'])
                        ->preload()
                        ->required()
                        ->helperText('Empresa a la que pertenece esta sucursal.'),

                    TextInput::make('name')
                        ->label('Nombre')
                        ->placeholder('Ej: Sucursal Central')
                        ->required()
                        ->maxLength(100)
                        ->unique(
                            table: Branch::class,
                            column: 'name',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('company_id', $get('company_id'))
                        )
                        ->helperText('El nombre debe ser único dentro de la empresa.'),

                    TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->placeholder('Ej: 0981123456')
                        ->maxLength(10)
                        ->regex('/^0\d{8,9}$/')
                        ->validationMessages([
                            'regex' => 'Ingrese un número válido de Paraguay: móvil (09XXXXXXXX) o fijo (021XXXXXX / 0XXXXXXXX).',
                        ])
                        ->helperText('Número sin espacios ni guiones. Ej: 0981123456'),

                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->placeholder('Ej: sucursal@empresa.com')
                        ->maxLength(100)
                        ->unique(Branch::class, 'email', ignoreRecord: true)
                        ->helperText('Debe ser único entre las sucursales.'),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Ubicación')
                ->description('Dirección y coordenadas GPS de la sucursal.')
                ->schema([
                    TextInput::make('address')
                        ->label('Dirección')
                        ->maxLength(100)
                        ->placeholder('Ej: Av. España 1234 c/ Brasil')
                        ->helperText('Se autocompleta al mover el pin en el mapa.'),

                    TextInput::make('city')
                        ->label('Ciudad')
                        ->readOnly()
                        ->maxLength(100)
                        ->helperText('Se completa automáticamente al ubicar el pin en el mapa.'),

                    Map::make('coordinates')
                        ->label('Ubicación en el mapa')
                        ->columnSpanFull()
                        ->defaultLocation([-25.2867, -57.6478]) // Asunción, Paraguay
                        ->draggable()
                        ->clickable()
                        ->autocomplete('address')
                        ->autocompleteReverse(true)
                        ->reverseGeocode([
                            'address' => '%n %S',
                            'city' => '%L',
                        ])
                        ->geolocate()
                        ->height('400px'),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }

    /**
     * Define la vista de detalle de una sucursal.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Información general')
                ->columns(2)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre de la sucursal')
                        ->copyable()
                        ->copyMessage('Nombre copiado'),

                    TextEntry::make('active_employees')
                        ->label('Empleados Activos / Total')
                        ->getStateUsing(
                            fn (Branch $record) => $record->activeEmployees()->count().' / '.$record->employees()->count()
                        )
                        ->badge()
                        ->color('success')
                        ->icon('heroicon-o-users'),

                    TextEntry::make('company.name')
                        ->label('Empresa'),

                    TextEntry::make('company.trade_name')
                        ->label('Nombre comercial')
                        ->placeholder('Sin nombre comercial'),
                ])
                ->collapsible(),

            InfoSection::make('Contacto y dirección')
                ->columns(2)
                ->schema([
                    TextEntry::make('address')
                        ->label('Dirección')
                        ->icon('heroicon-o-map')
                        ->copyable()
                        ->copyMessage('Dirección copiada')
                        ->placeholder('Sin dirección'),

                    TextEntry::make('city')
                        ->label('Ciudad')
                        ->badge()
                        ->color('info')
                        ->placeholder('Sin ciudad'),

                    TextEntry::make('phone')
                        ->label('Teléfono')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->placeholder('Sin teléfono'),

                    TextEntry::make('email')
                        ->label('Correo electrónico')
                        ->icon('heroicon-o-envelope')
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->placeholder('Sin correo'),
                ])
                ->collapsible(),
        ]);
    }

    /**
     * Define la tabla de listado de sucursales con filtros y acciones.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->description(fn ($record) => $record->company?->trade_name)
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Sucursal')
                    ->description(fn ($record) => $record->address)
                    ->icon('heroicon-o-building-storefront')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('contact_info')
                    ->label('Contacto')
                    ->getStateUsing(fn ($record) => $record->phone ?: 'Sin teléfono')
                    ->description(fn ($record) => $record->email ?: null)
                    ->icon('heroicon-o-phone')
                    ->placeholder('Sin contacto'),

                TextColumn::make('active_employees_count')
                    ->label('Empleados Activos')
                    ->counts('activeEmployees')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name', fn ($query) => $query->active())
                    ->getOptionLabelFromRecordUsing(
                        fn (Company $record) => $record->name.($record->trade_name ? ' ('.$record->trade_name.')' : '')
                    )
                    ->searchable(['name', 'trade_name'])
                    ->preload()
                    ->placeholder('Todas las empresas'),

                SelectFilter::make('city')
                    ->label('Ciudad')
                    ->options(
                        fn () => Branch::query()
                            ->distinct()
                            ->pluck('city', 'city')
                            ->filter()
                            ->sort()
                            ->toArray()
                    )
                    ->placeholder('Todas las ciudades')
                    ->searchable()
                    ->native(false),
            ])
            ->actions([
                Action::make('view_map')
                    ->label('Ver en mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(
                        fn ($record) => isset($record->coordinates['lat'], $record->coordinates['lng'])
                            ? sprintf('https://www.google.com/maps?q=%s,%s', $record->coordinates['lat'], $record->coordinates['lng'])
                            : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => isset($record->coordinates['lat'], $record->coordinates['lng'])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->modalHeading('Eliminar sucursales')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas sucursales? Los empleados asignados quedarán sin sucursal.'),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No hay sucursales registradas')
            ->emptyStateDescription('Comienza agregando la primera sucursal.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }

    /**
     * Retorna los relation managers asociados al recurso.
     *
     * @return array<class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
    }

    /**
     * Retorna las páginas registradas para este recurso.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
