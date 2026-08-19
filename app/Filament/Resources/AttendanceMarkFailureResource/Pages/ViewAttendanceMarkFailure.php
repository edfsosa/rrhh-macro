<?php

namespace App\Filament\Resources\AttendanceMarkFailureResource\Pages;

use App\Filament\Resources\AttendanceMarkFailureResource;
use Filament\Resources\Pages\ViewRecord;

/** Página de detalle de un intento fallido de marcación. */
class ViewAttendanceMarkFailure extends ViewRecord
{
    protected static string $resource = AttendanceMarkFailureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AttendanceMarkFailureResource::getApproveAction(),
            AttendanceMarkFailureResource::getDismissAction(),
        ];
    }
}
