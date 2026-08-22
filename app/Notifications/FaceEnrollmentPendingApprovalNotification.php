<?php

namespace App\Notifications;

use App\Filament\Resources\FaceEnrollmentResource;
use App\Models\FaceEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando un empleado completa el autoenrolamiento
 * facial (`/registro-facial`) y el registro queda en `pending_approval`.
 * Sin esto, la única forma de enterarse de que hay una solicitud esperando
 * revisión era entrar a `FaceEnrollmentResource` a mirar manualmente.
 */
class FaceEnrollmentPendingApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly FaceEnrollment $enrollment,
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
        $employee = $this->enrollment->employee;
        $employeeLabel = $employee ? "{$employee->full_name} (CI: {$employee->ci})" : 'un empleado';

        return [
            'title' => 'Autoenrolamiento facial pendiente de aprobación',
            'body' => "{$employeeLabel} completó la captura de su rostro y está esperando que apruebes o rechaces la solicitud.",
            'icon' => 'heroicon-o-face-smile',
            'color' => 'warning',
            'actions' => [
                [
                    'label' => 'Revisar solicitud',
                    'url' => FaceEnrollmentResource::getUrl('index'),
                ],
            ],
            'enrollment_id' => $this->enrollment->id,
        ];
    }
}
