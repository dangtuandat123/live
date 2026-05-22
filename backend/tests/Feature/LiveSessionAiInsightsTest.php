<?php

namespace Tests\Feature;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\RunwareAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LiveSessionAiInsightsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that ai_insights and ai_alerts can be filled and retrieved correctly on LiveSession.
     */
    public function test_ai_insights_and_alerts_are_fillable_and_cast_correctly()
    {
        $user = User::factory()->create();

        $insightsText = 'This is a detailed AI insights report for the live session.';
        $alertsArray = [
            [
                'type' => 'negative_sentiment',
                'message' => 'High rate of negative comments detected',
                'timestamp' => '2026-05-22T10:00:00Z',
            ],
            [
                'type' => 'spam',
                'message' => 'Possible spam bot detected in live chat',
                'timestamp' => '2026-05-22T10:05:00Z',
            ],
        ];

        // Create live session with the new attributes
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'AI Session Test',
            'tiktok_username' => 'test_user_ai',
            'status' => 'live',
            'ai_insights' => $insightsText,
            'ai_alerts' => $alertsArray,
        ]);

        // Refresh model from database
        $session->refresh();

        // Assert attributes
        $this->assertEquals($insightsText, $session->ai_insights);
        $this->assertIsArray($session->ai_alerts);
        $this->assertCount(2, $session->ai_alerts);
        $this->assertEquals('negative_sentiment', $session->ai_alerts[0]['type']);
        $this->assertEquals('spam', $session->ai_alerts[1]['type']);
    }

    /**
     * Test that ai_insights and ai_alerts can be null.
     */
    public function test_ai_insights_and_alerts_can_be_nullable()
    {
        $user = User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'AI Session Test Nullable',
            'tiktok_username' => 'test_user_ai_null',
            'status' => 'live',
            'ai_insights' => null,
            'ai_alerts' => null,
        ]);

        $session->refresh();

        $this->assertNull($session->ai_insights);
        $this->assertNull($session->ai_alerts);
    }

    public function test_livesessionanalyzer_instructions_and_schema_are_valid()
    {
        $analyzer = new LiveSessionAnalyzer;
        $this->assertNotEmpty($analyzer->instructions());

        $schema = new JsonSchemaTypeFactory;
        $schemaArray = $analyzer->schema($schema);

        $this->assertArrayHasKey('summary', $schemaArray);
        $this->assertArrayHasKey('alerts', $schemaArray);
        $this->assertInstanceOf(StringType::class, $schemaArray['summary']);
        $this->assertInstanceOf(ArrayType::class, $schemaArray['alerts']);
    }

    /**
     * Test manual refresh insights endpoint.
     */
    public function test_manual_refresh_insights_endpoint_works_and_validates_ownership()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Manual Refresh Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        $mockSummary = 'Livestream is going well.';
        $mockAlerts = [
            [
                'type' => 'pricing_question',
                'title' => 'High price interest',
                'description' => 'Users asking about pricing',
                'action' => 'Mention product price',
            ],
        ];

        $this->mock(RunwareAiService::class, function ($mock) use ($mockSummary, $mockAlerts) {
            $mock->shouldReceive('chatJson')
                ->once()
                ->andReturn([
                    'summary' => $mockSummary,
                    'alerts' => $mockAlerts,
                ]);
        });

        $response = $this->actingAs($user)
            ->post(route('lives.refresh-insights', $session));

        $response->assertStatus(200);
        $response->assertJson([
            'ai_insights' => $mockSummary,
            'ai_alerts' => $mockAlerts,
        ]);

        $session->refresh();
        $this->assertEquals($mockSummary, $session->ai_insights);
        $this->assertEquals($mockAlerts, $session->ai_alerts);

        $cacheKey = "live_session_{$session->id}_last_insight_time";
        $this->assertNotNull(Cache::get($cacheKey));

        // Test ownership validation: another user should get 403
        $otherUser = User::factory()->create();
        $response2 = $this->actingAs($otherUser)
            ->post(route('lives.refresh-insights', $session));
        $response2->assertStatus(403);
    }

    /**
     * Test manual refresh insights requires authentication.
     */
    public function test_manual_refresh_insights_requires_authentication()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Guest Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        $response = $this->post(route('lives.refresh-insights', $session));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test auto insights trigger runs when throttle is expired/not set.
     */
    public function test_auto_insights_trigger_runs_if_throttle_expired()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Auto Insights Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 1'],
            'ai_processed' => false,
        ]);

        $mockSummary = 'Auto summary.';
        $mockAlerts = [['type' => 'test', 'title' => 'Test', 'description' => 'Test desc', 'action' => 'Test action']];

        $this->mock(RunwareAiService::class, function ($mock) use ($comment, $mockSummary, $mockAlerts) {
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
                    'session_note' => 'Processed batch.',
                ]);

            $mock->shouldReceive('chatJson')
                ->once()
                ->andReturn([
                    'summary' => $mockSummary,
                    'alerts' => $mockAlerts,
                ]);
        });

        // Ensure throttle is not set
        $cacheKey = "live_session_{$session->id}_last_insight_time";
        Cache::forget($cacheKey);

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertEquals($mockSummary, $session->ai_insights);
        $this->assertEquals($mockAlerts, $session->ai_alerts);
        $this->assertNotNull(Cache::get($cacheKey));
    }

    /**
     * Test auto insights trigger throttles within 30 seconds.
     */
    public function test_auto_insights_trigger_throttles_within_30_seconds()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Throttle Insights Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
            'ai_insights' => 'old insights',
            'ai_alerts' => [],
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 1'],
            'ai_processed' => false,
        ]);

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
                    'session_note' => 'Processed batch.',
                ]);

            $mock->shouldNotReceive('chatJson');
        });

        // Set last insight time to 10 seconds ago
        $cacheKey = "live_session_{$session->id}_last_insight_time";
        Cache::put($cacheKey, now()->timestamp - 10);

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertEquals('old insights', $session->ai_insights);
        $this->assertEquals([], $session->ai_alerts);
    }

    /**
     * Test fetchEvents response includes ai_insights and ai_alerts.
     */
    public function test_fetchevents_response_includes_ai_insights_and_alerts()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Fetch Events test',
            'tiktok_username' => 'testuser',
            'status' => 'ended',
            'tiktok_session_id' => 'mocked-tiktok-session-id',
            'ai_insights' => 'Mocked insights text',
            'ai_alerts' => [['type' => 'alert_1']],
        ]);

        $response = $this->actingAs($user)
            ->post(route('lives.fetch-events', $session->id));

        $response->assertStatus(200);
        $response->assertJson([
            'ai_insights' => 'Mocked insights text',
            'ai_alerts' => [['type' => 'alert_1']],
        ]);
    }

    /**
     * Test manual refresh insights endpoint throttles within 30 seconds.
     */
    public function test_manual_refresh_insights_endpoint_throttles()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Throttled Refresh Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        // Put the cache key indicating a refresh happened 10 seconds ago
        $cacheKey = "live_session_{$session->id}_last_insight_time";
        Cache::put($cacheKey, now()->timestamp - 10);

        $response = $this->actingAs($user)
            ->post(route('lives.refresh-insights', $session));

        $response->assertStatus(429);
        $response->assertJson([
            'error' => 'Vui lòng đợi 20 giây trước khi yêu cầu làm mới tiếp theo.',
        ]);
    }

    /**
     * Test manual refresh insights endpoint is gated by subscription AI credits.
     */
    public function test_manual_refresh_insights_endpoint_gated_by_credits()
    {
        $user = User::factory()->create();

        $package = SubscriptionPackage::create([
            'name' => 'Test Package',
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

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 10, // Maxed out
        ]);

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Credit Gated Refresh Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        $response = $this->actingAs($user)
            ->post(route('lives.refresh-insights', $session));

        $response->assertStatus(402);
        $response->assertJson([
            'error' => 'Đã hết tín dụng AI của gói dịch vụ.',
        ]);
    }

    /**
     * Test manual refresh insights endpoint handles Runware AI service exceptions.
     */
    public function test_manual_refresh_insights_endpoint_handles_exceptions()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Exception Refresh Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        $this->mock(RunwareAiService::class, function ($mock) {
            $mock->shouldReceive('chatJson')
                ->once()
                ->andThrow(new \RuntimeException('AI service error'));
        });

        // Ensure no throttle is set
        $cacheKey = "live_session_{$session->id}_last_insight_time";
        Cache::forget($cacheKey);

        $response = $this->actingAs($user)
            ->post(route('lives.refresh-insights', $session));

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Dịch vụ AI hiện đang bận. Vui lòng thử lại sau.',
        ]);
    }

    /**
     * Test manual refresh insights endpoint increments used_ai_credits.
     */
    public function test_manual_refresh_insights_endpoint_increments_credits()
    {
        $user = User::factory()->create();

        $package = SubscriptionPackage::create([
            'name' => 'Test Package',
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

        $sub = $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 5,
        ]);

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Credit Increment Test',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);

        // Create 3 comment events
        for ($i = 0; $i < 3; $i++) {
            LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now(),
                'tiktok_user_id' => "user_$i",
                'tiktok_nickname' => "nickname_$i",
                'data' => ['comment' => "comment $i"],
                'ai_processed' => false,
            ]);
        }

        $mockSummary = 'Insights summary.';
        $mockAlerts = [];

        $this->mock(RunwareAiService::class, function ($mock) use ($mockSummary, $mockAlerts) {
            $mock->shouldReceive('chatJson')
                ->once()
                ->andReturn([
                    'summary' => $mockSummary,
                    'alerts' => $mockAlerts,
                ]);
        });

        // Ensure no throttle is set
        $cacheKey = "live_session_{$session->id}_last_insight_time";
        Cache::forget($cacheKey);

        $response = $this->actingAs($user)
            ->post(route('lives.refresh-insights', $session));

        $response->assertStatus(200);

        $sub->refresh();
        // Since there were 3 comments, credits should increment by 3: 5 + 3 = 8
        $this->assertEquals(8, $sub->used_ai_credits);
    }
}
