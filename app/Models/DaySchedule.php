<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DaySchedule extends Model
{
    protected $table = 'day_schedules';

    protected $fillable = [
        'schedule_type_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function scheduleType()
    {
        return $this->belongsTo(ScheduleType::class);
    }
}
