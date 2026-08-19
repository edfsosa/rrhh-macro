<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el flujo de revisión manual a los fallos de marcación: hasta ahora
 * `AttendanceMarkFailureResource` era de solo lectura — un `sync_conflict`
 * (kiosko offline, ver AttendanceEventSyncService) quedaba registrado pero
 * sin ninguna acción posible desde Filament. Permite que un admin apruebe
 * (reconstruye el `AttendanceEvent`), o descarte el fallo como revisado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_mark_failures', function (Blueprint $table) {
            $table->string('resolution_status', 20)
                ->default('pending')
                ->after('metadata')
                ->comment('pending, approved, dismissed');

            $table->timestamp('resolved_at')->nullable()->after('resolution_status');

            $table->foreignId('resolved_by_id')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('resolution_notes')->nullable()->after('resolved_by_id');

            $table->foreignId('resolved_event_id')
                ->nullable()
                ->after('resolution_notes')
                ->constrained('attendance_events')
                ->nullOnDelete()
                ->comment('AttendanceEvent creado al aprobar la marcación');

            $table->index(['resolution_status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_mark_failures', function (Blueprint $table) {
            // El índice compuesto debe eliminarse antes que la columna que lo compone —
            // en SQLite (a diferencia de MySQL, que lo maneja implícitamente) dropColumn()
            // falla si el índice todavía la referencia.
            $table->dropIndex(['resolution_status', 'occurred_at']);
            $table->dropConstrainedForeignId('resolved_by_id');
            $table->dropConstrainedForeignId('resolved_event_id');
            $table->dropColumn(['resolution_status', 'resolved_at', 'resolution_notes']);
        });
    }
};
