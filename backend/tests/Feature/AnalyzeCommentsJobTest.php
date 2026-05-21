<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyzeCommentsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_analyzes_comments_and_saves_ai_tags(): void
    {
        // 1. Setup session and comments
        $user = \App\Models\User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 2'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Mã 2 ạ'],
            'ai_processed' => false,
        ]);

        // 2. Mock RunwareAiService — now uses chatMultimodal instead of chatJson
        $this->mock(RunwareAiService::class, function ($mock) use ($comment1, $comment2) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment1->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $comment2->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Đang bán sản phẩm, có 2 khách chốt đơn.',
                ]);
        });

        // Mock TikTokService — getSnapshot returns no audio (graceful fallback)
        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')->andReturn([
                'audio_b64' => null,
                'image_b64' => null,
                'title' => 'Test',
            ]);
        });

        // 3. Run job
        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // 4. Assert database is updated correctly
        $this->assertDatabaseHas('live_events', [
            'id' => $comment1->id,
            'intent_tag' => 'Chốt đơn',
            'sentiment' => 'neutral',
            'ai_processed' => true,
        ]);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment2->id,
            'intent_tag' => 'Chốt đơn',
            'sentiment' => 'neutral',
            'ai_processed' => true,
        ]);
    }

    public function test_system_prompts_contain_key_instructions(): void
    {
        // 1. Check CommentAnalyzer agent instructions (unchanged — used by Laravel AI SDK agent)
        $analyzer = new \App\Ai\Agents\CommentAnalyzer();
        $instructions = $analyzer->instructions();

        $this->assertStringContainsString('Chốt đơn', $instructions);
        $this->assertStringContainsString('cú pháp đặt hàng', $instructions);

        // 2. Check AnalyzeCommentsJob system prompt includes session_note + audio section
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 2'],
            'ai_processed' => false,
        ]);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->withArgs(function ($systemPrompt, $parts) {
                    $hasChotDon = str_contains($systemPrompt, 'Chốt đơn');
                    $hasCuPhap = str_contains($systemPrompt, 'cú pháp đặt hàng');
                    $hasSessionNote = str_contains($systemPrompt, 'session_note');
                    return $hasChotDon && $hasCuPhap && $hasSessionNote;
                })
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ]
                    ],
                    'session_note' => 'Batch đầu tiên, có 1 chốt đơn.',
                ]);
        });

        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')->andReturn([
                'audio_b64' => null,
                'image_b64' => null,
                'title' => 'Test',
            ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);
    }

    public function test_audio_fallback_to_text_only(): void
    {
        // When TikTokService::getSnapshot throws exception, job should still work (text-only fallback)
        $user = \App\Models\User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Audio Fallback Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
            'tiktok_session_id' => 'fake-session-id',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'cho em hỏi giá'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class, function ($mock) {
            // Simulate FFmpeg/network failure
            $mock->shouldReceive('getSnapshot')
                ->once()
                ->andThrow(new \RuntimeException('FFmpeg timeout'));
        });

        $this->mock(RunwareAiService::class, function ($mock) use ($comment) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->withArgs(function ($systemPrompt, $parts) {
                    // Should NOT have audio section in prompt since audio failed
                    $hasAudioSection = str_contains($systemPrompt, 'AUDIO LIVESTREAM');
                    // Parts should only contain text, no audio
                    $hasOnlyTextPart = count($parts) === 1 && $parts[0]['type'] === 'text';
                    return !$hasAudioSection && $hasOnlyTextPart;
                })
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Hỏi thông tin',
                            'question_tag' => 'Hỏi giá',
                            'product_tag' => null,
                            'has_phone' => false,
                        ]
                    ],
                    'session_note' => 'Có khách hỏi giá.',
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment->id,
            'intent_tag' => 'Hỏi thông tin',
            'question_tag' => 'Hỏi giá',
            'ai_processed' => true,
        ]);
    }

    public function test_memory_is_saved_and_loaded(): void
    {
        $user = \App\Models\User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Memory Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // --- Batch 1: AI saves session_note ---
        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'size M còn không'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment1) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment1->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Hỏi thông tin',
                            'question_tag' => 'Hỏi size',
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Đang bán áo thun, nhiều người hỏi size M.',
                ]);
        });

        $job1 = new AnalyzeCommentsJob($session->id);
        app()->call([$job1, 'handle']);

        // Verify memory was saved
        $session->refresh();
        $this->assertEquals('Đang bán áo thun, nhiều người hỏi size M.', $session->ai_context_summary);

        // --- Batch 2: AI receives memory from batch 1 ---
        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(10),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Mã 3'],
            'ai_processed' => false,
        ]);

        // Re-mock services for batch 2
        $this->mock(TikTokService::class);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment2) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->withArgs(function ($systemPrompt, $parts) {
                    // Prompt should contain memory from batch 1
                    return str_contains($systemPrompt, 'Đang bán áo thun, nhiều người hỏi size M.')
                        && str_contains($systemPrompt, 'BỘ NHỚ PHIÊN LIVE');
                })
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment2->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Tiếp tục bán áo thun, có thêm 1 chốt đơn Mã 3.',
                ]);
        });

        $job2 = new AnalyzeCommentsJob($session->id);
        app()->call([$job2, 'handle']);

        // Verify memory was updated
        $session->refresh();
        $this->assertEquals('Tiếp tục bán áo thun, có thêm 1 chốt đơn Mã 3.', $session->ai_context_summary);

        // Verify comment was processed
        $this->assertDatabaseHas('live_events', [
            'id' => $comment2->id,
            'intent_tag' => 'Chốt đơn',
            'ai_processed' => true,
        ]);
    }
}
