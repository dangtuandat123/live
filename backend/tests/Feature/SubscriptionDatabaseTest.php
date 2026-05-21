<?php

namespace Tests\Feature;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test SubscriptionPackage factory, creation, and casts.
     */
    public function test_subscription_package_creation_and_casts(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'name' => 'Gold Package',
            'price' => 500000,
            'duration_days' => 60,
            'features' => ['live_chat' => true, 'custom_tags' => false],
        ]);

        $this->assertDatabaseHas('subscription_packages', [
            'id' => $package->id,
            'name' => 'Gold Package',
            'price' => 500000,
            'duration_days' => 60,
        ]);

        $retrieved = SubscriptionPackage::find($package->id);
        $this->assertIsArray($retrieved->features);
        $this->assertTrue($retrieved->features['live_chat']);
        $this->assertFalse($retrieved->features['custom_tags']);
    }

    /**
     * Test UserSubscription relations and active helper.
     */
    public function test_user_subscription_relations_and_active_status(): void
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        $this->assertTrue($subscription->isActive());
        $this->assertInstanceOf(User::class, $subscription->user);
        $this->assertEquals($user->id, $subscription->user->id);
        $this->assertInstanceOf(SubscriptionPackage::class, $subscription->package);
        $this->assertEquals($package->id, $subscription->package->id);

        // Test activeSubscription relationship on User model
        $activeSub = $user->activeSubscription;
        $this->assertNotNull($activeSub);
        $this->assertEquals($subscription->id, $activeSub->id);

        // Test expired subscription
        $expiredSubscription = UserSubscription::factory()->expired()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
        ]);

        $this->assertFalse($expiredSubscription->isActive());
    }

    /**
     * Test PaymentConfig creation and casts.
     */
    public function test_payment_config_creation_and_casts(): void
    {
        $config = PaymentConfig::factory()->create([
            'name' => 'CustomPay',
            'prefix' => 'CP_',
            'suffix' => '_END',
            'webhook_url' => 'https://example.com/webhook',
            'method' => 'POST',
            'params_template' => ['key' => 'value'],
            'headers_template' => ['X-Header' => 'Secret'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('payment_configs', [
            'id' => $config->id,
            'name' => 'CustomPay',
            'prefix' => 'CP_',
            'suffix' => '_END',
            'webhook_url' => 'https://example.com/webhook',
            'method' => 'POST',
            'is_active' => true,
        ]);

        $retrieved = PaymentConfig::find($config->id);
        $this->assertIsArray($retrieved->params_template);
        $this->assertEquals('value', $retrieved->params_template['key']);
        $this->assertIsArray($retrieved->headers_template);
        $this->assertEquals('Secret', $retrieved->headers_template['X-Header']);
    }

    /**
     * Test Transaction creation, relations, and statuses.
     */
    public function test_transaction_creation_and_relations(): void
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 150000,
            'payment_config_id' => $config->id,
            'status' => 'pending',
            'transaction_id' => 'TX_999888',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'amount' => 150000,
            'payment_config_id' => $config->id,
            'status' => 'pending',
            'transaction_id' => 'TX_999888',
        ]);

        $retrieved = Transaction::find($transaction->id);
        $this->assertInstanceOf(User::class, $retrieved->user);
        $this->assertEquals($user->id, $retrieved->user->id);
        $this->assertInstanceOf(PaymentConfig::class, $retrieved->paymentConfig);
        $this->assertEquals($config->id, $retrieved->paymentConfig->id);
    }

    /**
     * Test default attribute values in models.
     */
    public function test_model_default_attributes(): void
    {
        $subscription = new UserSubscription;
        $this->assertEquals('active', $subscription->status);

        $config = new PaymentConfig;
        $this->assertEquals('POST', $config->method);
        $this->assertTrue($config->is_active);

        $transaction = new Transaction;
        $this->assertEquals('pending', $transaction->status);
    }

    /**
     * Test a subscription with a future starts_at date is NOT active.
     */
    public function test_future_starts_at_subscription_is_not_active(): void
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => now()->addDays(5),
            'expires_at' => now()->addDays(35),
            'status' => 'active',
        ]);

        $this->assertFalse($subscription->isActive());

        // Refresh user relation/query
        $user->unsetRelation('activeSubscription');
        $this->assertNull($user->activeSubscription);
    }

    /**
     * Test cascade delete restrictions for SubscriptionPackage.
     */
    public function test_cascade_delete_restrictions(): void
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
        ]);

        $this->expectException(QueryException::class);
        $package->delete();
    }

    /**
     * Test cascade delete restrictions for PaymentConfig.
     */
    public function test_payment_config_cascade_delete_restrictions(): void
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'payment_config_id' => $config->id,
        ]);

        $this->expectException(QueryException::class);
        $config->delete();
    }

    /**
     * Test used_ai_credits is cast and defaults correctly.
     */
    public function test_user_subscription_used_ai_credits(): void
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 15,
        ]);

        $this->assertSame(15, $subscription->used_ai_credits);

        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $subscription->id,
            'used_ai_credits' => 15,
        ]);

        $retrieved = UserSubscription::find($subscription->id);
        $this->assertSame(15, $retrieved->used_ai_credits);
        $this->assertIsInt($retrieved->used_ai_credits);

        $subscriptionDefault = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);
        $this->assertSame(0, $subscriptionDefault->used_ai_credits);
        $this->assertSame(0, UserSubscription::find($subscriptionDefault->id)->used_ai_credits);
    }
}
