<?php

namespace Tests\Feature;

use App\Ai\Agents\CommentAnalyzer;
use App\Ai\Agents\LiveSessionAnalyzer;
use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeCommentsJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake the LiveSessionAnalyzer so the job's auto-insights step never hits the network.
     */
    private function fakeInsights(): void
    {
        LiveSessionAnalyzer::fake([
            ['summary' => 'Auto insight summary.', 'alerts' => []],
        ]);
    }

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

        // 2. Fake the CommentAnalyzer agent (Laravel AI SDK)
        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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

        CommentAnalyzer::assertPrompted(fn ($prompt) => $prompt->contains((string) $comment1->id));
    }

    public function test_system_prompts_contain_key_instructions(): void
    {
        // CommentAnalyzer agent instructions must carry the core classification rules.
        $analyzer = (new CommentAnalyzer)
            ->withProducts([])
            ->withKeywords([]);
        $instructions = $analyzer->instructions();

        $this->assertStringContainsString('Chốt đơn', $instructions);
        $this->assertStringContainsString('cú pháp đặt hàng', $instructions);
        $this->assertStringContainsString('session_note', $instructions);
        $this->assertStringContainsString('extracted_keywords', $instructions);

        // Memory context must be embedded into the instructions when provided.
        $withMemory = (new CommentAnalyzer)
            ->withMemory('Đang bán áo thun, nhiều người hỏi size M.')
            ->instructions();

        $this->assertStringContainsString('SESSION MEMORY', $withMemory);
        $this->assertStringContainsString('Đang bán áo thun, nhiều người hỏi size M.', $withMemory);
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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

        $job1 = new AnalyzeCommentsJob($session->id);
        app()->call([$job1, 'handle']);

        // Verify memory was saved
        $session->refresh();
        $this->assertEquals('Đang bán áo thun, nhiều người hỏi size M.', $session->ai_context_summary);

        // --- Batch 2: AI receives memory from batch 1 (embedded in instructions) ---
        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(10),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Mã 3'],
            'ai_processed' => false,
        ]);

        CommentAnalyzer::fake([
            [
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
            ],
        ]);

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        $session->refresh();
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

        // The agent must never be prompted for an all-empty batch.
        CommentAnalyzer::fake();

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

        CommentAnalyzer::assertNeverPrompted();

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);

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

        // Fake the agent to throw an unrecoverable exception
        CommentAnalyzer::fake(function () {
            throw new \RuntimeException('AI Response has invalid structure');
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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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

        CommentAnalyzer::fake([
            [
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
            ],
        ]);
        $this->fakeInsights();

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
