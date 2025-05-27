<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // verifica el first_name y last_name para convertir a mayúsculas
        $data['first_name'] = strtoupper($data['first_name']);
        $data['last_name'] = strtoupper($data['last_name']);

        // verifica el email ingresado y lo convierte a minusculas
        $data['email'] = strtolower($data['email']);

        // verifica el ci, phone y salario ingresado y en caso de que tenga 0 al inicio lo elimina
        $data['ci'] = ltrim($data['ci'], '0');
        $data['phone'] = ltrim($data['phone'], '0');
        $data['base_salary'] = ltrim($data['base_salary'], '0');

        return $data;
    }

    // Recarga la pagina al guardar
    protected function afterSave(): void
    {
        $this->redirect($this->getResource()::getUrl('edit', [
            'record' => $this->record,
        ]));
    }

    // Personaliza el mensaje de guardado
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Empleado actualizado correctamente';
    }
}
