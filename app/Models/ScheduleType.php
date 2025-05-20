<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleType extends Model
{
    protected $table = 'schedule_types';

    protected $fillable = ['name',];

    // Un tipo de turno tiene muchos horarios de día
    public function daySchedules(): HasMany
    {
        return $this->hasMany(DaySchedule::class);
    }

    // Un tipo de turno tiene muchos periodos de descanso
    public function breakPeriods(): HasMany
    {
        return $this->hasMany(BreakPeriod::class);
    }

    // Un tipo de turno se asigna a muchos empleados
    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }
}
