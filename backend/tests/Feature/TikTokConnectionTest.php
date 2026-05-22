<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokConnectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest users are redirected to login.
     */
    public function test_guest_is_redirected_to_login()
    {
        // Connecting TikTok
        $response = $this->post(route('settings.tiktok.connect'), [
            'tiktok_username' => 'testuser',
        ]);
        $response->assertRedirect(route('login'));

        // Disconnecting TikTok
        $response = $this->post(route('settings.tiktok.disconnect'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test connecting a valid TikTok account successfully updates settings.
     */
    public function test_connect_valid_tiktok_username()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Connect username without @
        $response = $this->post(route('settings.tiktok.connect'), [
            'tiktok_username' => 'my_tiktok_user',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Đã kết nối tài khoản TikTok thành công.');

        $user->refresh();
        $this->assertEquals('@my_tiktok_user', $user->getSettingsWithDefaults()['tiktok_username']);

        // Connect username with @
        $response = $this->post(route('settings.tiktok.connect'), [
            'tiktok_username' => '@another_user',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Đã kết nối tài khoản TikTok thành công.');

        $user->refresh();
        $this->assertEquals('@another_user', $user->getSettingsWithDefaults()['tiktok_username']);
    }

    /**
     * Test validation error when username is empty.
     */
    public function test_connect_empty_tiktok_username_throws_validation_error()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson(route('settings.tiktok.connect'), [
            'tiktok_username' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tiktok_username']);
    }

    /**
     * Test disconnecting TikTok account resets username to null.
     */
    public function test_disconnect_tiktok_removes_from_settings()
    {
        $user = User::factory()->create([
            'settings' => [
                'tiktok_username' => '@some_user',
                'ai_language' => 'vi',
            ]
        ]);

        $this->actingAs($user);

        $response = $this->post(route('settings.tiktok.disconnect'));

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Đã ngắt kết nối tài khoản TikTok.');

        $user->refresh();
        $this->assertNull($user->getSettingsWithDefaults()['tiktok_username']);
        // Verify other settings are preserved
        $this->assertEquals('vi', $user->getSettingsWithDefaults()['ai_language']);
    }

    /**
     * Test settings index endpoint returns correct metrics and default settings.
     */
    public function test_settings_page_returns_correct_metrics_and_defaults()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Pro Package',
            'price' => 299000,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 5,
                'max_duration_hours' => 3,
                'ai_credits' => 5000,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
            'status' => 'active',
            'used_ai_credits' => 10,
        ]);

        // Active streams (live or connecting)
        LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Session 1',
            'tiktok_username' => 'user1',
            'status' => 'live',
        ]);
        LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Session 2',
            'tiktok_username' => 'user2',
            'status' => 'connecting',
        ]);
        // Ended stream
        LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Session 3',
            'tiktok_username' => 'user3',
            'status' => 'ended',
        ]);
        // Session before cycle start
        $session4 = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Session 4',
            'tiktok_username' => 'user4',
            'status' => 'ended',
        ]);
        $session4->created_at = now()->subDays(6);
        $session4->save();

        $this->actingAs($user);

        $response = $this->get(route('settings.index'));

        $response->assertStatus(200);

        // Assert direct props passed to Settings/Index page
        $pageProps = $response->original->getData()['page']['props'];
        $this->assertEquals(2, $pageProps['activeStreamsCount']); // Session 1 and 2
        $this->assertEquals(3, $pageProps['totalSessionsInCycle']); // Session 1, 2, 3 created within sub cycle (last 5 days)
        $this->assertNull($pageProps['settings']['tiktok_username']); // defaults to null
    }

    /**
     * Test that limit gating parameters are shared in Inertia middleware correctly.
     */
    public function test_inertia_middleware_shares_subscription_metrics()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 10000,
            'duration_days' => 15,
            'features' => [
                'limit_streams' => 2,
                'max_duration_hours' => 2,
                'ai_credits' => 100,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(13),
            'status' => 'active',
            'used_ai_credits' => 15,
        ]);

        // 1 active streams
        LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Active Session',
            'tiktok_username' => 'user1',
            'status' => 'live',
        ]);

        $this->actingAs($user);

        // Fetch settings page instead of dashboard to avoid mysql-specific DATE_FORMAT
        $response = $this->get(route('settings.index'));
        $response->assertStatus(200);

        $subscriptionShared = $response->original->getData()['page']['props']['auth']['subscription'] ?? null;

        $this->assertNotNull($subscriptionShared);
        $this->assertTrue($subscriptionShared['active']);
        $this->assertEquals('Trial Package', $subscriptionShared['package_name']);
        $this->assertEquals(10000, $subscriptionShared['price']);
        $this->assertEquals(15, $subscriptionShared['duration_days']);
        $this->assertEquals(1, $subscriptionShared['active_streams_count']);
        $this->assertEquals(1, $subscriptionShared['total_sessions_in_cycle']);
    }
}
