<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveSession extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'tiktok_username',
        'status',
        'tiktok_session_id',
        'room_id',
        'thumbnail',
        'duration_seconds',
        'started_at',
        'ended_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'live_session_products');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LiveEvent::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(LiveSessionKeyword::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(LiveStat::class);
    }

    // --- Accessors ---

    public function getDurationFormattedAttribute(): string
    {
        $seconds = $this->duration_seconds;
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return $hours . 'h ' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . 'm';
        }
        return $minutes . 'm';
    }

    // --- Scopes ---

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }
}
