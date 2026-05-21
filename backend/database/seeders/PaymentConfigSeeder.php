<?php

namespace Database\Seeders;

use App\Models\PaymentConfig;
use Illuminate\Database\Seeder;

class PaymentConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentConfig::updateOrCreate(
            ['name' => 'VietQR'],
            [
                'prefix' => 'LS_',
                'suffix' => '',
                'webhook_url' => 'http://localhost/api/payments/callback',
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
            ]
        );
    }
}
