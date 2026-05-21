<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionStatsHistory extends Model
{
    protected $table = 'live_session_stats_history';

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
}
