<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * M1 fix: Nâng event_at lên millisecond precision + thêm data_hash vào unique index
     * để tránh dedup sai khi cùng user gửi 2 comment khác nhau trong cùng 1 giây.
     */
    public function up(): void
    {
        // 1. Thêm cột data_hash (MD5 hash của comment content)
        Schema::table('live_events', function (Blueprint $table) {
            $table->char('data_hash', 32)->nullable()->after('data');
        });

        // 2. Populate data_hash cho events hiện có
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE live_events SET data_hash = MD5(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.comment')), ''))");
        }

        // 3. Nâng event_at lên DATETIME(3) — millisecond precision
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE live_events MODIFY event_at DATETIME(3) NULL');
        }

        // 4. Drop unique index cũ, tạo mới có data_hash
        Schema::table('live_events', function (Blueprint $table) {
            $table->dropUnique('live_events_dedup_unique');
            $table->unique(
                ['live_session_id', 'event_type', 'tiktok_user_id', 'event_at', 'data_hash'],
                'live_events_dedup_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_events', function (Blueprint $table) {
            $table->dropUnique('live_events_dedup_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE live_events MODIFY event_at TIMESTAMP NULL');
        }

        Schema::table('live_events', function (Blueprint $table) {
            $table->unique(
                ['live_session_id', 'event_type', 'tiktok_user_id', 'event_at'],
                'live_events_dedup_unique'
            );
            $table->dropColumn('data_hash');
        });
    }
};
