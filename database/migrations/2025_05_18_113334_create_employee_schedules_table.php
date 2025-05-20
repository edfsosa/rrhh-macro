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
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade'); // Relación con la tabla employees
            $table->foreignId('schedule_type_id')->constrained()->onDelete('cascade'); // Relación con la tabla schedule_types
            $table->date('valid_from')->nullable();  // opcional para vigencias
            $table->date('valid_to')->nullable(); // opcional para vigencias
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
