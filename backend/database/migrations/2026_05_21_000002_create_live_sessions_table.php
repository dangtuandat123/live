<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform')->default('tiktok');
            $table->string('tiktok_username');
            $table->string('status')->default('draft'); // draft, connecting, live, ended, error
            $table->string('tiktok_session_id')->nullable()->index(); // UUID from Python service
            $table->string('room_id')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
