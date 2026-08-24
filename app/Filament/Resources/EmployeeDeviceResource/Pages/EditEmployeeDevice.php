<?php

namespace App\Filament\Resources\EmployeeDeviceResource\Pages;

use App\Filament\Resources\EmployeeDeviceResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Edición de un dispositivo vinculado — solo los campos anotables a mano
 * (marca/modelo/serie/MAC/notas). Sin DeleteAction: es un registro de
 * historial, no se elimina — el ciclo de vida real (vincular/revocar) lo
 * maneja `Employee::claimMobileToken()`/`revokeMobileToken()`.
 */
class EditEmployeeDevice extends EditRecord
{
    protected static string $resource = EmployeeDeviceResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
