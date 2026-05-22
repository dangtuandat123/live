<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LiveSession;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokUsernameValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test connecting a TikTok username containing spaces or special characters via Settings route.
     */
    public function test_connect_invalid_tiktok_username_in_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test space character
        $response1 = $this->post(route('settings.tiktok.connect'), [
            'tiktok_username' => 'john doe',
        ]);
        $response1->assertRedirect();
        $user->refresh();
        $this->assertEquals('@john doe', $user->getSettingsWithDefaults()['tiktok_username']);

        // Test special character
        $response2 = $this->post(route('settings.tiktok.connect'), [
            'tiktok_username' => 'john$doe',
        ]);
        $response2->assertRedirect();
        $user->refresh();
        $this->assertEquals('@john$doe', $user->getSettingsWithDefaults()['tiktok_username']);
    }

    /**
     * Test creating a live session with a TikTok username containing spaces or special characters.
     */
    public function test_create_session_with_invalid_tiktok_username()
    {
        // Mock TikTokService to avoid actual connection
        $this->mock(\App\Services\TikTokService::class, function ($mock) {
            $mock->shouldReceive('startSession')->andReturn([
                'session_id' => 'mock-session-id',
                'status' => 'connecting',
            ]);
        });

        $user = User::factory()->create();

        // Create an unlimited package to avoid hitting active stream limits
        $package = SubscriptionPackage::create([
            'name' => 'Unlimited Package',
            'price' => 0,
            'duration_days' => 30,
            'features' => [
                'limit_streams' => -1, // Unlimited
                'max_duration_hours' => -1,
                'ai_credits' => 1000,
            ],
        ]);

        $user->subscriptions()->create([
            'subscription_package_id' => $package->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'used_ai_credits' => 0,
        ]);

        $this->actingAs($user);

        // Test space character
        $response1 = $this->post(route('lives.store'), [
            'name' => 'My Live Stream 1',
            'tiktok_username' => 'john doe',
        ]);
        $response1->assertRedirect();
        $this->assertDatabaseHas('live_sessions', [
            'name' => 'My Live Stream 1',
            'tiktok_username' => 'john doe',
        ]);

        // Test special character
        $response2 = $this->post(route('lives.store'), [
            'name' => 'My Live Stream 2',
            'tiktok_username' => 'john$doe',
        ]);
        $response2->assertRedirect();
        $this->assertDatabaseHas('live_sessions', [
            'name' => 'My Live Stream 2',
            'tiktok_username' => 'john$doe',
        ]);
    }
}
