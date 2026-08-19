<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de intentos fallidos de marcación de asistencia.
 *
 * Persiste en BD todos los fallos del proceso de marcación (facial, terminal y móvil)
 * para permitir auditoría, diagnóstico y visualización en el panel de administración.
 * Los registros se retienen 30 días y se limpian automáticamente.
 */
class AttendanceMarkFailure extends Model
{
    protected $fillable = [
        'mode',
        'failure_type',
        'employee_id',
        'branch_id',
        'attempted_event_type',
        'failure_message',
        'metadata',
        'ip_address',
        'location',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'location' => 'array',
        'occurred_at' => 'datetime',
    ];

    // ──────────────────────────────────────────
    // Relaciones
    // ──────────────────────────────────────────

    /** Empleado involucrado en el intento fallido (puede ser null si no se identificó). */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Sucursal desde donde se realizó el intento. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ──────────────────────────────────────────
    // Labels y colores para el panel
    // ──────────────────────────────────────────

    /**
     * Retorna el label legible del tipo de fallo.
     */
    public static function getFailureTypeLabel(string $type): string
    {
        return match ($type) {
            'face_no_match' => 'Rostro no reconocido',
            'face_ambiguous' => 'Rostro ambiguo',
            'face_no_candidates' => 'Sin empleados enrolados',
            'face_invalid_descriptor' => 'Descriptor facial inválido',
            'employee_not_found' => 'Empleado no encontrado',
            'employee_inactive' => 'Empleado inactivo',
            'employee_no_branch' => 'Sin sucursal asignada',
            'branch_no_coordinates' => 'Sucursal sin coordenadas',
            'invalid_event_sequence' => 'Secuencia de evento inválida',
            'invalid_location' => 'Ubicación inválida',
            'internal_error' => 'Error interno',
            'sync_conflict' => 'Conflicto al sincronizar (offline)',
            default => $type,
        };
    }

    /**
     * Retorna el color del badge según el tipo de fallo.
     */
    public static function getFailureTypeColor(string $type): string
    {
        return match ($type) {
            'face_no_match', 'face_ambiguous' => 'warning',
            'face_no_candidates', 'internal_error' => 'danger',
            'employee_not_found', 'employee_inactive' => 'danger',
            'employee_no_branch', 'branch_no_coordinates' => 'warning',
            'invalid_event_sequence' => 'info',
            'invalid_location' => 'warning',
            'face_invalid_descriptor' => 'gray',
            'sync_conflict' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Retorna el label del modo de marcación.
     */
    public static function getModeLabel(string $mode): string
    {
        return match ($mode) {
            'terminal' => 'Terminal',
            'mobile' => 'Móvil',
            default => 'Desconocido',
        };
    }

    /**
     * Retorna el color del badge según el modo.
     */
    public static function getModeColor(string $mode): string
    {
        return match ($mode) {
            'terminal' => 'info',
            'mobile' => 'success',
            default => 'gray',
        };
    }

    /**
     * Retorna todas las opciones de tipo de fallo para filtros.
     *
     * @return array<string, string>
     */
    public static function getFailureTypeOptions(): array
    {
        return [
            'face_no_match' => 'Rostro no reconocido',
            'face_ambiguous' => 'Rostro ambiguo',
            'face_no_candidates' => 'Sin empleados enrolados',
            'face_invalid_descriptor' => 'Descriptor facial inválido',
            'employee_not_found' => 'Empleado no encontrado',
            'employee_inactive' => 'Empleado inactivo',
            'employee_no_branch' => 'Sin sucursal asignada',
            'branch_no_coordinates' => 'Sucursal sin coordenadas',
            'invalid_event_sequence' => 'Secuencia de evento inválida',
            'invalid_location' => 'Ubicación inválida',
            'internal_error' => 'Error interno',
            'sync_conflict' => 'Conflicto al sincronizar (offline)',
        ];
    }

    /**
     * Crea y persiste un registro de fallo de marcación.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(array $data): static
    {
        return static::create(array_merge(
            ['occurred_at' => now()],
            $data,
        ));
    }
}
