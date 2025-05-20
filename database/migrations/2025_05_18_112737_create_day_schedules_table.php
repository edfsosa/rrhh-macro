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
        Schema::create('day_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_type_id')->constrained()->onDelete('cascade'); // Relación con la tabla schedule_types
            $table->tinyInteger('day_of_week');   // 0=domingo … 6=sábado
            $table->time('start_time'); // Hora de inicio
            $table->time('end_time'); // Hora de fin
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_schedules');
    }
};
