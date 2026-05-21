<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_limit_gating()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 1,
                'ai_credits' => 1000,
                'audio_analysis' => false,
                'export_leads' => false,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        // Create first active session (connecting status counts as active)
        LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Session 1',
            'tiktok_username' => 'user1',
            'status' => 'connecting',
        ]);

        // Try to create second session, should throw ValidationException
        $this->actingAs($user);

        $response = $this->postJson(route('lives.store'), [
            'name' => 'Session 2',
            'tiktok_username' => 'user2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tiktok_username');
    }

    public function test_stream_duration_limit_gating()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 2,
                'ai_credits' => 1000,
                'audio_analysis' => false,
                'export_leads' => false,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        // Create a live session started 3 hours ago (max duration is 2 hours)
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Long Session',
            'tiktok_username' => 'user1',
            'status' => 'live',
            'started_at' => now()->subHours(3),
            'tiktok_session_id' => 'dummy-tiktok-session',
        ]);

        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('stopSession')
                ->once()
                ->with('dummy-tiktok-session');
        });

        $this->actingAs($user);

        // Fetching the session show route should trigger the auto-stop duration check
        $response = $this->get(route('lives.show', $session));

        $session->refresh();

        $this->assertEquals('ended', $session->status);
        $this->assertNotNull($session->ended_at);
        $this->assertStringContainsString('vượt quá thời lượng tối đa cho phép', $session->error_message);
    }

    public function test_ai_credits_limit_gating()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 1,
                'ai_credits' => 10,
                'audio_analysis' => false,
                'export_leads' => false,
            ],
        ]);

        $sub = $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 10, // Maxed out
        ]);

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'AI Gated Session',
            'tiktok_username' => 'user1',
            'status' => 'live',
        ]);

        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test'],
            'ai_processed' => false,
        ]);

        // Mock TikTokService and RunwareAiService so they don't do real work
        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) {
            $mock->shouldNotReceive('chatMultimodal');
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();

        $this->assertEquals('error', $session->status);
        $this->assertEquals('Đã hết tín dụng AI của gói dịch vụ.', $session->error_message);
    }

    public function test_audio_analysis_gating()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 1,
                'ai_credits' => 1000,
                'audio_analysis' => false, // Gated
                'export_leads' => false,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Audio Gated Session',
            'tiktok_username' => 'user1',
            'status' => 'live',
            'tiktok_session_id' => 'dummy-tiktok-session',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test comments'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class, function ($mock) {
            // Should NEVER be called because audio_analysis is false
            $mock->shouldNotReceive('getSnapshot');
        });

        $this->mock(RunwareAiService::class, function ($mock) use ($comment) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => null,
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'No audio used.',
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);
    }

    public function test_inertia_props_sharing()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 1,
                'ai_credits' => 1000,
                'audio_analysis' => false,
                'export_leads' => false,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 5,
        ]);

        $this->actingAs($user);

        // Check if shared subscription data contains correct values
        $response = $this->get('/dashboard');

        $response->assertStatus(200);

        $inertiaShared = $response->original->getData()['page']['props']['auth']['subscription'] ?? null;

        $this->assertNotNull($inertiaShared);
        $this->assertTrue($inertiaShared['active']);
        $this->assertEquals($package->id, $inertiaShared['package_id']);
        $this->assertEquals('Trial Package', $inertiaShared['package_name']);
        $this->assertEquals(5, $inertiaShared['used_ai_credits']);
        $this->assertEquals(1000, $inertiaShared['features']['ai_credits']);
    }

    public function test_subscription_route_props()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Trial Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => 1,
                'max_duration_hours' => 1,
                'ai_credits' => 1000,
                'audio_analysis' => false,
                'export_leads' => false,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 5,
        ]);

        $config = PaymentConfig::factory()->create(['is_active' => true]);

        // Create transaction
        Transaction::create([
            'transaction_id' => 'TX_1234567890',
            'user_id' => $user->id,
            'amount' => 100000,
            'payment_config_id' => $config->id,
            'subscription_package_id' => $package->id,
            'status' => 'success',
        ]);

        $this->actingAs($user);

        $response = $this->get('/subscription');
        $response->assertStatus(200);

        $pageProps = $response->original->getData()['page']['props'];

        // Assert transactions prop exists
        $this->assertArrayHasKey('transactions', $pageProps);
        $this->assertCount(1, $pageProps['transactions']);
        $this->assertEquals('TX_1234567890', $pageProps['transactions'][0]['transaction_id']);
        $this->assertEquals('Trial Package', $pageProps['transactions'][0]['package_name']);

        // Assert activeSubscription prop contains extended info
        $this->assertArrayHasKey('activeSubscription', $pageProps);
        $this->assertEquals(5, $pageProps['activeSubscription']['used_ai_credits']);
        $this->assertArrayHasKey('features', $pageProps['activeSubscription']);
        $this->assertEquals(1000, $pageProps['activeSubscription']['features']['ai_credits']);
    }
}
