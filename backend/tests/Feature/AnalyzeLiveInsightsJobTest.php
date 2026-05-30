<?php

namespace Tests\Feature;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Jobs\AnalyzeLiveInsightsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyzeLiveInsightsJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeLiveSession(User $user): LiveSession
    {
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Insights Job Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'sản phẩm này giá bao nhiêu'],
            'ai_processed' => true,
        ]);

        return $session;
    }

    public function test_it_generates_and_saves_insights(): void
    {
        $user = User::factory()->create();
        $session = $this->makeLiveSession($user);

        LiveSessionAnalyzer::fake([
            [
                'summary' => 'Phiên live diễn ra ổn định.',
                'alerts' => [
                    ['type' => 'info', 'title' => 'Quan tâm giá', 'desc' => 'Khách hỏi giá', 'action' => 'Báo giá ngay'],
                ],
            ],
        ]);

        Cache::forget(AnalyzeLiveInsightsJob::cacheKey($session->id));

        $job = new AnalyzeLiveInsightsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertEquals('Phiên live diễn ra ổn định.', $session->ai_insights);
        $this->assertEquals('info', $session->ai_alerts[0]['type']);
        $this->assertNotNull(Cache::get(AnalyzeLiveInsightsJob::cacheKey($session->id)));
    }

    public function test_it_throttles_within_window(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Throttle Job Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
            'ai_insights' => 'old insights',
            'ai_alerts' => [],
        ]);

        // Faked but must never be prompted because of throttle.
        LiveSessionAnalyzer::fake([
            ['summary' => 'should not run', 'alerts' => []],
        ]);

        Cache::put(AnalyzeLiveInsightsJob::cacheKey($session->id), now()->timestamp - 5);

        $job = new AnalyzeLiveInsightsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertEquals('old insights', $session->ai_insights);
        LiveSessionAnalyzer::assertNeverPrompted();
    }

    public function test_it_charges_fixed_credit_cost(): void
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
            'used_ai_credits' => 0,
        ]);

        $session = $this->makeLiveSession($user);

        LiveSessionAnalyzer::fake([
            ['summary' => 'Tổng kết.', 'alerts' => []],
        ]);

        Cache::forget(AnalyzeLiveInsightsJob::cacheKey($session->id));

        $job = new AnalyzeLiveInsightsJob($session->id);
        app()->call([$job, 'handle']);

        $sub->refresh();
        $this->assertEquals(LiveSessionAnalyzer::INSIGHTS_CREDIT_COST, $sub->used_ai_credits);
    }

    public function test_it_skips_when_credits_exhausted(): void
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
            'used_ai_credits' => 10, // maxed out
        ]);

        $session = $this->makeLiveSession($user);

        LiveSessionAnalyzer::fake([
            ['summary' => 'should not run', 'alerts' => []],
        ]);

        Cache::forget(AnalyzeLiveInsightsJob::cacheKey($session->id));

        $job = new AnalyzeLiveInsightsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertNull($session->ai_insights);
        LiveSessionAnalyzer::assertNeverPrompted();
    }

    public function test_it_skips_for_non_active_session(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Ended Session',
            'status' => 'ended',
            'tiktok_username' => 'testuser',
        ]);

        LiveSessionAnalyzer::fake([
            ['summary' => 'should not run', 'alerts' => []],
        ]);

        Cache::forget(AnalyzeLiveInsightsJob::cacheKey($session->id));

        $job = new AnalyzeLiveInsightsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertNull($session->ai_insights);
        LiveSessionAnalyzer::assertNeverPrompted();
    }
}
