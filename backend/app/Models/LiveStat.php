<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStat extends Model
{
    protected $fillable = [
        'live_session_id',
        'total_views',
        'total_comments',
        'total_likes',
        'total_gifts',
        'total_follows',
        'total_shares',
        'viewer_count',
        'sentiment_positive',
        'sentiment_neutral',
        'sentiment_negative',
        'leads_count',
    ];

    protected function casts(): array
    {
        return [
            'total_views' => 'integer',
            'total_comments' => 'integer',
            'total_likes' => 'integer',
            'total_gifts' => 'integer',
            'total_follows' => 'integer',
            'total_shares' => 'integer',
            'viewer_count' => 'integer',
            'sentiment_positive' => 'integer',
            'sentiment_neutral' => 'integer',
            'sentiment_negative' => 'integer',
            'leads_count' => 'integer',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * Tính phần trăm cảm xúc tích cực (0-100).
     */
    public static function sentimentScore(?self $stats): int
    {
        if (! $stats) {
            return 0;
        }
        $total = $stats->sentiment_positive + $stats->sentiment_neutral + $stats->sentiment_negative;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($stats->sentiment_positive / $total) * 100);
    }
}
