<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'group' => 'general',
            'name' => 'terminal_stale_threshold_hours',
            'payload' => json_encode(2),
            'locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'general')
            ->where('name', 'terminal_stale_threshold_hours')
            ->delete();
    }
};
