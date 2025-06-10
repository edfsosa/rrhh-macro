<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->comment('Ej: Estándar, Sucursal A, etc'); // Nombre del tipo de turno
            $table->boolean('is_default')->default(false); // Indica si es el tipo de turno por defecto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_types');
    }
};
