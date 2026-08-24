<?php

namespace App\Filament\Resources\EmployeeDeviceResource\Pages;

use App\Filament\Resources\EmployeeDeviceResource;
use Filament\Resources\Pages\ListRecords;

/** Lista de dispositivos personales vinculados por empleados (histórico) — sin creación manual. */
class ListEmployeeDevices extends ListRecords
{
    protected static string $resource = EmployeeDeviceResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
