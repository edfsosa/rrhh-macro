<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Patrón de rotación: ciclo ordenado de turnos definido como secuencia JSON de IDs. */
class RotationPattern extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'sequence',
        'is_active',
    ];

    protected $casts = [
        'sequence' => 'array',
        'is_active' => 'boolean',
    ];

    /** Empresa propietaria del patrón. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Asignaciones de empleados que usan este patrón. */
    public function assignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class, 'pattern_id');
    }

    /**
     * Empleados con asignación de rotación activa a este patrón hoy —
     * misma lógica que Schedule::currentEmployees(), adaptada a
     * rotation_assignments/pattern_id.
     */
    public function currentEmployees(): HasManyThrough
    {
        $today = Carbon::today()->toDateString();

        return $this->hasManyThrough(
            Employee::class,
            RotationAssignment::class,
            'pattern_id',   // FK en rotation_assignments
            'id',           // FK en employees
            'id',           // PK en rotation_patterns
            'employee_id',  // PK local en rotation_assignments
        )
            ->where('rotation_assignments.valid_from', '<=', $today)
            ->where(fn ($q) => $q
                ->whereNull('rotation_assignments.valid_until')
                ->orWhere('rotation_assignments.valid_until', '>=', $today)
            );
    }

    /**
     * Largo del ciclo en días (derivado del array sequence).
     */
    public function getCycleLengthAttribute(): int
    {
        return count($this->sequence ?? []);
    }

    /**
     * Retorna el ShiftTemplate ID para la posición dada del ciclo.
     *
     * @param  int  $position  Posición 0-based dentro del ciclo.
     */
    public function shiftIdAtPosition(int $position): ?int
    {
        $seq = $this->sequence ?? [];

        if (empty($seq)) {
            return null;
        }

        return $seq[$position % count($seq)] ?? null;
    }
}
