<?php

namespace Tests\Feature;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardAndUsersTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentConfig $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Create active payment config
        $this->paymentConfig = PaymentConfig::factory()->create([
            'is_active' => true,
        ]);

        // Seed packages
        SubscriptionPackage::create([
            'name' => 'Free',
            'price' => 0,
            'duration_days' => 30,
            'features' => [],
        ]);

        SubscriptionPackage::create([
            'name' => 'Pro',
            'price' => 199000,
            'duration_days' => 30,
            'features' => [],
        ]);

        SubscriptionPackage::create([
            'name' => 'Enterprise',
            'price' => 499000,
            'duration_days' => 30,
            'features' => [],
        ]);
    }

    public function test_admin_dashboard_metrics_and_recent_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $user1 = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Create transactions
        Transaction::create([
            'user_id' => $user1->id,
            'payment_config_id' => $this->paymentConfig->id,
            'subscription_package_id' => SubscriptionPackage::where('name', 'Pro')->first()->id,
            'transaction_id' => 'TX1',
            'amount' => 199000,
            'status' => 'success',
            'created_at' => now(),
        ]);

        Transaction::create([
            'user_id' => $user2->id,
            'payment_config_id' => $this->paymentConfig->id,
            'subscription_package_id' => SubscriptionPackage::where('name', 'Enterprise')->first()->id,
            'transaction_id' => 'TX2',
            'amount' => 499000,
            'status' => 'failed', // non-success
            'created_at' => now(),
        ]);

        // Create active subscription for user1 (Pro)
        UserSubscription::create([
            'user_id' => $user1->id,
            'subscription_package_id' => SubscriptionPackage::where('name', 'Pro')->first()->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();

        // Verify stats array shows correct title and sum of success transactions
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats', 4)
            ->where('stats.2.title', 'Tổng doanh thu')
            ->where('stats.2.value', '199,000đ') // Only success transaction
            ->has('recentUsers', 3) // admin, user1, user2
        );

        // Verify recent users mapping has the resolved plan name
        $recentUsers = $response->viewData('page')['props']['recentUsers'];

        $u1Data = collect($recentUsers)->firstWhere('email', $user1->email);
        $this->assertEquals('Pro', $u1Data['plan']);

        $u2Data = collect($recentUsers)->firstWhere('email', $user2->email);
        $this->assertEquals('Free', $u2Data['plan']); // User2 transaction failed, no subscription active, so resolves to Free
    }

    public function test_admin_users_index_shows_correct_plan_name(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $userPro = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        UserSubscription::create([
            'user_id' => $userPro->id,
            'subscription_package_id' => SubscriptionPackage::where('name', 'Pro')->first()->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users')
        );

        $users = $response->viewData('page')['props']['users'];

        $uProData = collect($users)->firstWhere('email', $userPro->email);
        $this->assertEquals('Pro', $uProData['plan_name']);

        $adminData = collect($users)->firstWhere('email', $admin->email);
        $this->assertEquals('Free', $adminData['plan_name']);
    }
}
