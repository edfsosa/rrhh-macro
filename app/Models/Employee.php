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
}
