<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaySlip extends Model
{
    protected $fillable = [
        'employee_id',
        'pay_period_id',
        'gross_earnings',
        'total_perceptions',
        'total_deductions',
        'net_salary'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function period()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }
    public function perceptions()
    {
        return $this->hasMany(EmployeePerception::class, 'pay_period_id', 'pay_period_id')
            ->where('employee_id', $this->employee_id);
    }
    public function deductions()
    {
        return $this->hasMany(EmployeeDeduction::class, 'pay_period_id', 'pay_period_id')
            ->where('employee_id', $this->employee_id);
    }
}
