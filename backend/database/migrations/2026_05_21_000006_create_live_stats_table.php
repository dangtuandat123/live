<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('total_comments')->default(0);
            $table->unsignedInteger('total_likes')->default(0);
            $table->unsignedInteger('total_gifts')->default(0);
            $table->unsignedInteger('total_follows')->default(0);
            $table->unsignedInteger('total_shares')->default(0);
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('sentiment_positive')->default(0);
            $table->unsignedInteger('sentiment_neutral')->default(0);
            $table->unsignedInteger('sentiment_negative')->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stats');
    }
};
