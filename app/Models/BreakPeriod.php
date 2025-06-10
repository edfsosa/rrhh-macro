<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BreakPeriod extends Model
{
    protected $table = 'break_periods';

    protected $fillable = [
        'schedule_type_id',
        'name',
        'start_time',
        'end_time',
    ];

    // Cada descanso está vinculado a un ScheduleType
    public function scheduleType()
    {
        return $this->belongsTo(ScheduleType::class);
    }

    // Método para obtener la duración del periodo de descanso
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return $end->diffInMinutes($start);
    }
}
