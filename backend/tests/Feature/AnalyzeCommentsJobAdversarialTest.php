<?php

namespace Tests\Feature;

use App\Ai\Agents\CommentAnalyzer;
use App\Ai\Agents\LiveSessionAnalyzer;
use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\LiveStat;
use App\Models\User;
use Illuminate\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeCommentsJobAdversarialTest extends TestCase
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

    /**
     * Test unique lock lifecycle: acquire, fail duplicate, and release.
     */
    public function test_unique_lock_lifecycle(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Lock Test Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        $lockKey = 'analyze-comments-lock-'.$session->id;
        $lock = cache()->lock($lockKey, 120);

        // 1. First acquire should succeed
        $this->assertTrue((bool) $lock->get());

        // 2. Second acquire for the same unique ID should fail
        $lock2 = cache()->lock($lockKey, 120);
        $this->assertFalse((bool) $lock2->get());

        // 3. Release the lock
        $lock->release();

        // 4. Third acquire should succeed again
        $lock3 = cache()->lock($lockKey, 120);
        $this->assertTrue((bool) $lock3->get());

        // Cleanup
        $lock3->release();
    }

    /**
     * Test retryable exception handling: when the error contains lowercase 'rate limit',
     * the job rethrows without marking comments processed or releasing lock.
     */
    public function test_retryable_exception_does_not_mark_comments_processed(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Retryable Test Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 1'],
            'ai_processed' => false,
        ]);

        // Fake the agent to throw a retryable rate limit exception (lowercase 'rate limit')
        CommentAnalyzer::fake(function () {
            throw new \RuntimeException('rate limit exceeded.');
        });

        $job = new AnalyzeCommentsJob($session->id);

        $thrown = false;
        try {
            app()->call([$job, 'handle']);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rate limit', $e->getMessage());
            $thrown = true;
        }

        $this->assertTrue($thrown);

        // Assert the comment remains unprocessed (no poison pill applied because it's retryable)
        $comment->refresh();
        $this->assertFalse((bool) $comment->ai_processed);

        // Assert next job was NOT dispatched (no recursive loop on retryable failure)
        Queue::assertNotPushed(AnalyzeCommentsJob::class);
    }

    /**
     * Test case-insensitivity fix: throwing 'Rate limit' (capitalized) is recognized as retryable
     * and does NOT apply poison pill, so the comments remain unprocessed.
     */
    public function test_case_insensitivity_rate_limit_is_retryable(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Case Sensitivity Test',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 1'],
            'ai_processed' => false,
        ]);

        // Fake the agent to throw an exception containing capitalized 'Rate limit'
        CommentAnalyzer::fake(function () {
            throw new \RuntimeException('Rate limit exceeded.');
        });

        $job = new AnalyzeCommentsJob($session->id);

        $thrown = false;
        try {
            app()->call([$job, 'handle']);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rate limit', $e->getMessage());
            $thrown = true;
        }

        $this->assertTrue($thrown);

        // Assert the comment remains unprocessed (no poison pill applied because it's retryable)
        $comment->refresh();
        $this->assertFalse((bool) $comment->ai_processed);
    }

    public function test_unrecoverable_exception_triggers_poison_pill(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Unrecoverable Test Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        // Create 50 comments (fill first batch)
        $comments = [];
        for ($i = 0; $i < 50; $i++) {
            $comments[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now()->addSeconds($i),
                'tiktok_user_id' => 'user_'.$i,
                'data' => ['comment' => 'Bình luận '.$i],
                'ai_processed' => false,
            ]);
        }

        // Create the 51st comment (not in the first batch)
        $nextComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(100),
            'tiktok_user_id' => 'user_51',
            'data' => ['comment' => 'Bình luận tiếp'],
            'ai_processed' => false,
        ]);

        // Fake the agent to throw an unrecoverable exception
        CommentAnalyzer::fake(function () {
            throw new \RuntimeException('Invalid JSON syntax returned by LLM.');
        });

        $job = new AnalyzeCommentsJob($session->id);

        $lockKey = 'analyze-comments-lock-'.$session->id;
        $lock = cache()->lock($lockKey, 120);

        $a1 = $lock->get();
        $this->assertTrue($a1);
        $lock->release(); // Release so handle() can acquire it

        $thrown = false;
        try {
            app()->call([$job, 'handle']);
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);

        $a2 = $lock->get();
        $this->assertTrue($a2);

        // Assert first batch comments were marked processed & neutral (poison pill applied)
        $comments[0]->refresh();
        $this->assertTrue((bool) $comments[0]->ai_processed);
        $this->assertEquals('neutral', $comments[0]->sentiment);

        // Assert the 51st comment (which was not in the batch) is still unprocessed
        $nextComment->refresh();
        $this->assertFalse((bool) $nextComment->ai_processed);

        // Assert next job was dispatched to process subsequent comments
        Queue::assertPushed(AnalyzeCommentsJob::class, function ($queuedJob) use ($session) {
            return $queuedJob->uniqueId() === 'analyze-comments-'.$session->id;
        });

        // Cleanup
        $lock->release();
    }

    /**
     * Test batch processing limit of 50 comments.
     */
    public function test_batch_processing_limit_exactly_50(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Limit Test Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        // Create 60 unprocessed comments
        $comments = [];
        for ($i = 0; $i < 60; $i++) {
            $comments[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now()->addSeconds($i),
                'tiktok_user_id' => 'user_'.$i,
                'data' => ['comment' => 'Bình luận '.$i],
                'ai_processed' => false,
            ]);
        }

        // Fake the agent: assert exactly 50 comments are sent and return 50 results.
        // In a fake closure, the first argument is the prompt string itself.
        CommentAnalyzer::fake(function ($prompt) use ($comments) {
            $lines = explode("\n", trim($prompt));

            // Exactly 50 lines (comments) should be sent to the AI.
            \PHPUnit\Framework\Assert::assertCount(50, $lines);

            return [
                'results' => array_map(function ($i) use ($comments) {
                    return [
                        'id' => $comments[$i]->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Hỏi thông tin',
                        'question_tag' => 'Hỏi giá',
                        'product_tag' => null,
                        'has_phone' => false,
                    ];
                }, range(0, 49)),
                'session_note' => 'Processed first 50.',
            ];
        });
        $this->fakeInsights();

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Assert the first 50 comments are processed
        for ($i = 0; $i < 50; $i++) {
            $comments[$i]->refresh();
            $this->assertTrue((bool) $comments[$i]->ai_processed);
        }

        // Assert the remaining 10 comments are still unprocessed
        for ($i = 50; $i < 60; $i++) {
            $comments[$i]->refresh();
            $this->assertFalse((bool) $comments[$i]->ai_processed);
        }

        // Assert next job was dispatched
        Queue::assertPushed(AnalyzeCommentsJob::class);
    }

    /**
     * Test stats aggregation accuracy and atomic increments.
     */
    public function test_stats_aggregation_accuracy_atomic_increments(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Stats Increment Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        // Pre-create initial stats record
        $stats = LiveStat::create([
            'live_session_id' => $session->id,
            'sentiment_positive' => 5,
            'sentiment_neutral' => 10,
            'sentiment_negative' => 2,
            'leads_count' => 3,
        ]);

        // Pre-create an existing processed comment showing 'user_1' is already a lead
        LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->subMinutes(10),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mua hàng'],
            'ai_processed' => true,
            'intent_tag' => 'Chốt đơn',
        ]);

        // Create 3 new comments to be analyzed
        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1', // existing lead
            'data' => ['comment' => 'Mua thêm cái nữa'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_2', // new lead
            'data' => ['comment' => 'Chốt đơn'],
            'ai_processed' => false,
        ]);

        $comment3 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(2),
            'tiktok_user_id' => 'user_3', // not a lead (Hỏi thông tin)
            'data' => ['comment' => 'Giá bao nhiêu vậy'],
            'ai_processed' => false,
        ]);

        CommentAnalyzer::fake([
            [
                'results' => [
                    [
                        'id' => $comment1->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                    [
                        'id' => $comment2->id,
                        'sentiment' => 'negative',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                    [
                        'id' => $comment3->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => 'Hỏi thông tin',
                        'question_tag' => 'Hỏi giá',
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Stats increment test.',
            ],
        ]);
        $this->fakeInsights();

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Verify stats increments:
        // sentiment_positive: 5 + 1 = 6
        // sentiment_neutral: 10 + 1 = 11
        // sentiment_negative: 2 + 1 = 3
        // leads_count: 3 + 1 = 4 (only user_2 added, since user_1 already exists)
        $stats->refresh();
        $this->assertEquals(6, $stats->sentiment_positive);
        $this->assertEquals(11, $stats->sentiment_neutral);
        $this->assertEquals(3, $stats->sentiment_negative);
        $this->assertEquals(4, $stats->leads_count);
    }

    /**
     * Test the "Stats Aggregation and Transaction Out-Of-Sync" issue.
     * If updateAggregateStats fails inside the transaction, the entire transaction
     * rolls back and the comments remain unprocessed.
     */
    public function test_stats_out_of_sync_on_stats_update_failure(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Sync failure session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 1'],
            'ai_processed' => false,
        ]);

        CommentAnalyzer::fake([
            [
                'results' => [
                    [
                        'id' => $comment->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Sync failure test.',
            ],
        ]);

        // Register a saving event listener on LiveStat that throws an exception
        // This will cause updateAggregateStats to throw an exception
        LiveStat::saving(function ($stat) {
            throw new \RuntimeException('Simulated DB deadlock (connection timeout) during stats update.');
        });

        $job = new AnalyzeCommentsJob($session->id);

        $thrown = false;
        try {
            app()->call([$job, 'handle']);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated DB deadlock', $e->getMessage());
            $thrown = true;
        }

        $this->assertTrue($thrown);

        // 1. The comments transaction is rolled back, comments remain unprocessed
        $comment->refresh();
        $this->assertFalse((bool) $comment->ai_processed);

        // 2. The stats record is not created/updated
        $this->assertDatabaseMissing('live_stats', [
            'live_session_id' => $session->id,
        ]);
    }

    /**
     * Test that the lock is released exactly once and not double-released when dispatching the next job.
     */
    public function test_lock_released_exactly_once_on_dispatch_next(): void
    {
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Lock Release Test Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        // Create 60 comments to ensure there are remaining comments to trigger next dispatch
        $comments = [];
        for ($i = 0; $i < 60; $i++) {
            $comments[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now()->addSeconds($i),
                'tiktok_user_id' => 'user_'.$i,
                'data' => ['comment' => 'Comment '.$i],
                'ai_processed' => false,
            ]);
        }

        // Fake the agent to process the first 50 comments
        CommentAnalyzer::fake([
            [
                'results' => array_map(function ($i) use ($comments) {
                    return [
                        'id' => $comments[$i]->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => null,
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ];
                }, range(0, 49)),
                'session_note' => 'Lock release test batch.',
            ],
        ]);
        $this->fakeInsights();

        // Mock the Cache lock to verify it is released exactly once
        $mockLock = \Mockery::mock(Lock::class);
        $mockLock->shouldReceive('get')->once()->andReturn(true);
        $mockLock->shouldReceive('release')->once()->andReturn(true);

        Cache::shouldReceive('lock')
            ->with('analyze-comments-lock-'.$session->id, 120)
            ->andReturn($mockLock);
        Cache::shouldReceive('forget')->zeroOrMoreTimes();
        Cache::shouldReceive('get')->zeroOrMoreTimes()->andReturn(null);
        Cache::shouldReceive('put')->zeroOrMoreTimes();

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Assert next job was dispatched
        Queue::assertPushed(AnalyzeCommentsJob::class);
    }

    /**
     * Test that when two jobs run sequentially to process comments
     * from the same user, the leads count is incremented exactly once.
     */
    public function test_concurrent_stats_leads_count_race_condition(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Leads Count Concurrency Session',
            'status' => 'live',
            'tiktok_username' => 'tester',
        ]);

        // Pre-create stats record with 0 leads
        $stats = LiveStat::create([
            'live_session_id' => $session->id,
            'sentiment_positive' => 0,
            'sentiment_neutral' => 0,
            'sentiment_negative' => 0,
            'leads_count' => 0,
        ]);

        // Create 2 comments for the same user, both represent 'Chốt đơn'
        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_A',
            'data' => ['comment' => 'Chốt đơn 1'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_A',
            'data' => ['comment' => 'Chốt đơn 2'],
            'ai_processed' => false,
        ]);

        // Run Job 1 processing comment 1
        CommentAnalyzer::fake([
            [
                'results' => [
                    [
                        'id' => $comment1->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Job 1 summary.',
            ],
        ]);
        $this->fakeInsights();

        $job1 = new AnalyzeCommentsJob($session->id);
        app()->call([$job1, 'handle']);

        // Run Job 2 processing comment 2 — re-fake for the second job
        CommentAnalyzer::fake([
            [
                'results' => [
                    [
                        'id' => $comment2->id,
                        'sentiment' => 'positive',
                        'intent_tag' => 'Chốt đơn',
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ],
                ],
                'session_note' => 'Job 2 summary.',
            ],
        ]);

        $job2 = new AnalyzeCommentsJob($session->id);
        app()->call([$job2, 'handle']);

        // Refresh stats and assert leads_count is exactly 1 (since both are from user_A)
        $stats->refresh();
        $this->assertEquals(1, $stats->leads_count, 'Leads count should be exactly 1 despite multiple orders from user_A.');
    }
}
