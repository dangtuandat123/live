<?php

namespace Database\Factories;

use App\Models\PaymentConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentConfig>
 */
class PaymentConfigFactory extends Factory
{
    protected $model = PaymentConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['VietQR', 'PayOS', 'Momo']),
            'prefix' => 'LS_',
            'suffix' => '',
            'webhook_url' => fake()->url(),
            'method' => 'POST',
            'params_template' => [
                'id_user' => '{user_id}',
                'sotien' => '{amount}',
                'description' => '{prefix}{transaction_id}{suffix}',
            ],
            'headers_template' => [
                'Content-Type' => 'application/json',
            ],
            'is_active' => true,
        ];
    }
}
