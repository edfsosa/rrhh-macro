<?php

namespace App\Filament\Resources\PayPeriodResource\Pages;

use App\Filament\Resources\PayPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayPeriods extends ListRecords
{
    protected static string $resource = PayPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
