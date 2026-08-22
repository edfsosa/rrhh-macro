<?php

namespace App\Notifications;

use App\Filament\Resources\TerminalResource;
use App\Models\Terminal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación a los admins cuando un kiosko/terminal completa su
 * provisión (reclama el enlace de configuración de un solo uso y recibe
 * su token Sanctum) — confirma que la instalación física salió bien sin
 * que un admin tenga que entrar a revisar `last_heartbeat_at` a mano.
 */
class TerminalProvisionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Terminal $terminal,
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
            'title' => 'Terminal provisionado',
            'body' => "El terminal \"{$this->terminal->name}\" ({$this->terminal->branch?->name}) completó su configuración y ya está listo para marcar asistencia.",
            'icon' => 'heroicon-o-computer-desktop',
            'color' => 'success',
            'actions' => [
                [
                    'label' => 'Ver terminal',
                    'url' => TerminalResource::getUrl('view', ['record' => $this->terminal]),
                ],
            ],
            'terminal_id' => $this->terminal->id,
        ];
    }
}
