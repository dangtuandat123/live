<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefix',
        'suffix',
        'webhook_url',
        'method',
        'params_template',
        'headers_template',
        'is_active',
    ];

    protected $attributes = [
        'method' => 'POST',
        'is_active' => true,
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params_template' => 'array',
            'headers_template' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
