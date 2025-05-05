<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'ci',
        'phone',
        'email',
        'hire_date',
        'contract_type',
        'base_salary',
        'payment_method',
        'position',
        'department',
        'status'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'integer',
        'status' => 'boolean',
    ];

    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    public function perceptions()
    {
        return $this->hasMany(Perception::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
