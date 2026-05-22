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
                'bank_name' => 'MB Bank',
                'bank_id' => '970416',
                'account_no' => '11183041',
                'account_name' => 'DANG TUAN DAT',
                'qr_template' => 'https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}',
            ]
        );
    }
}
