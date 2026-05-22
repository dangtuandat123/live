<?php

namespace Tests\Feature;

use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiveSessionUIIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_inertia_props_contains_potential_customers_count_and_top_keywords()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Integration Session',
            'tiktok_username' => 'test_user_show',
            'status' => 'live',
        ]);
        $session->keywords()->create(['keyword' => 'kem']);
        $session->keywords()->create(['keyword' => 'son']);

        // Create some distinct users with order intent or phone number
        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_1',
            'tiktok_nickname' => 'Customer One',
            'event_at' => now(),
            'ai_processed' => true,
            'intent_tag' => 'Chốt đơn',
            'data' => ['comment' => 'Mua kem'],
        ]);

        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_2',
            'tiktok_nickname' => 'Customer Two',
            'event_at' => now(),
            'ai_processed' => true,
            'has_phone' => true,
            'data' => ['comment' => '0912345678'],
        ]);

        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_1', // same distinct user
            'tiktok_nickname' => 'Customer One',
            'event_at' => now(),
            'ai_processed' => true,
            'has_phone' => true,
            'data' => ['comment' => '0912345679'],
        ]);

        // Mention a product keyword
        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_3',
            'tiktok_nickname' => 'Customer Three',
            'event_at' => now(),
            'ai_processed' => true,
            'data' => ['comment' => 'Kem nay ngon qua'],
        ]);

        $this->actingAs($user);

        $response = $this->get(route('lives.show', $session->id));

        $response->assertStatus(200);

        // Assert that the page is rendered with Inertia and contains the expected props
        $response->assertInertia(function (Assert $page) {
            $page->component('Lives/Show')
                ->has('potentialCustomersCount')
                ->has('topKeywords')
                ->where('potentialCustomersCount', 2);
        });

        // Assert that potentialCustomersCount is cached
        $cacheKey = "live_session_{$session->id}_potential_customers_count";
        $this->assertEquals(2, Cache::get($cacheKey));
    }

    public function test_fetch_events_returns_potential_customers_count_and_top_keywords_json()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Fetch Events Session',
            'tiktok_username' => 'test_user_fetch',
            'tiktok_session_id' => 'mock_session_id',
            'status' => 'ended',
        ]);
        $session->keywords()->create(['keyword' => 'kem']);
        $session->keywords()->create(['keyword' => 'son']);

        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_abc',
            'tiktok_nickname' => 'Customer ABC',
            'event_at' => now(),
            'ai_processed' => true,
            'intent_tag' => 'Chốt đơn',
            'data' => ['comment' => 'Mua son'],
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('lives.fetch-events', $session->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'potentialCustomersCount',
            'topKeywords',
        ]);

        $this->assertEquals(1, $response->json('potentialCustomersCount'));
        $topKeywords = $response->json('topKeywords');
        $this->assertNotEmpty($topKeywords);

        $sonKw = collect($topKeywords)->firstWhere('keyword', 'son');
        $this->assertNotNull($sonKw);
        $this->assertEquals(1, $sonKw['count']);
    }

    public function test_updating_event_clears_cache()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Cache Clear Session',
            'tiktok_username' => 'test_user_cache',
            'status' => 'live',
        ]);
        $session->keywords()->create(['keyword' => 'kem']);

        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_user_id' => 'cust_xyz',
            'tiktok_nickname' => 'Customer XYZ',
            'event_at' => now(),
            'ai_processed' => true,
            'data' => ['comment' => 'Hello'],
        ]);

        // Prime cache
        $cacheQuestions = "live_session_{$session->id}_top_questions";
        $cacheKeywords = "live_session_{$session->id}_top_keywords";
        $cacheCustomersCount = "live_session_{$session->id}_potential_customers_count";

        Cache::put($cacheQuestions, 'cached_questions', 60);
        Cache::put($cacheKeywords, 'cached_keywords', 60);
        Cache::put($cacheCustomersCount, 'cached_count', 60);

        $this->actingAs($user);

        // Update the event
        $response = $this->putJson(route('live-events.update', $event->id), [
            'is_pinned' => true,
        ]);

        $response->assertStatus(200);

        // Assert caches are cleared
        $this->assertNull(Cache::get($cacheQuestions));
        $this->assertNull(Cache::get($cacheKeywords));
        $this->assertNull(Cache::get($cacheCustomersCount));
    }
}
