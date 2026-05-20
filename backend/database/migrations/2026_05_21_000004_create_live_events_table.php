<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20)->index(); // comment, gift, like, follow, share, join
            $table->string('tiktok_user_id')->nullable()->index();
            $table->string('tiktok_unique_id')->nullable();
            $table->string('tiktok_nickname')->nullable();
            $table->json('data')->nullable(); // message, gift_name, gift_count, etc.
            $table->timestamp('event_at')->nullable();
            $table->timestamps();

            $table->index(['live_session_id', 'event_type']);
            $table->index(['live_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_events');
    }
};
