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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable(); // foto
            $table->string('first_name', 60); // nombre
            $table->string('last_name', 60); // apellido
            $table->string('ci', 20)->unique(); // cédula
            $table->string('phone', 30)->nullable(); // teléfono
            $table->string('email', 60)->unique(); // correo
            $table->date('hire_date'); // fecha de ingreso
            $table->enum('contract_type', ['mensualero', 'jornalero']); // tipo de contrato
            $table->integer('base_salary'); // salario base en Guaranies (PYG)
            $table->enum('payment_method', ['debito', 'efectivo', 'cheque']); // forma de pago
            $table->foreignId('position_id')->constrained('positions')->onDelete('cascade'); // posición
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade'); // sucursal
            $table->foreignId('schedule_id')->nullable()->constrained('schedule_types')->onDelete('set null'); // horario
            $table->enum('status', ['activo', 'inactivo', 'suspendido'])->default('activo'); // estado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
