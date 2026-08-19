<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los contadores de cola offline que el kiosko reporta en cada
 * heartbeat (Fase 6 — hardening): sin esto, el badge de conectividad
 * (Fase 5) no distingue un terminal con red intermitente y una cola de
 * marcaciones atascada de uno realmente sano — ambos se ven "en línea"
 * porque el heartbeat en sí llega, aunque la cola de `outbound_events` del
 * lado del cliente (ver terminal-offline/queue.js) no se esté vaciando.
 *
 * Nullable porque un terminal que nunca hizo heartbeat (o uno con una
 * versión vieja del bundle, antes de esta fase) no reporta el dato — se
 * distingue de "0 pendientes" para no dar una falsa sensación de salud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->unsignedInteger('last_pending_events_count')
                ->nullable()
                ->after('last_event_sync_at')
                ->comment('Eventos pendientes de sincronizar reportados en el último heartbeat');

            $table->unsignedInteger('last_conflict_events_count')
                ->nullable()
                ->after('last_pending_events_count')
                ->comment('Eventos en conflicto (requieren revisión manual) reportados en el último heartbeat');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['last_pending_events_count', 'last_conflict_events_count']);
        });
    }
};
