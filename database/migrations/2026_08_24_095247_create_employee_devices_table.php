<?php

use App\Models\Employee;
use App\Models\EmployeeDevice;
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
        Schema::create('employee_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('linked_at');
            // null = dispositivo activo (todavía no reemplazado ni revocado) — un
            // empleado solo puede tener un registro con unlinked_at null a la vez,
            // igual que el `mobile_linked_at` único que reemplaza este historial.
            $table->timestamp('unlinked_at')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_brand', 60)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('device_serial', 100)->nullable();
            $table->string('device_mac', 17)->nullable();
            $table->text('device_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'unlinked_at']);
        });

        // Backfill: empleados con un dispositivo ya vinculado antes de este historial
        // (mobile_linked_at venía siendo el único registro, sin historial). Sin esto,
        // el historial arrancaría vacío pese a vinculaciones reales ya en uso.
        Employee::query()
            ->whereNotNull('mobile_linked_at')
            ->select(['id', 'mobile_linked_at'])
            ->each(function (Employee $employee) {
                EmployeeDevice::create([
                    'employee_id' => $employee->id,
                    'linked_at' => $employee->mobile_linked_at,
                    'unlinked_at' => null,
                    'user_agent' => null, // no se registraba antes de este historial
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_devices');
    }
};
