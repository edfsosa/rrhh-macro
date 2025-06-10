<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollFactory> */
    use HasFactory;

    protected $fillable = [
        'period',
        'start_date',
        'end_date',
        'pay_date',
        'notes',
        'status',
    ];

    // Relación con el modelo PayrollItem, una nómina puede tener muchos items
    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    // Relación con el modelo Employee, una nómina puede tener muchos empleados asociados
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'payroll_items');
    }
}
