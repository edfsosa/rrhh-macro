<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Exports\AttendanceExport;
use App\Filament\Resources\AttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Maatwebsite\Excel\Facades\Excel;

class ManageAttendances extends ManageRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new AttendanceExport, 'marcaciones.xlsx');
                }),

        ];
    }
}
