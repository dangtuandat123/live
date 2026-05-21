<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveEvent extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'live_session_id',
        'event_type',
        'tiktok_user_id',
        'tiktok_unique_id',
        'tiktok_nickname',
        'data',
        'data_hash',
        'event_at',
        'sentiment',
        'intent_tag',
        'question_tag',
        'product_tag',
        'has_phone',
        'ai_processed',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'event_at' => 'datetime',
            'has_phone' => 'boolean',
            'ai_processed' => 'boolean',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }
}
