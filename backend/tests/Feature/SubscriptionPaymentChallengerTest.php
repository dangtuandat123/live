<?php

namespace Tests\Feature;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionPaymentChallengerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that when two packages have the same price, the callback incorrectly
     * resolves to the first matched package instead of the one the user checked out.
     */
    public function test_callback_same_price_different_package_bug()
    {
        Queue::fake();

        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        // Create two packages with the same price but different durations/names
        $packageA = SubscriptionPackage::create([
            'name' => 'Basic Package (30 Days)',
            'price' => 150000,
            'duration_days' => 30,
            'features' => [],
        ]);

        $packageB = SubscriptionPackage::create([
            'name' => 'Promo Package (60 Days)',
            'price' => 150000,
            'duration_days' => 60,
            'features' => [],
        ]);

        // User checkouts the promo package (Package B)
        $response = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $packageB->id,
        ]);

        $response->assertStatus(200);
        $transactionId = $response->json('transaction_id');

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => $transactionId,
            'subscription_package_id' => $packageB->id,
            'status' => 'pending',
            'amount' => 150000,
        ]);

        // Trigger callback for the user with the price of 150000
        $callbackResponse = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 150000,
        ]);

        $callbackResponse->dump();
        $callbackResponse->assertStatus(200);

        // Retrieve the subscription that was created
        $user->unsetRelation('activeSubscription');
        $activeSub = $user->activeSubscription;

        $this->assertNotNull($activeSub, 'An active subscription should have been created');

        // Assert that the active subscription is indeed for Package B (the one they checked out)
        // This will FAIL if the system resolved to Package A by price instead of using the pending transaction's package.
        $this->assertEquals(
            $packageB->id,
            $activeSub->subscription_package_id,
            "Expected subscription to be for Package B ({$packageB->name}), but got Package ID: {$activeSub->subscription_package_id}"
        );

        $this->assertEquals(
            60,
            $activeSub->starts_at->diffInDays($activeSub->expires_at),
            'Expected subscription duration to be 60 days, but got: '.$activeSub->starts_at->diffInDays($activeSub->expires_at)
        );
    }

    /**
     * Test that duplicate sequential callback requests for the same payment lead to double-crediting
     * because there is no unique transaction/callback identifier and the pending status check is bypassed.
     */
    public function test_callback_duplicate_requests_cause_double_crediting()
    {
        Queue::fake();

        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        $package = SubscriptionPackage::create([
            'name' => 'Premium Package (30 Days)',
            'price' => 200000,
            'duration_days' => 30,
            'features' => [],
        ]);

        // 1. Checkout to create a pending transaction
        $response = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $package->id,
        ]);
        $response->assertStatus(200);

        // 2. First callback (success processing)
        $callback1 = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 200000,
        ]);
        $callback1->assertStatus(200);

        $user->unsetRelation('activeSubscription');
        $activeSub1 = $user->activeSubscription;
        $this->assertNotNull($activeSub1);
        $this->assertEquals(30, $activeSub1->starts_at->diffInDays($activeSub1->expires_at));

        // 3. Second callback (simulating retry or duplicate webhook execution)
        $callback2 = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 200000,
        ]);

        $callback2->assertStatus(200);

        $user->unsetRelation('activeSubscription');
        $activeSub2 = $user->activeSubscription;

        // If it was double-credited, the expires_at will have been extended to 60 days
        $duration = $activeSub2->starts_at->diffInDays($activeSub2->expires_at);

        $this->assertEquals(
            30,
            $duration,
            "Expected subscription duration to remain 30 days (idempotent), but it was extended to {$duration} days."
        );

        // Check if a duplicate successful transaction was created
        $successTransactionCount = Transaction::where('user_id', $user->id)
            ->where('status', 'success')
            ->count();

        $this->assertEquals(
            1,
            $successTransactionCount,
            "Expected only 1 success transaction, but found {$successTransactionCount} transactions."
        );
    }

    /**
     * Test that users can abuse the free package checkout endpoint to get infinite duration
     * of free trials since there is no check/validation preventing repeated checkouts of free packages.
     */
    public function test_free_package_checkout_infinite_abuse()
    {
        $user = User::factory()->create();
        $config = PaymentConfig::factory()->create(['is_active' => true]);

        $freePackage = SubscriptionPackage::create([
            'name' => 'Free Trial (7 Days)',
            'price' => 0,
            'duration_days' => 7,
            'features' => [],
        ]);

        // First checkout: activates instantly
        $res1 = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $freePackage->id,
        ]);
        $res1->assertStatus(200);

        $user->unsetRelation('activeSubscription');
        $this->assertNotNull($user->activeSubscription);
        $this->assertEquals(7, $user->activeSubscription->starts_at->diffInDays($user->activeSubscription->expires_at));

        // Second checkout immediately after: should be blocked or return an error (e.g. 400 Bad Request / 422)
        // because they already have an active/used free subscription.
        // But under the current implementation, it will succeed and extend the subscription!
        $res2 = $this->actingAs($user)->postJson('/api/subscription/checkout', [
            'package_id' => $freePackage->id,
        ]);

        // If the exploit works, it returns 200, which is a vulnerability.
        // We assert that it is BLOCKED (e.g. status code 400 or 422) to verify if the vulnerability is present.
        $res2->assertStatus(400); // This will FAIL because current code returns 200 and extends the trial.
    }

    /**
     * Test package deletion is blocked if there are associated subscriptions or transactions.
     */
    public function test_package_delete_association_prevention(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // 1. Package with subscription
        $packageWithSub = SubscriptionPackage::create([
            'name' => 'Package with Sub',
            'price' => 100000,
            'duration_days' => 30,
            'features' => [],
        ]);
        UserSubscription::factory()->create([
            'user_id' => $admin->id,
            'subscription_package_id' => $packageWithSub->id,
        ]);

        // 2. Package with transaction
        $packageWithTx = SubscriptionPackage::create([
            'name' => 'Package with Tx',
            'price' => 200000,
            'duration_days' => 30,
            'features' => [],
        ]);
        Transaction::factory()->create([
            'user_id' => $admin->id,
            'subscription_package_id' => $packageWithTx->id,
        ]);

        // 3. Package with no associations
        $cleanPackage = SubscriptionPackage::create([
            'name' => 'Clean Package',
            'price' => 300000,
            'duration_days' => 30,
            'features' => [],
        ]);

        // Attempt deleting package with subscription - should be blocked
        $response1 = $this->actingAs($admin)->delete("/admin/packages/{$packageWithSub->id}");
        $response1->assertSessionHasErrors('error');
        $this->assertDatabaseHas('subscription_packages', ['id' => $packageWithSub->id]);

        // Attempt deleting package with transaction - should be blocked
        $response2 = $this->actingAs($admin)->delete("/admin/packages/{$packageWithTx->id}");
        $response2->assertSessionHasErrors('error');
        $this->assertDatabaseHas('subscription_packages', ['id' => $packageWithTx->id]);

        // Attempt deleting clean package - should succeed
        $response3 = $this->actingAs($admin)->delete("/admin/packages/{$cleanPackage->id}");
        $response3->assertSessionHas('success');
        $this->assertDatabaseMissing('subscription_packages', ['id' => $cleanPackage->id]);
    }

    /**
     * Test package CRUD endpoints and validation support for min -1.
     */
    public function test_package_crud_validation_min_minus_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // 1. Check creating package with stream limit -1 succeeds
        $response1 = $this->actingAs($admin)->post('/admin/packages', [
            'name' => 'Unlimited Stream Pack',
            'price' => 500000,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => -1,
                'max_duration_hours' => 5,
                'ai_credits' => 5000,
                'audio_analysis' => true,
                'export_leads' => true,
            ],
        ]);
        $response1->assertSessionHas('success');
        $this->assertDatabaseHas('subscription_packages', [
            'name' => 'Unlimited Stream Pack',
            'price' => 500000,
        ]);

        // 2. Check creating package with limit_streams -2 fails validation
        $response2 = $this->actingAs($admin)->post('/admin/packages', [
            'name' => 'Invalid Stream Pack',
            'price' => 500000,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => -2,
                'max_duration_hours' => 5,
                'ai_credits' => 5000,
                'audio_analysis' => true,
                'export_leads' => true,
            ],
        ]);
        $response2->assertSessionHasErrors(['features.limit_streams']);

        // 3. Check updating package with max_duration_hours -1 succeeds
        $package = SubscriptionPackage::where('name', 'Unlimited Stream Pack')->first();
        $response3 = $this->actingAs($admin)->put("/admin/packages/{$package->id}", [
            'name' => 'Unlimited Stream Pack Updated',
            'price' => 600000,
            'duration_days' => 60,
            'features' => [
                'limit_streams' => -1,
                'max_duration_hours' => -1,
                'ai_credits' => 10000,
                'audio_analysis' => true,
                'export_leads' => true,
            ],
        ]);
        $response3->assertSessionHas('success');
        $this->assertDatabaseHas('subscription_packages', [
            'id' => $package->id,
            'name' => 'Unlimited Stream Pack Updated',
            'price' => 600000,
        ]);
    }
}
