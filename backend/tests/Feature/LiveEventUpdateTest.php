<?php

namespace Tests\Feature;

use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveEventUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_live_event()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Live Session',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);
        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_nickname' => 'John Doe',
            'tiktok_unique_id' => 'johndoe',
            'data' => ['comment' => 'Hello'],
            'event_at' => now(),
        ]);

        $response = $this->putJson(route('live-events.update', $event->id), [
            'is_pinned' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_owner_cannot_update_live_event()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'name' => 'Live Session',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);
        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_nickname' => 'John Doe',
            'tiktok_unique_id' => 'johndoe',
            'data' => ['comment' => 'Hello'],
            'event_at' => now(),
        ]);

        $this->actingAs($otherUser);

        $response = $this->putJson(route('live-events.update', $event->id), [
            'is_pinned' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_update_live_event_fields_and_data_json()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Live Session',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);
        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_nickname' => 'John Doe',
            'tiktok_unique_id' => 'johndoe',
            'data' => ['comment' => 'Hello'],
            'event_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->putJson(route('live-events.update', $event->id), [
            'is_pinned' => true,
            'is_highlighted' => true,
            'sort_order' => 42,
            'qty' => 5,
            'note' => 'Customer wants blue color',
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('event.is_pinned', true);
        $response->assertJsonPath('event.is_highlighted', true);
        $response->assertJsonPath('event.sort_order', 42);
        $response->assertJsonPath('event.qty', 5);
        $response->assertJsonPath('event.note', 'Customer wants blue color');
        $response->assertJsonPath('event.status', 'confirmed');

        $event->refresh();
        $this->assertTrue((bool) $event->is_pinned);
        $this->assertTrue((bool) $event->is_highlighted);
        $this->assertEquals(42, $event->sort_order);
        $this->assertEquals(5, $event->data['qty']);
        $this->assertEquals('Customer wants blue color', $event->data['note']);
        $this->assertEquals('confirmed', $event->data['status']);
    }

    public function test_api_live_events_route_prefix_check()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Live Session',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);
        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_nickname' => 'John Doe',
            'tiktok_unique_id' => 'johndoe',
            'data' => ['comment' => 'Hello'],
            'event_at' => now(),
        ]);

        $this->actingAs($user);

        // Frontend calls "/api/live-events/{id}". Let's test if this URL returns 404 or works.
        $response = $this->putJson("/api/live-events/{$event->id}", [
            'is_pinned' => true,
        ]);

        // If this returns 404, it proves a frontend-backend URL mismatch bug!
        $this->assertEquals(200, $response->getStatusCode(), "The frontend fetches '/api/live-events/{id}' and it should resolve correctly!");
    }

    public function test_invalid_validation_inputs_on_update_live_event()
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Live Session',
            'tiktok_username' => 'testuser',
            'status' => 'live',
        ]);
        $event = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'tiktok_nickname' => 'John Doe',
            'tiktok_unique_id' => 'johndoe',
            'data' => ['comment' => 'Hello'],
            'event_at' => now(),
        ]);

        $this->actingAs($user);

        // Test invalid data types (e.g. is_pinned is not boolean, sort_order/qty is not integer)
        $response = $this->putJson(route('live-events.update', $event->id), [
            'is_pinned' => 'not-a-boolean',
            'qty' => 'not-an-integer',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['is_pinned', 'qty']);
    }
}
