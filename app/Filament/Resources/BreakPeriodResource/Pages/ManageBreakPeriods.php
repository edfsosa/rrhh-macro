<?php

namespace App\Filament\Resources\BreakPeriodResource\Pages;

use App\Filament\Resources\BreakPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBreakPeriods extends ManageRecords
{
    protected static string $resource = BreakPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
