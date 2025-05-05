<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'file_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Accessor para obtener la URL pública
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
