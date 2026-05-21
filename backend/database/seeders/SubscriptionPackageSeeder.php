<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Free',
                'price' => 0,
                'duration_days' => 30,
                'features' => [
                    'limit_streams' => 1,
                    'max_duration_hours' => 1,
                    'ai_credits' => 1000,
                    'audio_analysis' => false,
                    'export_leads' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'price' => 299000,
                'duration_days' => 30,
                'features' => [
                    'limit_streams' => 5,
                    'max_duration_hours' => 4,
                    'ai_credits' => 50000,
                    'audio_analysis' => true,
                    'export_leads' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'price' => 999000,
                'duration_days' => 90,
                'features' => [
                    'limit_streams' => -1,
                    'max_duration_hours' => 24,
                    'ai_credits' => 500000,
                    'audio_analysis' => true,
                    'export_leads' => true,
                ],
            ],
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
