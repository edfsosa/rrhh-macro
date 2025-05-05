<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeductions extends ManageRecords
{
    protected static string $resource = DeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
