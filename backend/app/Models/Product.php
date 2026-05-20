<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'sku',
        'price',
        'category',
        'image',
        'keywords',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liveSessions(): BelongsToMany
    {
        return $this->belongsToMany(LiveSession::class, 'live_session_products');
    }
}
