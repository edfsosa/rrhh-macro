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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete(); // Relación con la nómina, se elimina si se elimina la nómina
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // Relación con el empleado, se elimina si se elimina el empleado
            $table->enum('type', ['salary', 'perception', 'deduction']); // Tipo de item: salario, percepción o deducción
            $table->string('description'); // Descripción del item, ej: "Salario base", "Bonificación", "Impuesto"
            $table->decimal('amount', 12, 2); // Monto del item, ej: 1500.00
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
