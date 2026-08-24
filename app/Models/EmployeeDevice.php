<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de dispositivos personales vinculados por un empleado para
 * marcación offline vía PWA (ver `Employee::claimMobileToken()`). A
 * diferencia de `Terminal` (un solo registro por kiosko, editado a mano),
 * acá cada vinculación real crea un registro nuevo — `unlinked_at` null
 * identifica el dispositivo actualmente activo (a lo sumo uno por
 * empleado). Marca/modelo/serie/MAC/notas quedan vacíos hasta que un
 * admin los completa a mano, igual que en `Terminal`.
 */
class EmployeeDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'linked_at',
        'unlinked_at',
        'user_agent',
        'device_brand',
        'device_model',
        'device_serial',
        'device_mac',
        'device_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Indica si este es el dispositivo actualmente vinculado (no reemplazado ni revocado). */
    public function isActive(): bool
    {
        return $this->unlinked_at === null;
    }

    /** Estado derivado de `unlinked_at`, para mostrar como badge (ver getStatusLabels/Colors). */
    public function getStatusAttribute(): string
    {
        return $this->isActive() ? 'active' : 'unlinked';
    }

    /** Descripción del dispositivo (marca + modelo) — mismo patrón que `Terminal::device_description`. */
    public function getDeviceDescriptionAttribute(): ?string
    {
        $parts = array_filter([$this->device_brand, $this->device_model]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return ['active' => 'Vinculado', 'unlinked' => 'Desvinculado'];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return ['active' => 'success', 'unlinked' => 'gray'];
    }
}
