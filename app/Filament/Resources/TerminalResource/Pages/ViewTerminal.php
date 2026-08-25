<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use App\Models\Terminal;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Vista de detalle de una terminal con QR de acceso y acciones de ciclo de vida. */
class ViewTerminal extends ViewRecord
{
    protected static string $resource = TerminalResource::class;

    /**
     * Al llegar desde la creación con `?provision=1` (ver
     * CreateTerminal::getRedirectUrl()), abre automáticamente el modal de
     * "Generar enlace de configuración" — evita que el admin tenga que volver
     * a la lista para conseguir el QR justo después de crear el terminal.
     *
     * No puede hacerse en mount(): `mountAction()` resuelve la acción contra
     * `cachedActions`, que recién se puebla en el hook de Livewire
     * `bootedInteractsWithHeaderActions()` — que corre después de mount().
     * Llamarlo ahí antes de tiempo hace que `getMountedAction()` no encuentre
     * la acción y la desmonte de inmediato, sin abrir el modal.
     */
    public function bootedInteractsWithHeaderActions(): void
    {
        parent::bootedInteractsWithHeaderActions();

        if (request()->boolean('provision')) {
            $this->mountAction('generate_setup_link');
        }
    }

    /**
     * Acciones del encabezado de la página de detalle.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->isInactive())
                ->requiresConfirmation()
                ->modalHeading('Activar terminal')
                ->modalDescription('La terminal volverá a estar disponible para marcaciones.')
                ->modalSubmitActionLabel('Sí, activar')
                ->action(function () {
                    $this->record->update(['status' => 'active']);
                    Notification::make()->success()->title('Terminal activada')->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('deactivate')
                ->label('Desactivar')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Desactivar terminal')
                ->modalDescription('La terminal dejará de aceptar marcaciones y mostrará una pantalla de fuera de servicio.')
                ->modalSubmitActionLabel('Sí, desactivar')
                ->action(function () {
                    $this->record->update(['status' => 'inactive']);
                    Notification::make()->warning()->title('Terminal desactivada')->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('generate_setup_link')
                ->label('Generar enlace de configuración')
                ->tooltip('Enlace/QR de un solo uso para vincular el dispositivo a la sincronización offline')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->modalHeading('Enlace de configuración del terminal')
                ->modalContent(fn () => TerminalResource::renderSetupLinkModal($this->record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            EditAction::make()->label('Editar')->icon('heroicon-o-pencil-square')->color('primary'),

            ActionGroup::make([
                Action::make('regenerate_code')
                    ->label('Regenerar código')
                    ->tooltip('Cambia la URL pública del terminal — el dispositivo físico deberá reconfigurarse con la nueva URL')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar código de acceso')
                    ->modalDescription('⚠️ Esto cambiará la URL de la terminal. El dispositivo físico dejará de funcionar hasta que sea reconfigurado con la nueva URL.')
                    ->modalSubmitActionLabel('Sí, regenerar')
                    ->action(function () {
                        $newCode = Terminal::generateUniqueCode();
                        $this->record->update(['code' => $newCode]);

                        Notification::make()
                            ->warning()
                            ->title('Código regenerado')
                            ->body('Recordá actualizar la URL en el dispositivo físico.')
                            ->send();

                        $this->refreshFormData(['code']);
                    }),

                Action::make('revoke_token')
                    ->label('Revocar token')
                    ->tooltip('Invalida el acceso del terminal a la sincronización offline — requerirá re-provisión')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn () => $this->record->tokens()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Revocar token de sincronización')
                    ->modalDescription('El terminal perderá acceso a la API de sincronización offline de inmediato. Deberá re-provisionarse con un nuevo enlace de configuración antes de volver a sincronizar.')
                    ->modalSubmitActionLabel('Sí, revocar')
                    ->action(function () {
                        $this->record->revokeSyncTokens();
                        Notification::make()
                            ->success()
                            ->title('Token revocado')
                            ->body('El terminal deberá re-provisionarse para volver a sincronizar.')
                            ->send();
                    }),

                DeleteAction::make()->icon('heroicon-o-trash'),
            ])
                ->label('Más acciones')
                ->icon('heroicon-m-chevron-down')
                ->color('gray')
                ->button(),
        ];
    }
}
