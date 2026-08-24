<?php

namespace App\Notifications;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando un empleado vincula su dispositivo por
 * primera vez (sin dispositivo previo) para marcación offline. A diferencia
 * de `MobileDeviceRelinkedNotification` (tono de advertencia, posible
 * incidente de seguridad), esta es puramente informativa.
 */
class MobileDeviceLinkedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Employee $employee,
        public readonly ?string $userAgent = null,
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
        $body = "{$this->employee->full_name} (CI: {$this->employee->ci}) vinculó su dispositivo para marcar asistencia offline.";

        if (filled($this->userAgent)) {
            $body .= " Dispositivo: {$this->userAgent}";
        }

        return [
            'title' => 'Dispositivo vinculado',
            'body' => $body,
            'icon' => 'heroicon-o-device-phone-mobile',
            'color' => 'success',
            'actions' => [
                [
                    'label' => 'Ver empleado',
                    'url' => EmployeeResource::getUrl('view', ['record' => $this->employee]),
                ],
            ],
            'employee_id' => $this->employee->id,
        ];
    }
}
