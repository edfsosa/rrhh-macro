<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EmployeeDeviceResource;
use App\Models\EmployeeDevice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Historial de dispositivos personales vinculados por el empleado — solo
 * lectura. Los registros los crea automáticamente
 * `Employee::claimMobileToken()`/`revokeMobileToken()`; para anotar
 * marca/modelo/serie/MAC/notas o revocar, se entra al detalle del
 * dispositivo en `EmployeeDeviceResource`.
 */
class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Dispositivos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (EmployeeDevice $record) => EmployeeDeviceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmployeeDevice::getStatusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => EmployeeDevice::getStatusColors()[$state] ?? 'gray'),

                TextColumn::make('device_description')
                    ->label('Dispositivo')
                    ->placeholder('Sin datos'),

                TextColumn::make('linked_at')
                    ->label('Vinculado')
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->sortable(),

                TextColumn::make('unlinked_at')
                    ->label('Desvinculado')
                    ->since()
                    ->placeholder('Sigue vinculado')
                    ->sortable(),
            ])
            ->defaultSort('linked_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Sin dispositivos vinculados')
            ->emptyStateDescription('Los dispositivos aparecen acá automáticamente cuando el empleado se vincula desde /vincular-dispositivo.')
            ->emptyStateIcon('heroicon-o-device-phone-mobile');
    }
}
