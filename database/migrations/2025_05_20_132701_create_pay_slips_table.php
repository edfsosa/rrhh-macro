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
        Schema::create('pay_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // Relación con la tabla employees
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete(); // Relación con la tabla pay_periods
            $table->decimal('gross_earnings', 12, 2); // Salario bruto
            $table->decimal('total_perceptions', 12, 2); // Total de percepciones
            $table->decimal('total_deductions', 12, 2); // Total de deducciones
            $table->decimal('net_salary', 12, 2); // Sueldo neto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_slips');
    }
};
