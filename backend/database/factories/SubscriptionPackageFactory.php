<?php

namespace Database\Factories;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPackage>
 */
class SubscriptionPackageFactory extends Factory
{
    protected $model = SubscriptionPackage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Free', 'Pro', 'Enterprise']),
            'price' => fake()->randomElement([0, 299000, 999000]),
            'duration_days' => fake()->randomElement([30, 90, 365]),
            'features' => [
                'limit_streams' => fake()->randomElement([1, 5, -1]),
                'audio_analysis' => fake()->boolean(),
                'export_leads' => fake()->boolean(),
            ],
        ];
    }
}
