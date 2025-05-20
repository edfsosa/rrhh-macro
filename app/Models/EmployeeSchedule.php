<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    protected $table = 'employee_schedules';

    protected $fillable = [
        'employee_id',
        'schedule_type_id',
        'valid_from',
        'valid_to',
    ];

    // Relación con el modelo ScheduleType
    public function scheduleType()
    {
        return $this->belongsTo(ScheduleType::class);
    }

    // Relación con el modelo Employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
