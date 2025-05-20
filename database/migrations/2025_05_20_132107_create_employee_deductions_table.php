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
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete(); // Relación con la tabla pay_periods
            $table->foreignId('deduction_type_id')->constrained()->cascadeOnDelete(); // Relación con la tabla deduction_types
            $table->decimal('amount', 12, 2); // Monto de la deducción
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
