<?php

namespace App\Filament\Resources\PerceptionTypeResource\Pages;

use App\Filament\Resources\PerceptionTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePerceptionTypes extends ManageRecords
{
    protected static string $resource = PerceptionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
