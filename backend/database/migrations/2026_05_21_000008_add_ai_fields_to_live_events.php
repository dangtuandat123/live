<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_events', function (Blueprint $table) {
            $table->string('sentiment', 20)->nullable()->after('data'); // positive, neutral, negative
            $table->string('intent_tag', 50)->nullable()->after('sentiment'); // Chốt đơn, Hỏi giá, etc.
            $table->string('question_tag', 50)->nullable()->after('intent_tag'); // Hỏi size, Hỏi ship, etc.
            $table->string('product_tag', 100)->nullable()->after('question_tag'); // matched product name
            $table->boolean('has_phone')->default(false)->after('product_tag');
            $table->boolean('ai_processed')->default(false)->after('has_phone');

            $table->index('sentiment');
            $table->index('intent_tag');
            $table->index('ai_processed');
        });
    }

    public function down(): void
    {
        Schema::table('live_events', function (Blueprint $table) {
            $table->dropIndex(['sentiment']);
            $table->dropIndex(['intent_tag']);
            $table->dropIndex(['ai_processed']);
            $table->dropColumn(['sentiment', 'intent_tag', 'question_tag', 'product_tag', 'has_phone', 'ai_processed']);
        });
    }
};
