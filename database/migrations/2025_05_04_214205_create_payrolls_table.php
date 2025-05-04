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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('period', 7); // Ej: 2025-05
            $table->integer('base_salary'); // Guaraníes
            $table->integer('bonuses')->default(0); // bonificaciones (horas extra, comisiones, etc.)
            $table->integer('deductions')->default(0); // descuentos (ips, faltas, anticipos, etc.)
            $table->integer('net_salary')->nullable(); // salario neto final
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
