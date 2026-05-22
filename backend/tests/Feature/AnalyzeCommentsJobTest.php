<?php

namespace Tests\Feature;

use App\Ai\Agents\CommentAnalyzer;
use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeCommentsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_analyzes_comments_and_saves_ai_tags(): void
    {
        // 1. Setup session and comments
        $user = User::factory()->create();

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
        $analyzer = new CommentAnalyzer;
        $instructions = $analyzer->instructions();

        $this->assertStringContainsString('Chốt đơn', $instructions);
        $this->assertStringContainsString('cú pháp đặt hàng', $instructions);

        // 2. Check AnalyzeCommentsJob system prompt includes session_note + audio section
        $user = User::factory()->create();
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
                        ],
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
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Premium',
            'price' => 299000,
            'duration_days' => 30,
            'features' => [
                'audio_analysis' => true,
                'limit_streams' => -1,
                'max_duration_hours' => 24,
                'ai_credits' => -1,
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

                    return ! $hasAudioSection && $hasOnlyTextPart;
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
                        ],
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
        $user = User::factory()->create();

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

    public function test_audio_present_adds_audio_section_and_part(): void
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::create([
            'name' => 'Premium',
            'price' => 299000,
            'duration_days' => 30,
            'features' => [
                'audio_analysis' => true,
                'limit_streams' => -1,
                'max_duration_hours' => 24,
                'ai_credits' => -1,
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
            'name' => 'Audio Present Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
            'tiktok_session_id' => 'real-session-id',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'cái này bao nhiêu'],
            'ai_processed' => false,
        ]);

        // Mock TikTokService returns actual audio
        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')
                ->once()
                ->andReturn([
                    'audio_b64' => base64_encode('fake-audio-data'),
                    'image_b64' => null,
                    'title' => 'Test Live',
                ]);
        });

        $this->mock(RunwareAiService::class, function ($mock) use ($comment) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->withArgs(function ($systemPrompt, $parts) {
                    // Prompt MUST have audio section
                    $hasAudioSection = str_contains($systemPrompt, 'AUDIO LIVESTREAM');
                    // Parts MUST have 2 elements: text + audio
                    $hasTwoParts = count($parts) === 2;
                    $hasAudioPart = $parts[1]['type'] === 'input_audio';

                    return $hasAudioSection && $hasTwoParts && $hasAudioPart;
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
                        ],
                    ],
                    'session_note' => 'Streamer đang giới thiệu sản phẩm.',
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment->id,
            'ai_processed' => true,
            'intent_tag' => 'Hỏi thông tin',
        ]);
    }

    public function test_session_note_is_truncated_to_500_chars(): void
    {
        $user = User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Truncation Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test'],
            'ai_processed' => false,
        ]);

        $longNote = str_repeat('A', 1000); // 1000 chars

        $this->mock(TikTokService::class);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment, $longNote) {
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
                    'session_note' => $longNote,
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
        $this->assertEquals(500, mb_strlen($session->ai_context_summary));
    }

    public function test_non_string_session_note_is_skipped(): void
    {
        $user = User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Non-string Note Test',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'hello'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);

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
                    // AI hallucinate: session_note is array instead of string
                    'session_note' => ['key' => 'value'],
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $this->assertNull($session->ai_context_summary);
    }

    public function test_text_less_comment_batch_does_not_stall_pipeline(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Text-less Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create 50 empty comments to fill the first batch
        $emptyComments = [];
        for ($i = 0; $i < 50; $i++) {
            $emptyComments[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now()->subMinutes(60)->addSeconds($i),
                'tiktok_user_id' => 'user_1',
                'data' => ['comment' => ''], // empty text (e.g. system join/emojis filtered out)
                'ai_processed' => false,
            ]);
        }

        // Batch 2: valid comment (will remain unprocessed)
        $validComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Mã 2'],
            'ai_processed' => false,
        ]);

        // Mock services
        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) {
            $mock->shouldNotReceive('chatMultimodal');
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Assert the empty comments were marked processed (neutral)
        foreach ($emptyComments as $emptyComment) {
            $emptyComment->refresh();
            $this->assertTrue((bool) $emptyComment->ai_processed);
            $this->assertEquals('neutral', $emptyComment->sentiment);
        }

        // Assert the valid comment is still unprocessed
        $validComment->refresh();
        $this->assertFalse((bool) $validComment->ai_processed);

        // Assert next job was dispatched to continue pipeline
        Queue::assertPushed(AnalyzeCommentsJob::class, function ($job) use ($session) {
            return $job->uniqueId() === 'analyze-comments-'.$session->id;
        });
    }

    public function test_stats_are_incremented_and_leads_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Stats Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create comments for first batch
        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Sản phẩm tốt quá'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_1', // same user
            'data' => ['comment' => 'Chốt đơn mã A'],
            'ai_processed' => false,
        ]);

        $comment3 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(2),
            'tiktok_user_id' => 'user_2', // different user
            'data' => ['comment' => 'Chốt đơn mã A'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) use ($comment1, $comment2, $comment3) {
            $mock->shouldReceive('chatMultimodal')->andReturn([
                'results' => [
                    [
                        'id' => $comment1->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Phản hồi SP',
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
                    [
                        'id' => $comment3->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Stats batch summary.',
            ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Verify stats are calculated correctly
        $session->refresh();
        $stats = $session->stats;
        $this->assertNotNull($stats);
        $this->assertEquals(1, $stats->sentiment_positive);
        $this->assertEquals(2, $stats->sentiment_neutral);
        $this->assertEquals(0, $stats->sentiment_negative);
        $this->assertEquals(2, $stats->leads_count); // user_1 and user_2 are leads

        // Create comments for second batch
        $comment4 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(10),
            'tiktok_user_id' => 'user_1', // user_1 orders again (should not increment lead count)
            'data' => ['comment' => 'Chốt đơn nữa'],
            'ai_processed' => false,
        ]);

        $comment5 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(11),
            'tiktok_user_id' => 'user_3', // user_3 is a new lead (should increment lead count)
            'data' => ['comment' => 'Chốt đơn mã B'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) use ($comment4, $comment5) {
            $mock->shouldReceive('chatMultimodal')->andReturn([
                'results' => [
                    [
                        'id' => $comment4->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                    [
                        'id' => $comment5->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Second batch summary.',
            ]);
        });

        $job2 = new AnalyzeCommentsJob($session->id);
        app()->call([$job2, 'handle']);

        $session->refresh();
        $stats = $session->stats;
        $this->assertEquals(1, $stats->sentiment_positive);
        $this->assertEquals(4, $stats->sentiment_neutral);
        $this->assertEquals(3, $stats->leads_count); // 3 unique leads (user_1, user_2, user_3)
    }

    public function test_ai_response_exception_does_not_stall_pipeline(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Exception Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create 50 comments for Batch 1 (will fail)
        $failedComments = [];
        for ($i = 0; $i < 50; $i++) {
            $failedComments[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now()->subMinutes(60)->addSeconds($i),
                'tiktok_user_id' => 'user_1',
                'data' => ['comment' => 'Hỏng rồi '.$i],
                'ai_processed' => false,
            ]);
        }

        // Batch 2 comment (should be queued and remain unprocessed)
        $nextComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Bình thường'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andThrow(new \RuntimeException('AI Response has invalid structure'));
        });

        $job = new AnalyzeCommentsJob($session->id);

        $thrown = false;
        try {
            app()->call([$job, 'handle']);
        } catch (\RuntimeException $e) {
            $this->assertEquals('AI Response has invalid structure', $e->getMessage());
            $thrown = true;
        }

        $this->assertTrue($thrown, 'RuntimeException should be rethrown');

        // Assert failed comments are marked processed & neutral
        foreach ($failedComments as $failedComment) {
            $failedComment->refresh();
            $this->assertTrue((bool) $failedComment->ai_processed);
            $this->assertEquals('neutral', $failedComment->sentiment);
        }

        // Assert next comment is still unprocessed
        $nextComment->refresh();
        $this->assertFalse((bool) $nextComment->ai_processed);

        // Assert next job was dispatched to process subsequent comments
        Queue::assertPushed(AnalyzeCommentsJob::class, function ($job) use ($session) {
            return $job->uniqueId() === 'analyze-comments-'.$session->id;
        });
    }

    public function test_it_extracts_and_persists_keywords_from_scratch(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Keywords Test Scratch',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
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
                    'session_note' => 'Notes',
                    'extracted_keywords' => ['áo thun', 'giá rẻ', 'size l'],
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $this->assertEquals(3, $session->keywords()->count());
        $this->assertEquals(['áo thun', 'giá rẻ', 'size l'], $session->keywords()->pluck('keyword')->toArray());
    }

    public function test_it_extracts_and_persists_keywords_with_30_limit(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Keywords Test Limit',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test'],
            'ai_processed' => false,
        ]);

        // Pre-populate 28 keywords to check the 30-limit
        for ($i = 1; $i <= 28; $i++) {
            $session->keywords()->create(['keyword' => "existing{$i}"]);
        }

        $this->mock(TikTokService::class);
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
                    'session_note' => 'Notes',
                    'extracted_keywords' => [
                        'EXISTING1', // existing, should be ignored (case insensitive)
                        '  new1  ',   // new, should be trimmed and lowercased
                        'new2',       // new, should be lowercased
                        'new3',       // new, but should exceed limit (since 28 + 2 = 30)
                        '',           // empty, should be ignored
                        'new2',       // duplicate in the same batch, should be ignored
                    ],
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Verify the count is exactly 30
        $this->assertEquals(30, $session->keywords()->count());

        // Verify the specific keywords in database
        $this->assertTrue($session->keywords()->where('keyword', 'new1')->exists());
        $this->assertTrue($session->keywords()->where('keyword', 'new2')->exists());
        $this->assertFalse($session->keywords()->where('keyword', 'new3')->exists());
    }

    public function test_it_analyzes_customer_inquiries_as_neutral_and_info_intent(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Inquiry Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'ban đầu e bôi hơi rát k sao dk ạ'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'sao e vào giỏ hàng k có ạ'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) use ($comment1, $comment2) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment1->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Hỏi thông tin',
                            'question_tag' => 'Hỏi công dụng',
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $comment2->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Hỏi thông tin',
                            'question_tag' => 'Hỏi tồn kho',
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Khách hỏi thăm sản phẩm và tồn kho giỏ hàng.',
                ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment1->id,
            'sentiment' => 'neutral',
            'intent_tag' => 'Hỏi thông tin',
            'question_tag' => 'Hỏi công dụng',
            'ai_processed' => true,
        ]);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment2->id,
            'sentiment' => 'neutral',
            'intent_tag' => 'Hỏi thông tin',
            'question_tag' => 'Hỏi tồn kho',
            'ai_processed' => true,
        ]);
    }
}
