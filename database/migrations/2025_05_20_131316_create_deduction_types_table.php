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
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nombre de la deducción (e.g. IPS, IRP)
            $table->text('description')->nullable();
            $table->enum('calculation', ['fixed', 'percentage'])->default('fixed'); // fijo o porcentaje
            $table->decimal('value', 8, 2); // monto fijo o porcentaje (e.g. 9.00)
            $table->boolean('applies_to_all')->default(false); // si aplica a todos los empleados
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};
