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
        Schema::create('perception_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nombre de la percepción (e.g. horas extras, bonificaciones)
            $table->string('description')->nullable(); // descripción opcional
            $table->enum('calculation', ['fixed', 'percentage'])->default('fixed'); // tipo de cálculo: fijo, por hora, porcentaje
            $table->decimal('value', 8, 2); // si hourly, valor por hora; porcentaje, relativo
            $table->boolean('applies_to_all')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perception_types');
    }
};
