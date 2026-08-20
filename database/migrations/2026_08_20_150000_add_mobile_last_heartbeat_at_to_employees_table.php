<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcación offline desde el celular personal del empleado (Parte C, Fase 4):
 * `mobile_last_heartbeat_at` registra el último heartbeat exitoso del celular
 * vinculado — a diferencia de `mobile_linked_at` (fecha de vinculación, no
 * cambia mientras el dispositivo siga siendo el mismo), este campo permite
 * ver en `EmployeeResource` si el celular sigue sincronizando con normalidad.
 * Menor prioridad que el heartbeat/staleness del kiosko (Terminal): acá es
 * un dispositivo personal, no un activo físico de la empresa a monitorear
 * activamente, así que no se agrega un sistema de umbral configurable como
 * el de `Terminal::connectivity_status` — solo se muestra la fecha relativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('mobile_last_heartbeat_at')->nullable()->after('mobile_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('mobile_last_heartbeat_at');
        });
    }
};
