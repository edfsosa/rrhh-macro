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
        'status',
        'photo',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'integer',
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

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
