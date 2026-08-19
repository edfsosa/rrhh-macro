<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el token de configuración de un solo uso usado para provisionar el
 * terminal como PWA offline: el admin genera el enlace desde Filament, el
 * terminal lo visita una vez (online) para reclamar su token Sanctum, y el
 * setup_token queda invalidado inmediatamente después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('setup_token', 64)
                ->nullable()
                ->unique()
                ->after('last_seen_at')
                ->comment('Token de un solo uso para reclamar el token Sanctum del terminal');

            $table->timestamp('setup_token_expires_at')
                ->nullable()
                ->after('setup_token');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['setup_token', 'setup_token_expires_at']);
        });
    }
};
