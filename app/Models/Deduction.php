<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deduction extends Model
{
    /** @use HasFactory<\Database\Factories\DeductionFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'description',
        'amount',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'amount' => 'integer',
        'percentage' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
