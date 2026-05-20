<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionKeyword extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'live_session_id',
        'keyword',
    ];

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }
}
