<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerceptionType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'calculation',
        'value',
        'applies_to_all',
    ];

    // Una percepción puede aplicarse a muchos empleados
    public function employees()
    {
        return $this->belongsToMany(Employee::class);
    }

    // Calcular monto para un empleado específico
    public function calculateFor(Employee $employee)
    {
        if ($this->type === 'percentage') {
            // Si es un porcentaje, calcular sobre el salario del empleado
            return $employee->base_salary * ($this->value / 100);
        } elseif ($this->calculation === 'fixed') {
            // Si es un monto fijo, retornar el valor directamente
            return $this->value;
        }
    }
}
