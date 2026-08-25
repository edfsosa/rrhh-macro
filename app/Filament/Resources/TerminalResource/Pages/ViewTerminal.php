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

            // "Ver enlace" (hay uno vigente sin usar): solo lectura, sin efecto colateral.
            Action::make('view_setup_link')
                ->label('Ver enlace de configuración')
                ->tooltip('Ver el enlace/QR de un solo uso todavía vigente, sin invalidarlo')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn () => TerminalResource::hasValidSetupLink($this->record))
                ->modalHeading('Enlace de configuración del terminal')
                ->modalContent(fn () => TerminalResource::renderCurrentSetupLinkModal($this->record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            // "Generar enlace" (sin enlace vigente): un solo paso, sin confirmación —
            // abre el modal y genera+muestra el QR de inmediato, como ya funcionaba.
            Action::make('generate_setup_link')
                ->label('Generar enlace de configuración')
                ->tooltip('Enlace/QR de un solo uso para vincular el dispositivo a la sincronización offline')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn () => ! TerminalResource::hasValidSetupLink($this->record))
                ->modalHeading('Enlace de configuración del terminal')
                ->modalDescription(fn () => $this->record->tokens()->exists()
                    ? '⚠️ Este terminal ya está vinculado y sincronizando. Si otro dispositivo reclama este enlace, el acceso del terminal actual se revocará automáticamente.'
                    : null)
                ->modalContent(fn () => TerminalResource::renderSetupLinkModal($this->record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            // "Generar nuevo enlace" (ya hay uno vigente): pide confirmación explícita
            // ANTES de generar — modalContent() se evalúa (con su efecto colateral) al
            // abrir el modal, no al confirmar, así que no se puede mostrar el QR nuevo
            // en el mismo paso sin invalidar el vigente antes de que el admin decida.
            // Genera y avisa por notificación; el QR nuevo se ve con "Ver enlace".
            Action::make('regenerate_setup_link')
                ->label('Generar nuevo enlace de configuración')
                ->tooltip('Invalida el enlace vigente y genera uno nuevo')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => TerminalResource::hasValidSetupLink($this->record))
                ->requiresConfirmation()
                ->modalHeading('¿Generar un enlace nuevo?')
                ->modalDescription(function () {
                    $base = 'Ya existe un enlace de configuración vigente para este terminal. Generar uno nuevo invalida el anterior de inmediato, aunque todavía no haya sido usado.';
                    if ($this->record->tokens()->exists()) {
                        $base .= ' ⚠️ Además, este terminal ya está vinculado y sincronizando — si otro dispositivo reclama el enlace nuevo, el acceso del terminal actual se revocará automáticamente.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Sí, generar uno nuevo')
                ->action(function () {
                    $this->record->generateSetupToken(30);
                    Notification::make()
                        ->success()
                        ->title('Enlace nuevo generado')
                        ->body('El enlace anterior quedó invalidado. Usá "Ver enlace de configuración" para verlo.')
                        ->send();
                }),

            EditAction::make()->label('Editar')->icon('heroicon-o-pencil-square')->color('primary'),

            ActionGroup::make([
                Action::make('regenerate_code')
                    ->label('Cambiar URL del terminal')
                    ->tooltip('Cambia la URL pública del terminal — el dispositivo físico deberá reconfigurarse con la nueva URL')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cambiar URL del terminal')
                    ->modalDescription('⚠️ Esto cambiará la URL pública de la terminal. El dispositivo físico dejará de funcionar hasta que sea reconfigurado con la nueva URL. Esto NO afecta el token de sincronización — el terminal seguirá conectado a la API mientras tanto.')
                    ->modalSubmitActionLabel('Sí, cambiar')
                    ->action(function () {
                        $newCode = Terminal::generateUniqueCode();
                        $this->record->update(['code' => $newCode]);

                        Notification::make()
                            ->warning()
                            ->title('URL del terminal actualizada')
                            ->body('Recordá reconfigurar el dispositivo físico con la nueva URL.')
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
                    ->modalDescription('El terminal perderá acceso a la API de sincronización offline de inmediato. Deberá re-provisionarse con un nuevo enlace de configuración antes de volver a sincronizar. El código y la URL del terminal no cambian.')
                    ->modalSubmitActionLabel('Sí, revocar')
                    ->action(function () {
                        $this->record->revokeSyncTokens();
                        Notification::make()
                            ->success()
                            ->title('Token revocado')
                            ->body('El terminal deberá re-provisionarse para volver a sincronizar.')
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->modalDescription('Esta acción no se puede deshacer. Las marcaciones ya registradas con este terminal no se eliminan, pero perderán la referencia a qué dispositivo físico las generó.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
                ->label('Más acciones')
                ->icon('heroicon-m-chevron-down')
                ->color('gray')
                ->button(),
        ];
    }
}
