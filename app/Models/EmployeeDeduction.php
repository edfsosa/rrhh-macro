<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    protected $fillable = [
        'employee_id',
        'deduction_type_id',
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
        return $this->belongsTo(DeductionType::class);
    }
}
