<?php

namespace App\Filament\Resources\AttendanceMarkFailureResource\Widgets;

use App\Filament\Resources\EmployeeResource;
use App\Models\AttendanceMarkFailure;
use App\Models\Employee;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Widget de tabla: empleados con más fallos de marcación facial en los últimos días.
 * Permite detectar proactivamente a quién le conviene re-inscribir su rostro, en vez
 * de esperar el reclamo. Vive dentro del namespace del resource (no en Widgets/ global)
 * para no aparecer en el Dashboard general — solo se adjunta al listado de fallos.
 */
class TopFailingEmployeesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Empleados con más fallos de marcación (últimos 30 días)';

    /** Días hacia atrás considerados en el reporte. */
    protected int $days = 30;

    /** Cantidad mínima de fallos para aparecer en el listado — evita ruido de casos aislados. */
    protected int $minFailures = 2;

    /** Cantidad máxima de empleados mostrados. */
    protected int $limit = 15;

    public function table(Table $table): Table
    {
        // Una sola query agregada — nunca un COUNT por empleado dentro del render de la tabla.
        $counts = AttendanceMarkFailure::query()
            ->where('occurred_at', '>=', now()->subDays($this->days))
            ->whereNotNull('employee_id')
            ->selectRaw('employee_id, COUNT(*) as total_failures, MAX(occurred_at) as last_failure_at')
            ->groupBy('employee_id')
            ->having('total_failures', '>=', $this->minFailures)
            ->orderByDesc('total_failures')
            ->limit($this->limit)
            ->get()
            ->keyBy('employee_id');

        return $table
            ->query($this->buildOrderedEmployeeQuery($counts))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Empleado')
                    ->getStateUsing(fn (Employee $record) => "{$record->first_name} {$record->last_name}")
                    ->description(fn (Employee $record) => $record->ci ? "CI: {$record->ci}" : null)
                    ->weight('medium'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('Sin sucursal')
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_failures')
                    ->label('Fallos')
                    ->getStateUsing(fn (Employee $record) => $counts->get($record->id)?->total_failures ?? 0)
                    ->badge()
                    ->color('danger'),

                TextColumn::make('last_failure_at')
                    ->label('Último fallo')
                    ->getStateUsing(fn (Employee $record) => $counts->get($record->id)?->last_failure_at)
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Employee $record) => EmployeeResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Sin empleados con fallos recurrentes')
            ->emptyStateDescription("Ningún empleado acumuló {$this->minFailures} o más fallos de marcación en los últimos {$this->days} días.")
            ->emptyStateIcon('heroicon-o-face-smile');
    }

    /**
     * Arma la query de empleados ordenada por cantidad de fallos (descendente), usando
     * el orden ya calculado en $counts en vez de dejar que whereIn() reordene libremente.
     *
     * @param  Collection<int, object{employee_id: int, total_failures: int, last_failure_at: string}>  $counts
     */
    private function buildOrderedEmployeeQuery(Collection $counts): Builder
    {
        $employeeIds = $counts->keys();

        $query = Employee::query()->whereIn('id', $employeeIds);

        if ($employeeIds->isNotEmpty()) {
            $query->orderByRaw('FIELD(id, '.$employeeIds->implode(',').')');
        }

        return $query;
    }
}
