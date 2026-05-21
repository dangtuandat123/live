# Adversarial Tests Found

These are the exact implementations of the two adversarial tests reported by Challenger 2, extracted directly from the system logs of previous subagent runs:

## 1. `test_concurrent_stats_leads_count_race_condition`

This test verifies the race condition where concurrent/interleaved statistics updates for comments of the same user result in a lead update being ignored or lost.

```php
    /**
     * Test the race condition where concurrent/interleaved stats updates
     * result in a user order never being counted as a lead.
     */
    public function test_concurrent_stats_leads_count_race_condition(): void
    {
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Concurrent Leads Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $stats = $session->stats()->create([
            'sentiment_positive' => 0,
            'sentiment_neutral' => 0,
            'sentiment_negative' => 0,
            'leads_count' => 0,
        ]);

        // Create two comments from the same user 'user_A'
        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_A',
            'data' => ['comment' => 'Chốt đơn mã 1'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_A',
            'data' => ['comment' => 'Chốt đơn mã 2'],
            'ai_processed' => false,
        ]);

        // 1. Job 1 processes its batch (comment 1) and commits transaction
        DB::transaction(function () use ($comment1) {
            $comment1->update([
                'ai_processed' => true,
                'sentiment' => 'neutral',
                'intent_tag' => 'Chốt đơn',
            ]);
        });

        // 2. Job 2 processes its batch (comment 2) and commits transaction
        DB::transaction(function () use ($comment2) {
            $comment2->update([
                'ai_processed' => true,
                'sentiment' => 'neutral',
                'intent_tag' => 'Chốt đơn',
            ]);
        });

        // 3. Now both events are committed as processed.
        // We call updateAggregateStats on both.
        // Job 1 updates stats with its batch: positive/neutral/negative and chot_don_users
        $job1 = new AnalyzeCommentsJob($session->id);
        $batchStats1 = [
            'positive' => 0,
            'neutral' => 1,
            'negative' => 0,
            'chot_don_users' => ['user_A'],
        ];
        
        // Job 1 update
        $reflector1 = new \ReflectionMethod(AnalyzeCommentsJob::class, 'updateAggregateStats');
        $reflector1->setAccessible(true);
        $reflector1->invoke($job1, $session, $batchStats1, [$comment1->id]);

        // Job 2 update
        $job2 = new AnalyzeCommentsJob($session->id);
        $batchStats2 = [
            'positive' => 0,
            'neutral' => 1,
            'negative' => 0,
            'chot_don_users' => ['user_A'],
        ];
        $reflector2 = new \ReflectionMethod(AnalyzeCommentsJob::class, 'updateAggregateStats');
        $reflector2->setAccessible(true);
        $reflector2->invoke($job2, $session, $batchStats2, [$comment2->id]);

        $session->refresh();
        $stats->refresh();

        // Expectation: user_A has ordered, so leads_count should be at least 1.
        // If the race condition occurs, leads_count remains 0!
        $this->assertEquals(1, $stats->leads_count, "Leads count was not incremented due to concurrency race condition!");
    }
```

---

## 2. `test_unique_lock_release_race_condition`

This test simulates the "Double Lock Release Race Condition" where Job A's completion releases the unique lock for Job B (with the same uniqueId) in the queue, rendering Job B completely unprotected.

```php
    /**
     * Test the race condition where manually releasing the lock during execution
     * causes the unique lock to be completely cleared after the first job finishes,
     * allowing duplicate jobs to be queued while a second job is already in queue.
     */
    public function test_unique_lock_release_race_condition(): void
    {
        // We fake the queue so we can inspect pushed jobs
        Queue::fake([AnalyzeCommentsJob::class]);

        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Lock Race Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create a comment to process
        $c1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'test'],
            'ai_processed' => false,
        ]);

        // Create another comment that will be unprocessed (this will trigger self-dispatch)
        $c2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(5),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'next comment'],
            'ai_processed' => false,
        ]);

        $this->mock(TikTokService::class);
        $this->mock(RunwareAiService::class, function ($mock) use ($c1) {
            $mock->shouldReceive('chatMultimodal')->andReturn([
                'results' => [
                    [
                        'id' => $c1->id,
                        'sentiment' => 'neutral',
                        'intent_tag' => null,
                        'question_tag' => null,
                        'product_tag' => null,
                        'has_phone' => false,
                    ]
                ],
                'session_note' => 'Lock race summary.',
            ]);
        });

        // Resolve the unique lock key that would be used
        $job = new AnalyzeCommentsJob($session->id);
        $lockKey = 'laravel_unique_job:App\Jobs\AnalyzeCommentsJob:' . $job->uniqueId();

        // 1. Acquire lock manually as if Job A is dispatched and starting
        $lock = Cache::lock($lockKey, 120);
        $this->assertTrue($lock->get(), "Should be able to acquire lock initially");

        // 2. Run Job A's handle method
        app()->call([$job, 'handle']);

        // Since Job A has completed handle, the Laravel queue worker (which we simulate via middleware)
        // would release the lock.
        $uniqueLock = resolve(\Illuminate\Bus\UniqueLock::class);
        $uniqueLock->release($job);

        // Check if the lock key is still present.
        // Since Job B is queued, the lock key SHOULD be present in the cache so that another dispatch is blocked.
        $isLockHeld = !Cache::lock($lockKey, 120)->get();
        if ($isLockHeld) {
            Cache::lock($lockKey, 120)->forceRelease();
        }

        $this->assertTrue($isLockHeld, "Unique lock key was completely deleted upon Job A's termination, leaving Job B unprotected!");
    }
```
