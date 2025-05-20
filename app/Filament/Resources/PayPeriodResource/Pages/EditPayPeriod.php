<?php

namespace App\Filament\Resources\PayPeriodResource\Pages;

use App\Filament\Resources\PayPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayPeriod extends EditRecord
{
    protected static string $resource = PayPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
