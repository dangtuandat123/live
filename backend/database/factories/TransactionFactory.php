<?php

namespace Database\Factories;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomElement([299000, 999000]),
            'payment_config_id' => PaymentConfig::factory(),
            'subscription_package_id' => SubscriptionPackage::factory(),
            'vietqr_url' => fake()->url(),
            'status' => 'pending',
            'transaction_id' => 'TX_'.strtoupper(fake()->bothify('??###?#')),
        ];
    }

    /**
     * Indicate that the transaction is successful.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
        ]);
    }

    /**
     * Indicate that the transaction has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
