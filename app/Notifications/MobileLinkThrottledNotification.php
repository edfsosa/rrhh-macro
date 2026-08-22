<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando una IP agota el límite diario de intentos
 * de vinculación de celular (`/vincular-celular`, 15/día). Puede ser un
 * empleado real trabado (CI o fecha de nacimiento mal recordada) o un
 * intento de fuerza bruta contra una credencial de baja entropía — en
 * cualquiera de los dos casos, antes nadie se enteraba hasta que el
 * empleado reclamaba directamente. Se dispara como máximo una vez por
 * IP por día (ver `MobileLinkController::notifyAdminsOfDailyLimitOnce()`).
 */
class MobileLinkThrottledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $ip,
        public readonly ?string $lastCiAttempted,
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
        $ciLabel = filled($this->lastCiAttempted) ? " (último CI intentado: {$this->lastCiAttempted})" : '';

        return [
            'title' => 'Límite diario de vinculación de celular agotado',
            'body' => "La IP {$this->ip} alcanzó el límite de 15 intentos de vinculación hoy{$ciLabel}. Puede ser un empleado con datos incorrectos o un intento de acceso indebido — revisar los logs si es necesario.",
            'icon' => 'heroicon-o-shield-exclamation',
            'color' => 'warning',
        ];
    }
}
