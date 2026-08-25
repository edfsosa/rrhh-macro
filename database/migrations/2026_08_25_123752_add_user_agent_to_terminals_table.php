<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `user_agent` a terminals — capturado en TerminalSetupController::claim()
 * al provisionar el kiosko, mismo dato que ya guarda EmployeeDevice desde la
 * Fase 1 de Parte C. Insumo de DeviceHintsParser para prellenar marca/modelo
 * como sugerencia editable, y referencia diagnóstica adicional en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('device_notes');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
