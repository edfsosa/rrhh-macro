<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePerception extends Model
{
    protected $fillable = [
        'employee_id',
        'pay_period_id',
        'perception_type_id',
        'quantity',
        'amount',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function period()
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function type()
    {
        return $this->belongsTo(PerceptionType::class);
    }
}
