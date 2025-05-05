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
        Schema::create('perceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['Bono', 'Comisión', 'Horas Extra', 'Otro']);
            $table->string('description')->nullable();
            $table->integer('amount')->nullable(); // monto fijo
            $table->integer('percentage')->nullable(); // porcentaje sobre salario
            $table->enum('mode', ['monto_fijo', 'porcentaje'])->default('monto_fijo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perceptions');
    }
};
