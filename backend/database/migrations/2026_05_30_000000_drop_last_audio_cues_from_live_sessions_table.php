<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Loại bỏ tính năng phân tích âm thanh: gỡ cột last_audio_cues khỏi live_sessions.
     */
    public function up(): void
    {
        if (Schema::hasColumn('live_sessions', 'last_audio_cues')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dropColumn('last_audio_cues');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('live_sessions', 'last_audio_cues')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->text('last_audio_cues')->nullable();
            });
        }
    }
};
