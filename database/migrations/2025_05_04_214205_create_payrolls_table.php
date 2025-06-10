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
            $table->string('period'); // Ej: 2025-05
            $table->date('start_date'); // Fecha de inicio del período
            $table->date('end_date'); // Fecha de fin del período
            $table->date('pay_date'); // Fecha de pago
            $table->text('notes')->nullable(); // Notas adicionales sobre la nómina
            $table->enum('status', ['draft', 'processed', 'paid'])->default('draft'); // Estado de la nómina, ej: borrador, procesada, pagada
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
