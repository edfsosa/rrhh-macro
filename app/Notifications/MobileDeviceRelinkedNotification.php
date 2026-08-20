<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando el celular vinculado de un empleado se
 * re-vincula (había un dispositivo ya vinculado y se reemplazó por otro).
 *
 * CI + fecha de nacimiento es una credencial débil (baja entropía) — alguien
 * con esos datos de otro empleado podría re-vincular su propio celular y así
 * revocar silenciosamente el dispositivo legítimo (denegación de servicio
 * dirigida). Esta notificación no previene el ataque, pero da visibilidad
 * para que un admin investigue una re-vinculación que el empleado no
 * reconoce (ver runbook de vinculación/revocación de celulares).
 */
class MobileDeviceRelinkedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Employee $employee,
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
        return [
            'title' => 'Celular re-vinculado',
            'body' => "El celular vinculado de {$this->employee->full_name} (CI: {$this->employee->ci}) fue reemplazado por uno nuevo. Si el empleado no reconoce este cambio, revocá el acceso desde su ficha.",
            'icon' => 'heroicon-o-device-phone-mobile',
            'color' => 'warning',
            'actions' => [
                [
                    'label' => 'Ver empleado',
                    'url' => "/admin/empleados/{$this->employee->id}",
                ],
            ],
            'employee_id' => $this->employee->id,
        ];
    }
}
