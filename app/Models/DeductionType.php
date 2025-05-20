<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    protected $fillable = [
        'name',
        'calculation',
        'value',
    ];
}
