<?php

namespace App\Notifications;

use App\Filament\Resources\AttendanceMarkFailureResource;
use App\Models\AttendanceMarkFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando se registra un conflicto de
 * sincronización offline (`AttendanceMarkFailure` con `failure_type:
 * sync_conflict`) — kiosko o dispositivo sincronizó una marcación que ya no es
 * válida contra el estado actual del servidor. Sin esto, el registro queda
 * en `AttendanceMarkFailureResource` sin que nadie se entere hasta que un
 * admin entra a mirar manualmente — justo el gap identificado al probar en
 * producción.
 */
class SyncConflictPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AttendanceMarkFailure $failure,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $employee = $this->failure->employee;
        $employeeLabel = $employee ? "{$employee->full_name} (CI: {$employee->ci})" : 'un empleado';
        $modeLabel = AttendanceMarkFailure::getModeLabel($this->failure->mode);

        return [
            'title' => 'Conflicto al sincronizar una marcación offline',
            'body' => "Una marcación de {$employeeLabel} desde {$modeLabel} no se pudo aplicar al reconectarse — la secuencia ya no era válida en el servidor. Requiere revisión manual.",
            'icon' => 'heroicon-o-exclamation-triangle',
            'color' => 'danger',
            'actions' => [
                [
                    'label' => 'Revisar conflicto',
                    'url' => AttendanceMarkFailureResource::getUrl('view', ['record' => $this->failure]),
                ],
            ],
            'failure_id' => $this->failure->id,
        ];
    }
}
