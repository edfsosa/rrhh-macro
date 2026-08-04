<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Traits\HasVigenciaActions;
use App\Models\EmployeePerception;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Gestiona las percepciones asignadas al empleado desde su vista de detalle. */
class EmployeePerceptionsRelationManager extends RelationManager
{
    use HasVigenciaActions;

    protected static string $relationship = 'employeePerceptions';

    protected static ?string $title = 'Percepciones';

    protected static ?string $modelLabel = 'Percepción';

    protected static ?string $pluralModelLabel = 'Percepciones';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getEntityField(): string
    {
        return 'perception_id';
    }

    protected function getEntityName(): string
    {
        return 'Percepción';
    }

    protected function getEntityModelClass(): string
    {
        return EmployeePerception::class;
    }

    /**
     * Define el formulario para crear/editar asignaciones de percepciones a empleados.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('perception_id')
                    ->label('Percepción')
                    ->relationship(
                        name: 'perception',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => $record->name.' ('.
                            ($record->isFixed()
                                ? '₲ '.number_format($record->amount, 0, ',', '.')
                                : $record->percent.'%'
                            ).')'
                    )
                    ->required()
                    ->searchable(['name', 'code'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('custom_amount', null))
                    ->hiddenOn('edit')
                    ->helperText('Solo se pueden asignar percepciones que estén activas en el sistema.'),

                ...$this->vigenciaFormSchema(),
            ])
            ->columns(2);
    }

    /**
     * Define la tabla para listar las percepciones asignadas a un empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('perception'))
            ->columns([
                TextColumn::make('perception.code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Copiar código al portapapeles')
                    ->copyMessage('Código copiado al portapapeles')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('perception.name')
                    ->label('Percepción')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                $this->vigenciaCalculationColumn(),
                $this->vigenciaAmountColumn(),
                ...$this->vigenciaDateColumns(),
            ])
            ->filters([$this->vigenciaEstadoFilter()])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar percepción')
                    ->icon('heroicon-o-plus')
                    ->before($this->vigenciaCreateBefore())
                    ->successNotificationTitle('Percepción asignada exitosamente'),
            ])
            ->actions([
                $this->vigenciaDeactivateAction(),
                $this->vigenciaReactivateAction(),
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->modalHeading('Editar Asignación')
                        ->before($this->vigenciaEditBefore())
                        ->successNotificationTitle('Asignación actualizada exitosamente'),

                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->modalHeading('¿Eliminar percepción?')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalDescription('¿Está seguro de que desea eliminar permanentemente esta asignación del historial?')
                        ->successNotificationTitle('Asignación eliminada del historial'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    $this->vigenciaDeactivateBulkAction(),
                    $this->vigenciaReactivateBulkAction(),
                    DeleteBulkAction::make()
                        ->label('Borrar del historial')
                        ->modalHeading('Borrar Asignaciones')
                        ->modalDescription('¿Está seguro de que desea borrar permanentemente estas asignaciones del historial?')
                        ->successNotificationTitle('Asignaciones borradas del historial'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay percepciones')
            ->emptyStateDescription('Comienza agregando una percepción al empleado')
            ->emptyStateIcon('heroicon-o-plus-circle');
    }
}
