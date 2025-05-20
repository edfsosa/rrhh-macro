<?php

namespace App\Filament\Resources\EmployeePerceptionResource\Pages;

use App\Filament\Resources\EmployeePerceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeePerceptions extends ManageRecords
{
    protected static string $resource = EmployeePerceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
