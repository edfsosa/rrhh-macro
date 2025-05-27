<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
    protected function afterCreate(): void
    {
        $this->redirect($this->getResource()::getUrl('index', [
            'record' => $this->record,
        ]));
    }

    // Personaliza el mensaje de guardado
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Empleado creado correctamente';
    }
}
