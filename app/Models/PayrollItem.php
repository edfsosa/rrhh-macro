<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'type',
        'description',
        'amount',
    ];

    /**
     * Relación con el modelo Payroll, un item pertenece a una nómina
     */
    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * Relación con el modelo Employee, un item pertenece a un empleado
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
