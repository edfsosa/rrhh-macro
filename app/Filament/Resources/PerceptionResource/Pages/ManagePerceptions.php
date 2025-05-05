<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePerceptions extends ManageRecords
{
    protected static string $resource = PerceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
