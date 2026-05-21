<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'amount',
        'payment_config_id',
        'subscription_package_id',
        'vietqr_url',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount' => 'integer',
            'payment_config_id' => 'integer',
            'subscription_package_id' => 'integer',
        ];
    }

    /**
     * Get the user who initiated the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment configuration for the transaction.
     */
    public function paymentConfig(): BelongsTo
    {
        return $this->belongsTo(PaymentConfig::class, 'payment_config_id');
    }

    /**
     * Get the subscription package.
     */
    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    /**
     * Get the subscription package (alias).
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }
}
