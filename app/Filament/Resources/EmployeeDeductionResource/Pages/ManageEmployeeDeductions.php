<?php

namespace App\Filament\Resources\EmployeeDeductionResource\Pages;

use App\Filament\Resources\EmployeeDeductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeDeductions extends ManageRecords
{
    protected static string $resource = EmployeeDeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
