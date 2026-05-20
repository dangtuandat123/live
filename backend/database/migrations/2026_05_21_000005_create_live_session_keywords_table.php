<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_keywords');
    }
};
