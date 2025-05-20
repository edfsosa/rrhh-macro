<?php

namespace App\Filament\Resources\DeductionTypeResource\Pages;

use App\Filament\Resources\DeductionTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeductionTypes extends ManageRecords
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
