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
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['Anticipo', 'Multa', 'Préstamo', 'Otro']); // Ej: Anticipo, Multa, Préstamo, Otro
            $table->string('description')->nullable(); // Descripción de la deducción
            $table->integer('amount')->nullable(); // en Guaraníes
            $table->integer('percentage')->nullable(); // porcentaje de deducción
            $table->boolean('is_active')->default(true); // Estado de la deducción
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
