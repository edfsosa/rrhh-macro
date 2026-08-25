<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de heartbeat/staleness a terminals (Fase 5 del rollout
 * offline vía PWA): a diferencia de `last_seen_at` (se actualiza también con
 * cada carga de página, vía sesión), estos campos solo se actualizan cuando
 * el terminal completa exitosamente cada tipo de sincronización con la API
 * Sanctum — permiten distinguir "el terminal cargó la página una vez" de "el
 * terminal está efectivamente sincronizando" en un dispositivo que puede
 * quedar abierto días sin recargar.
 *
 * - last_heartbeat_at: último POST /api/v1/terminal/heartbeat exitoso.
 * - last_employee_sync_at: último GET /api/v1/terminal/employees/sync exitoso.
 * - last_event_sync_at: último POST /api/v1/terminal/events/sync exitoso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')
                ->nullable()
                ->after('last_seen_at')
                ->comment('Último heartbeat exitoso vía API Sanctum');

            $table->timestamp('last_employee_sync_at')
                ->nullable()
                ->after('last_heartbeat_at')
                ->comment('Último sync de empleados exitoso vía API Sanctum');

            $table->timestamp('last_event_sync_at')
                ->nullable()
                ->after('last_employee_sync_at')
                ->comment('Último envío de eventos de marcación exitoso vía API Sanctum');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['last_heartbeat_at', 'last_employee_sync_at', 'last_event_sync_at']);
        });
    }
};
