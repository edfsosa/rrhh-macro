<?php

namespace App\Filament\Resources\RotationPatternResource\RelationManagers;

use App\Filament\Resources\EmployeeResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RotationPattern;
use App\Models\ShiftTemplate;
use App\Services\RotationService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Muestra y gestiona los empleados con asignación activa al patrón de
 * rotación, usando RotationService::assign()/closeActive() — mismo patrón
 * que ScheduleResource\RelationManagers\EmployeesRelationManager, adaptado
 * a rotation_assignments (que además requiere start_index).
 */
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'currentEmployees';

    protected static ?string $title = 'Empleados Asignados';

    protected static ?string $modelLabel = 'empleado';

    protected static ?string $pluralModelLabel = 'empleados';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Formulario para asignar empleados al patrón, con filtro opcional por
     * sucursal y selección del día de inicio del ciclo (por nombre de turno,
     * no por índice crudo).
     */
    public function form(Form $form): Form
    {
        /** @var RotationPattern $pattern */
        $pattern = $this->getOwnerRecord();

        return $form
            ->schema([
                Select::make('filter_branch')
                    ->label('Filtrar por Sucursal')
                    ->options(fn () => Branch::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('employee_ids', []))
                    ->columnSpanFull(),

                Select::make('employee_ids')
                    ->label('Empleados')
                    ->options(function (callable $get) use ($pattern) {
                        $patternId = $pattern->id;
                        $today = Carbon::today();

                        $query = Employee::query()->where('status', 'active');

                        $filterBranch = $get('filter_branch');
                        if ($filterBranch) {
                            $query->where('branch_id', $filterBranch);
                        }

                        return $query
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get()
                            ->mapWithKeys(function (Employee $employee) use ($patternId, $today) {
                                $current = $employee->rotationAssignments()
                                    ->forDate($today)
                                    ->with('pattern')
                                    ->first();

                                if ($current?->pattern_id === $patternId) {
                                    return [];
                                }

                                $label = "{$employee->full_name} - CI: {$employee->ci}";
                                if ($current) {
                                    $label .= " ⚠ (Rotación actual: {$current->pattern?->name})";
                                }

                                return [$employee->id => $label];
                            })
                            ->filter();
                    })
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->helperText('⚠ Los empleados con rotación actual la cambiarán a este patrón')
                    ->columnSpanFull(),

                Select::make('start_index')
                    ->label('Empieza en')
                    ->options(function () use ($pattern) {
                        $shiftIds = collect($pattern->sequence ?? []);
                        $shifts = ShiftTemplate::whereIn('id', $shiftIds->unique())->get()->keyBy('id');

                        return $shiftIds
                            ->values()
                            ->mapWithKeys(function (int $shiftId, int $index) use ($shifts) {
                                $shift = $shifts->get($shiftId);
                                $label = $shift
                                    ? ($shift->is_day_off ? $shift->name : "{$shift->name} ({$shift->start_time}–{$shift->end_time})")
                                    : 'Turno';

                                return [$index => 'Día '.($index + 1).": {$label}"];
                            });
                    })
                    ->default(0)
                    ->native(false)
                    ->required()
                    ->helperText('El mismo día de inicio se aplica a todos los empleados seleccionados. Para desfasar a alguien, asignalo aparte.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tabla de empleados asignados actualmente al patrón, con acciones para asignar y remover.
     */
    public function table(Table $table): Table
    {
        /** @var RotationPattern $pattern */
        $pattern = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('first_name')
            ->recordUrl(fn (Employee $record) => EmployeeResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->addSelect([
                    'employees.*',
                    'rotation_assignments.valid_from as assignment_start_date',
                    'rotation_assignments.start_index as assignment_start_index',
                    'users.name as assignment_created_by',
                ])
                ->leftJoin('users', 'users.id', '=', 'rotation_assignments.created_by_id')
                ->with(['activeContract.position.department', 'branch']))
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->avatar_url),

                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->getStateUsing(fn (Employee $record) => $record->first_name.' '.$record->last_name)
                    ->description(fn (Employee $record) => 'CI: '.$record->ci)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                    )
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('activeContract.position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->default('-')
                    ->badge()
                    ->color('info'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->default('-')
                    ->badge()
                    ->color('success'),

                TextColumn::make('assignment_start_date')
                    ->label('Vigente desde')
                    ->description(fn ($record) => Carbon::parse($record->assignment_start_date)->diffForHumans())
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('assignment_start_index')
                    ->label('Día de inicio')
                    ->getStateUsing(function ($record) use ($pattern) {
                        $index = (int) $record->assignment_start_index;
                        $shiftId = $pattern->sequence[$index] ?? null;
                        $shift = $shiftId ? ShiftTemplate::find($shiftId) : null;

                        return 'Día '.($index + 1).($shift ? ": {$shift->name}" : '');
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('assignment_created_by')
                    ->label('Asignado por')
                    ->icon('heroicon-o-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Asignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading('Asignar Empleados al Patrón')
                    ->modalSubmitActionLabel('Asignar')
                    ->modalWidth('2xl')
                    ->form(fn () => $this->form($this->makeForm())->getComponents())
                    ->action(function (array $data) use ($pattern) {
                        $employees = Employee::whereIn('id', $data['employee_ids'])->get();
                        $assigned = 0;
                        $errors = [];

                        foreach ($employees as $employee) {
                            try {
                                RotationService::assign(
                                    employee: $employee,
                                    pattern: $pattern,
                                    validFrom: Carbon::today(),
                                    startIndex: (int) $data['start_index'],
                                );
                                $assigned++;
                            } catch (\Exception) {
                                $errors[] = $employee->full_name;
                            }
                        }

                        if ($assigned > 0) {
                            Notification::make()
                                ->success()
                                ->title('Empleados asignados')
                                ->body("Se asignó \"{$pattern->name}\" a {$assigned} empleado(s) a partir de hoy.")
                                ->send();
                        }

                        if (! empty($errors)) {
                            Notification::make()
                                ->warning()
                                ->title('Algunos empleados no pudieron asignarse')
                                ->body('Revisar solapamientos en: '.implode(', ', $errors))
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('remove')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remover Empleado del Patrón')
                    ->modalDescription(fn ($record) => "¿Está seguro de que desea remover a {$record->full_name} de este patrón de rotación?")
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (Employee $record) {
                        RotationService::closeActive($record, Carbon::today());

                        Notification::make()
                            ->success()
                            ->title('Empleado removido')
                            ->body("Se cerró la asignación de {$record->full_name} con fecha de hoy.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('remove')
                    ->label('Remover seleccionados')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remover Empleados del Patrón')
                    ->modalDescription('¿Está seguro de que desea remover los empleados seleccionados de este patrón de rotación?')
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function ($records) {
                        $count = $records->count();

                        foreach ($records as $record) {
                            RotationService::closeActive($record, Carbon::today());
                        }

                        Notification::make()
                            ->success()
                            ->title('Empleados removidos')
                            ->body("Se cerró la asignación de {$count} empleado(s) con fecha de hoy.")
                            ->send();
                    }),
            ])
            ->defaultSort('assignment_start_date', 'desc')
            ->emptyStateHeading('No hay empleados asignados')
            ->emptyStateDescription('Comience asignando empleados a este patrón.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
