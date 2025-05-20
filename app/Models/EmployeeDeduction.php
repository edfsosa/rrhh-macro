<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    protected $fillable = [
        'employee_id',
        'pay_period_id',
        'deduction_type_id',
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
        return $this->belongsTo(DeductionType::class);
    }
}
