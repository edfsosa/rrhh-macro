<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleType extends Model
{
    protected $table = 'schedule_types';

    protected $fillable = ['name', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Un horario puede tener muchos días
    public function daySchedules(): HasMany
    {
        return $this->hasMany(DaySchedule::class);
    }

    // Un horario puede tener muchos descansos
    public function breakPeriods(): HasMany
    {
        return $this->hasMany(BreakPeriod::class);
    }

    // Un horario puede estar asociado a muchos empleados
    public function employees(): BelongsToMany
    {   
        return $this->belongsToMany(Employee::class);
    }
}
