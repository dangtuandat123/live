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
        Schema::table('live_events', function (Blueprint $table) {
            // Ngăn chặn event trùng lặp khi polling đồng thời
            $table->unique(
                ['live_session_id', 'event_type', 'tiktok_user_id', 'event_at'],
                'live_events_dedup_unique'
            );

            // Index cho query sắp xếp theo event_at
            $table->index('event_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_events', function (Blueprint $table) {
            $table->dropUnique('live_events_dedup_unique');
            $table->dropIndex(['event_at']);
        });
    }
};
