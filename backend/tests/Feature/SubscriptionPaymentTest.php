<?php

namespace Tests\Feature;

use App\Jobs\SendOutboundPaymentWebhookJob;
use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_packages_listing_returns_seeded_packages()
    {
        $package1 = SubscriptionPackage::factory()->create([
            'name' => 'Free Trial',
            'price' => 0,
            'duration_days' => 7,
            'features' => ['audio_analysis' => false],
        ]);

        $package2 = SubscriptionPackage::factory()->create([
            'name' => 'Pro Plan',
            'price' => 299000,
            'duration_days' => 30,
            'features' => ['audio_analysis' => true],
        ]);

        $response = $this->getJson('/api/subscription/packages');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment([
            'name' => 'Free Trial',
            'price' => 0,
            'duration_days' => 7,
        ]);
        $response->assertJsonFragment([
            'name' => 'Pro Plan',
            'price' => 299000,
            'duration_days' => 30,
        ]);
    }

    public function test_user_subscription_status_endpoint_returns_correct_response()
    {
        $user = User::factory()->create();

        // 1. Unauthenticated status request
        $this->getJson('/api/subscription/status')
            ->assertStatus(401);

        // 2. Authenticated status request - no subscription
        $this->actingAs($user)
            ->getJson('/api/subscription/status')
            ->assertStatus(200)
            ->assertJson([
                'active' => false,
                'package_id' => null,
                'expires_at' => null,
            ]);

        // 3. Authenticated status request - has active subscription
        $package = SubscriptionPackage::factory()->create(['duration_days' => 30]);
        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        $user->unsetRelation('activeSubscription');

        $this->actingAs($user)
            ->getJson('/api/subscription/status')
            ->assertStatus(200)
            ->assertJson([
                'active' => true,
                'package_id' => $package->id,
            ]);
    }

    public function test_checkout_free_package_activates_instantly_without_vietqr()
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create(['is_active' => true]);
        $package = SubscriptionPackage::factory()->create([
            'price' => 0,
            'duration_days' => 15,
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['transaction_id', 'vietqr_url']);
        $response->assertJson([
            'vietqr_url' => null,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 0,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
        ]);
    }

    public function test_checkout_paid_package_generates_vietqr_url_and_creates_pending_transaction()
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create([
            'prefix' => 'PAY_',
            'suffix' => '_CONF',
            'is_active' => true,
        ]);
        $package = SubscriptionPackage::factory()->create([
            'price' => 299000,
            'duration_days' => 30,
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transaction_id',
            'vietqr_url',
            'beneficiary_bank',
            'beneficiary_account',
            'beneficiary_name',
        ]);

        $data = $response->json();
        $transactionId = $data['transaction_id'];
        $vietQrUrl = $data['vietqr_url'];

        $this->assertStringContainsString('PAY_', $transactionId);
        $this->assertStringContainsString('PAY_', $vietQrUrl);
        $this->assertStringContainsString((string) $user->id, $vietQrUrl);
        $this->assertStringContainsString('_CONF', $vietQrUrl);
        $this->assertStringContainsString('299000', $vietQrUrl);

        $this->assertEquals($config->bank_name, $data['beneficiary_bank']);
        $this->assertEquals($config->account_no, $data['beneficiary_account']);
        $this->assertEquals($config->account_name, $data['beneficiary_name']);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'amount' => 299000,
            'status' => 'pending',
            'vietqr_url' => $vietQrUrl,
        ]);
    }

    public function test_checkout_returns_503_if_no_active_payment_config()
    {
        $user = User::factory()->create();
        PaymentConfig::query()->update(['is_active' => false]);
        $package = SubscriptionPackage::factory()->create(['price' => 299000]);

        $response = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(503);
    }

    public function test_callback_processes_payment_upgrades_subscription_and_marks_transaction_success()
    {
        Queue::fake();

        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create([
            'price' => 299000,
            'duration_days' => 30,
        ]);
        $config = PaymentConfig::factory()->create([
            'is_active' => true,
            'webhook_url' => 'https://example.com/api/webhook',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 299000,
            'status' => 'pending',
            'payment_config_id' => $config->id,
            'subscription_package_id' => $package->id,
        ]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 299000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription upgraded successfully',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
        ]);

        Queue::assertPushed(SendOutboundPaymentWebhookJob::class);
    }

    public function test_callback_extends_active_subscription_of_same_package()
    {
        Queue::fake();

        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create([
            'price' => 299000,
            'duration_days' => 30,
        ]);
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        // Existing active subscription
        $startsAt = now();
        $expiresAt = now()->addDays(10);
        $existingSub = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 299000,
        ]);

        $response->assertStatus(200);

        // Verify sub extended
        $existingSub->refresh();
        $this->assertEquals($expiresAt->addDays(30)->toDateTimeString(), $existingSub->expires_at->toDateTimeString());
        $this->assertEquals('active', $existingSub->status);
    }

    public function test_callback_deactivates_old_subscription_if_different_package()
    {
        Queue::fake();

        $user = User::factory()->create();
        $packageOld = SubscriptionPackage::factory()->create([
            'price' => 99000,
            'duration_days' => 30,
        ]);
        $packageNew = SubscriptionPackage::factory()->create([
            'price' => 299000,
            'duration_days' => 30,
        ]);
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        // Existing active subscription of Package Old
        $existingSub = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $packageOld->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 299000,
        ]);

        $response->assertStatus(200);

        $existingSub->refresh();
        $this->assertEquals('inactive', $existingSub->status);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_package_id' => $packageNew->id,
            'status' => 'active',
        ]);
    }

    public function test_callback_returns_422_if_no_package_matches_price()
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 123456, // Random price
        ]);

        $response->assertStatus(422);
    }

    public function test_callback_returns_500_if_no_active_payment_config()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create(['price' => 299000]);
        PaymentConfig::query()->update(['is_active' => false]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 299000,
        ]);

        $response->assertStatus(500);
    }

    public function test_outbound_webhook_job_sends_http_request_with_correct_replacements()
    {
        Http::fake();

        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create([
            'name' => 'CustomWebhook',
            'prefix' => 'PRE_',
            'suffix' => '_SUF',
            'webhook_url' => 'https://webhook.site/pay',
            'method' => 'POST',
            'params_template' => [
                'userId' => '{userId}',
                'user_id' => '{user_id}',
                'amount' => '{amount}',
                'transactionId' => '{transactionId}',
                'transaction_id' => '{transaction_id}',
                'prefix' => '{prefix}',
                'Suffix' => '{Suffix}',
            ],
            'headers_template' => [
                'X-Custom-Auth' => 'Bearer {prefix}{transactionId}',
                'Content-Type' => 'application/json',
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 299000,
            'status' => 'pending',
            'payment_config_id' => $config->id,
            'transaction_id' => 'PRE_ABC123_SUF',
        ]);

        $job = new SendOutboundPaymentWebhookJob($transaction->id);
        $job->handle();

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://webhook.site/pay' &&
                $request->method() === 'POST' &&
                $request->header('X-Custom-Auth')[0] === 'Bearer PRE_PRE_ABC123_SUF' &&
                $request['userId'] == $user->id &&
                $request['user_id'] == $user->id &&
                $request['amount'] == 299000 &&
                $request['transactionId'] === 'PRE_ABC123_SUF' &&
                $request['transaction_id'] === 'PRE_ABC123_SUF' &&
                $request['prefix'] === 'PRE_' &&
                $request['Suffix'] === '_SUF';
        });
    }
}
