<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePerception extends Model
{
    protected $fillable = [
        'employee_id',
        'perception_type_id',
        'start_date',
        'end_date',
        'installments',
        'remaining_installments',
        'custom_amount',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function type()
    {
        return $this->belongsTo(PerceptionType::class);
    }
}
