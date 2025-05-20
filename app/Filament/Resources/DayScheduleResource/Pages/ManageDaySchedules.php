<?php

namespace App\Filament\Resources\DayScheduleResource\Pages;

use App\Filament\Resources\DayScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDaySchedules extends ManageRecords
{
    protected static string $resource = DayScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
