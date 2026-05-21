<?php

namespace Database\Factories;

use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSubscription>
 */
class UserSubscriptionFactory extends Factory
{
    protected $model = UserSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_package_id' => SubscriptionPackage::factory(),
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(30),
            'status' => 'expired',
        ]);
    }
}
