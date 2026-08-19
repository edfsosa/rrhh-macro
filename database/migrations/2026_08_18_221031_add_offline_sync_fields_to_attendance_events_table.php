<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega client_event_id y synced_at para el flujo de sincronización de
 * terminales (kiosko offline vía PWA, ver AttendanceEventSyncController).
 *
 * - client_event_id: UUID generado en el cliente al momento de la captura
 *   (no al sincronizar), usado para deduplicar reintentos con seguridad.
 * - synced_at: null para eventos registrados en línea en tiempo real;
 *   poblado cuando el evento llegó a través del flujo de sincronización
 *   offline, para poder distinguir "marcado en vivo" de "marcado offline,
 *   sincronizado después" en reportes y exports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->string('client_event_id', 36)
                ->nullable()
                ->unique()
                ->after('id')
                ->comment('UUID generado en el cliente al capturar — deduplica reintentos de sync');

            $table->timestamp('synced_at')
                ->nullable()
                ->after('recorded_at')
                ->comment('Momento en que el evento fue recibido vía sync offline (null = registrado en línea)');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropColumn(['client_event_id', 'synced_at']);
        });
    }
};
