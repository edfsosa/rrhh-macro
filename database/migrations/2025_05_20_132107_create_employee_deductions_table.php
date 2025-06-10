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
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // Relación con la tabla employees
            $table->foreignId('deduction_type_id')->constrained()->cascadeOnDelete(); // Relación con la tabla deduction_types
            $table->date('start_date'); // Fecha de inicio de la deducción
            $table->date('end_date')->nullable(); // Fecha de fin de la deducción (opcional)
            $table->unsignedInteger('installments')->default(1); // Número de cuotas para la deducción
            $table->unsignedInteger('remaining_installments')->default(1); // Cuotas restantes para la deducción
            $table->decimal('custom_amount', 10, 2)->nullable(); // Monto personalizado de la deducción (opcional)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};
