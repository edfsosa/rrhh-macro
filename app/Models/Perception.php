<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perception extends Model
{
    /** @use HasFactory<\Database\Factories\PerceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'description',
        'amount',
        'percentage',
        'mode',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'integer',
        'percentage' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
