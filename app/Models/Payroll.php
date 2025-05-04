<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period',
        'base_salary',
        'bonuses',
        'deductions',
        'net_salary',
    ];

    protected $casts = [
        'base_salary' => 'integer',
        'bonuses' => 'integer',
        'deductions' => 'integer',
        'net_salary' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Método para calcular net_salary con IPS incluido en deductions
    public function calculateNetSalary()
    {
        $gross = $this->base_salary + $this->bonuses;
        $ips = intval($gross * 0.09);
        $this->deductions += $ips;
        $this->net_salary = $gross - $this->deductions;
    }
}
