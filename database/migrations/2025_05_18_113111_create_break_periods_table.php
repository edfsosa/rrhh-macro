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
        Schema::create('break_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_type_id')->constrained()->onDelete('cascade'); // Relación con la tabla schedule_types
            $table->time('start_time'); // Hora de inicio del descanso
            $table->time('end_time'); // Hora de fin del descanso
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_periods');
    }
};
