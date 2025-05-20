<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayPeriod extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Recibos de sueldo asociados al período
     */
    public function paySlips()
    {
        return $this->hasMany(PaySlip::class);
    }

    /**
     * Deducciones de empleados en este período
     */
    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class, 'pay_period_id');
    }

    /**
     * Percepciones de empleados en este período
     */
    public function employeePerceptions()
    {
        return $this->hasMany(EmployeePerception::class, 'pay_period_id');
    }
}
