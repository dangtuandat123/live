<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const DEFAULT_SETTINGS = [
        'ai_language' => 'vi',
        'auto_extract_phone' => true,
        'auto_extract_address' => true,
        'realtime_alerts' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'settings',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /**
     * Get merged settings with defaults.
     */
    public function getSettingsWithDefaults(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /**
     * Get all subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get all transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    /**
     * Resolve the active subscription or auto-subscribe to Free package if none exists.
     */
    public function resolveActiveSubscription(): ?UserSubscription
    {
        if (! $this->subscriptions()->exists()) {
            $freePackage = SubscriptionPackage::where('price', 0)->first()
                ?? SubscriptionPackage::where('name', 'Free')->first();

            if ($freePackage) {
                $subscription = $this->subscriptions()->create([
                    'subscription_package_id' => $freePackage->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($freePackage->duration_days ?? 30),
                    'status' => 'active',
                    'used_ai_credits' => 0,
                ]);
                $this->unsetRelation('subscriptions');
                $this->unsetRelation('activeSubscription');

                return $subscription;
            }
        }

        return $this->activeSubscription;
    }

    /**
     * Get subscription features merged with defaults.
     */
    public function getSubscriptionFeatures(): array
    {
        $defaults = [
            'limit_streams' => 1,
            'max_duration_hours' => 1,
            'ai_credits' => 1000,
            'audio_analysis' => false,
            'export_leads' => false,
        ];

        $activeSub = $this->resolveActiveSubscription();
        $features = $activeSub && $activeSub->package ? ($activeSub->package->features ?? []) : [];

        return array_merge($defaults, $features);
    }
}
