<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
