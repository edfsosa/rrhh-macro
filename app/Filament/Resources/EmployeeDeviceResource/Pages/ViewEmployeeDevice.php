<?php

namespace App\Filament\Resources\EmployeeDeviceResource\Pages;

use App\Filament\Resources\EmployeeDeviceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Detalle de un dispositivo vinculado — solo permite editar marca/modelo/serie/MAC/notas. */
class ViewEmployeeDevice extends ViewRecord
{
    protected static string $resource = EmployeeDeviceResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-o-pencil-square'),
        ];
    }
}
