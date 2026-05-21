# Handoff Report — AnalyzeCommentsJob Fix Proposal

This report analyzes the 7 High and Medium severity findings identified in the comment analysis pipeline and proposes a minimal, robust fix strategy for `backend/app/Jobs/AnalyzeCommentsJob.php` and its corresponding test suite `backend/tests/Feature/AnalyzeCommentsJobTest.php`.

---

## 1. Observation

Based on direct inspection of the codebase, the following locations and verbatim code blocks are associated with each of the 7 findings:

### Finding 1: High — Text-less Comments Pipeline Stall
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 81-85)
* **Verbatim Code**:
  ```php
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      return;
  }
  ```

### Finding 2: High — O(N^2) Performance Bottleneck in Stats Aggregation
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 465-487)
* **Verbatim Code**:
  ```php
  private function updateAggregateStats(LiveSession $session): void
  {
      $stats = LiveEvent::where('live_session_id', $session->id)
          ->where('event_type', 'comment')
          ->where('ai_processed', true)
          ->selectRaw("
              SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
              SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
              SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative,
              COUNT(DISTINCT CASE WHEN intent_tag = 'Chốt đơn' THEN tiktok_user_id END) as leads
          ")
          ->first();

      $session->stats()->updateOrCreate(
          ['live_session_id' => $session->id],
          [
              'sentiment_positive' => $stats->positive ?? 0,
              'sentiment_neutral' => $stats->neutral ?? 0,
              'sentiment_negative' => $stats->negative ?? 0,
              'leads_count' => $stats->leads ?? 0,
          ]
      );
  }
  ```

### Finding 3: Medium — N+1 Database Write Operations in Transaction Loop
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 203-212)
* **Verbatim Code**:
  ```php
  LiveEvent::where('id', $eventId)
      ->where('live_session_id', $this->liveSessionId)
      ->update([
          'sentiment' => $validated['sentiment'],
          'intent_tag' => $validated['intent_tag'],
          'question_tag' => $validated['question_tag'],
          'product_tag' => $validated['product_tag'],
          'has_phone' => $validated['has_phone'],
          'ai_processed' => true,
      ]);
  ```

### Finding 4: Medium — TypeError on TikTok Snapshot Failure
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 110-111)
* **Verbatim Code**:
  ```php
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot['audio_b64'] ?? null;
  ```

### Finding 5: Medium — Brittle Manual Cache Lock Deletion
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 241-250)
* **Verbatim Code**:
  ```php
  // Giải phóng unique lock của job hiện tại để Laravel cho phép dispatch job tiếp theo
  $lockKey = 'laravel_unique_job:' . self::class . ':' . $this->uniqueId();
  try {
      cache()->forget($lockKey);
  } catch (\Throwable $cacheEx) {
      Log::warning('Failed to clear unique job lock key', [
          'key' => $lockKey,
          'error' => $cacheEx->getMessage(),
      ]);
  }
  ```

### Finding 6: High — Unrecoverable Error Poison Pill Pipeline Stall
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 269-288)
* **Verbatim Code**:
  ```php
  $isLastAttempt = $this->attempts() >= $this->tries;
  $isUnrecoverable = !str_contains($e->getMessage(), 'rate limit') && 
                     !str_contains($e->getMessage(), 'timeout') && 
                     !str_contains($e->getMessage(), 'Connection');

  if ($isLastAttempt || $isUnrecoverable) {
      try {
          DB::table('live_events')
              ->whereIn('id', $unprocessed->pluck('id'))
              ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
          Log::warning('Marked batch comments as processed (neutral) due to unrecoverable AI error or max retries reached to prevent queue deadlock', [
              'session_id' => $this->liveSessionId,
              'comments_ids' => $unprocessed->pluck('id')->toArray(),
          ]);
      } catch (\Throwable $dbEx) {
          Log::error('Failed to mark poison pill comments as processed', [
              'error' => $dbEx->getMessage(),
          ]);
      }
  }
  ```

### Finding 7: Medium — Lock Expiry Race Condition / Duplicate Workers
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Line 30)
* **Verbatim Code**:
  ```php
  public int $uniqueFor = 30; // 30s lock
  ```

---

## 2. Logic Chain

1. **Finding 1 (Text-less batch stall)**: The early `return;` on line 84 bypasses the recursive check `hasMoreUnprocessed` and self-dispatch logic (lines 234-258). Therefore, if a batch of 50 comments contains only empty texts or emojis (filtered out), the job updates them to processed, exits, and halts the entire livestream analysis pipeline permanently.
2. **Finding 2 (O(N^2) Performance)**: Calling `updateAggregateStats` (lines 465-487) on every batch queries the entire historic sum of positive, negative, and neutral comments for that session. As comments grow to $N$, this results in an $O(N^2)$ reads loop, triggering severe database latency and lock contentions.
3. **Finding 3 (N+1 Writes)**: Processing 50 comments sequentially executes up to 50 individual `UPDATE` queries in a loop (lines 203-212) inside a single transaction. This increases database transaction duration, increasing connection pool usage and lock latency.
4. **Finding 4 (TypeError)**: `TikTokService::getSnapshot` returns `?array`. If the service fails and returns `null`, line 111 performs offset access on `null` (e.g. `$snapshot['audio_b64']`), immediately throwing a PHP `TypeError`.
5. **Finding 5 (Brittle cache key)**: Hardcoding `'laravel_unique_job:'` assumes a fixed lock format and doesn't respect custom configuration prefixes or custom cache driver store methods, which risks failing to release lock keys on custom cache configurations.
6. **Finding 6 (Poison pill stall)**: The catch block on lines 260-298 catches unrecoverable exceptions and updates comments to prevent deadlocks, but then executes `throw $e;`. This immediately terminates the job and bypasses the recursive self-dispatch check, halting processing for all subsequent comments in the livestream.
7. **Finding 7 (Lock expiry)**: The unique lock duration `uniqueFor` is set to 30 seconds, while the job timeout is set to 120 seconds. If the AI API takes more than 30 seconds to respond, the lock expires while the job is still active, allowing a newly dispatched duplicate job to run concurrently and process the same batch.

---

## 3. Caveats

* DB performance under huge loads (> 50,000 comments) has been modeled statically; physical load testing has not been run.
* The external Runware AI multi-modal API and TikTok audio captures are mocked inside the feature tests.
* Custom cache drivers with distinct TTL configurations are assumed to operate under standard Laravel queue behaviors.

---

## 4. Conclusion & Proposed Fixes

To resolve these findings safely and cleanly while respecting scope boundaries (not directly editing files), the following minimal fix strategies and concrete code snippets are proposed:

### Proposed Fix 1 & 5: Text-less Comments Stall & Dynamic Lock Release
* **Strategy**: When the batch is empty, check for remaining unprocessed comments, release the unique lock dynamically using Laravel's native `Illuminate\Bus\UniqueLock`, and dispatch the next batch.
* **Code Block to Replace (Lines 81-85)**:
  ```php
  // Before:
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      return;
  }

  // After:
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);

      $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
          ->where('event_type', 'comment')
          ->where('ai_processed', false)
          ->exists();

      if ($hasMoreUnprocessed) {
          try {
              app(\Illuminate\Bus\UniqueLock::class)->release($this);
          } catch (\Throwable $lockEx) {
              Log::warning('Failed to release unique job lock key on empty batch', [
                  'session_id' => $this->liveSessionId,
                  'error' => $lockEx->getMessage(),
              ]);
          }
          self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
      }
      return;
  }
  ```

### Proposed Fix 2: O(N^2) Stats Aggregation Bottleneck
* **Strategy**: Calculate the sentiment and lead changes *only* for the current batch (in memory) and update the `live_stats` row using atomic database increments, bypassing historic scans.
* **Code Block to Replace (Lines 465-487)**:
  ```php
  // Replace the entire private function updateAggregateStats with:
  private function updateAggregateStats(LiveSession $session, array $results, \Illuminate\Support\Collection $unprocessed, array $missingIds, array $productNames): void
  {
      $batchPositive = 0;
      $batchNeutral = count($missingIds);
      $batchNegative = 0;
      $batchLeadUserIds = [];

      // Collect counts from the AI results
      foreach ($results as $result) {
          $eventId = $result['id'] ?? null;
          if (!$eventId) {
              continue;
          }

          $validated = $this->validateResult($result, $productNames);
          if ($validated['sentiment'] === 'positive') {
              $batchPositive++;
          } elseif ($validated['sentiment'] === 'negative') {
              $batchNegative++;
          } else {
              $batchNeutral++;
          }

          if ($validated['intent_tag'] === 'Chốt đơn') {
              $event = $unprocessed->firstWhere('id', $eventId);
              if ($event && $event->tiktok_user_id) {
                  $batchLeadUserIds[] = $event->tiktok_user_id;
              }
          }
      }

      $batchLeadUserIds = array_unique($batchLeadUserIds);
      $newLeadsCount = 0;

      if (!empty($batchLeadUserIds)) {
          // Identify which user IDs were not already leads in the session
          $existingLeadUserIds = LiveEvent::where('live_session_id', $session->id)
              ->whereIn('tiktok_user_id', $batchLeadUserIds)
              ->where('intent_tag', 'Chốt đơn')
              ->whereNotIn('id', $unprocessed->pluck('id'))
              ->distinct()
              ->pluck('tiktok_user_id')
              ->toArray();

          $newLeadsCount = count(array_diff($batchLeadUserIds, $existingLeadUserIds));
      }

      // Ensure the stats row exists to prevent raw insert additions
      $stats = $session->stats()->firstOrCreate(
          ['live_session_id' => $session->id],
          [
              'sentiment_positive' => 0,
              'sentiment_neutral' => 0,
              'sentiment_negative' => 0,
              'leads_count' => 0,
          ]
      );

      // Perform dynamic atomic increment
      $stats->update([
          'sentiment_positive' => DB::raw("sentiment_positive + {$batchPositive}"),
          'sentiment_neutral' => DB::raw("sentiment_neutral + {$batchNeutral}"),
          'sentiment_negative' => DB::raw("sentiment_negative + {$batchNegative}"),
          'leads_count' => DB::raw("leads_count + {$newLeadsCount}"),
      ]);
  }
  ```
  *(Note: Update the caller at line 224 to pass the appropriate parameters: `$this->updateAggregateStats($session, $results, $unprocessed, $missingIds, $productNames);`)*

### Proposed Fix 3: N+1 Writes Loop Elimination
* **Strategy**: Collect batch updates in memory and perform a single bulk query using Laravel's `upsert` on the primary key `id`.
* **Code Block to Replace (Lines 190-221)**:
  ```php
  // Replace the DB::transaction block:
  DB::transaction(function () use ($results, $unprocessed, $productNames, &$missingIds) {
      $processedIds = [];
      $updateValues = [];

      foreach ($results as $result) {
          $eventId = $result['id'] ?? null;
          if (!$eventId) {
              continue;
          }

          $event = $unprocessed->firstWhere('id', $eventId);
          if (!$event) {
              continue;
          }

          $processedIds[] = $eventId;
          $validated = $this->validateResult($result, $productNames);

          $updateValues[] = [
              'id' => $event->id,
              'live_session_id' => $event->live_session_id,
              'event_type' => $event->event_type,
              'sentiment' => $validated['sentiment'],
              'intent_tag' => $validated['intent_tag'],
              'question_tag' => $validated['question_tag'],
              'product_tag' => $validated['product_tag'],
              'has_phone' => $validated['has_phone'] ? 1 : 0,
              'ai_processed' => 1,
          ];
      }

      if (!empty($updateValues)) {
          LiveEvent::upsert(
              $updateValues,
              ['id'],
              ['sentiment', 'intent_tag', 'question_tag', 'product_tag', 'has_phone', 'ai_processed']
          );
      }

      $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
      if (!empty($missingIds)) {
          LiveEvent::whereIn('id', $missingIds)
              ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      }
  });
  ```

### Proposed Fix 4: TikTok Snapshot Null Check
* **Strategy**: Coalesce the nullable return value of `getSnapshot`.
* **Code Block to Replace (Lines 110-111)**:
  ```php
  // Before:
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot['audio_b64'] ?? null;

  // After:
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot ? ($snapshot['audio_b64'] ?? null) : null;
  ```

### Proposed Fix 5 & 6: Clean Lock Deletion & Poison Pill Recovery
* **Strategy**: When an unrecoverable exception is caught, release the unique lock cleanly and recursively dispatch the next batch of comments (if any) before throwing the error.
* **Code Block to Replace (Lines 241-248 and 269-288)**:
  * Replace the main release lock block on lines 241-248:
    ```php
    // After:
    try {
        app(\Illuminate\Bus\UniqueLock::class)->release($this);
    } catch (\Throwable $lockEx) {
        Log::warning('Failed to clear unique job lock key', [
            'session_id' => $this->liveSessionId,
            'error' => $lockEx->getMessage(),
        ]);
    }
    ```
  * Replace the catch block lines 269-288:
    ```php
    // After:
    $isLastAttempt = $this->attempts() >= $this->tries;
    $isUnrecoverable = !str_contains($e->getMessage(), 'rate limit') && 
                       !str_contains($e->getMessage(), 'timeout') && 
                       !str_contains($e->getMessage(), 'Connection');

    if ($isLastAttempt || $isUnrecoverable) {
        try {
            DB::table('live_events')
                ->whereIn('id', $unprocessed->pluck('id'))
                ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
            Log::warning('Marked batch comments as processed (neutral) due to unrecoverable AI error or max retries reached to prevent queue deadlock', [
                'session_id' => $this->liveSessionId,
                'comments_ids' => $unprocessed->pluck('id')->toArray(),
            ]);

            // Prevent pipeline stall: dispatch next batch of comments (if any)
            $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                ->where('event_type', 'comment')
                ->where('ai_processed', false)
                ->exists();

            if ($hasMoreUnprocessed) {
                try {
                    app(\Illuminate\Bus\UniqueLock::class)->release($this);
                } catch (\Throwable $lockEx) {
                    Log::warning('Failed to release unique job lock key on poison pill recovery', [
                        'session_id' => $this->liveSessionId,
                        'error' => $lockEx->getMessage(),
                    ]);
                }
                self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
                Log::info('Dispatched next AnalyzeCommentsJob to process remaining comments after poison pill recovery', [
                    'session_id' => $this->liveSessionId,
                ]);
            }
        } catch (\Throwable $dbEx) {
            Log::error('Failed to mark poison pill comments as processed', [
                'error' => $dbEx->getMessage(),
            ]);
        }
    }
    ```

### Proposed Fix 7: Lock Expiry Range Increase
* **Strategy**: Increase the lock duration to match the maximum timeout duration of the job.
* **Code Block to Replace (Line 30)**:
  ```php
  // Before:
  public int $uniqueFor = 30; // 30s lock

  // After:
  public int $uniqueFor = 120; // 120s lock
  ```

---

## 5. New Test Cases in `AnalyzeCommentsJobTest.php`

To cover the text-less comment batch stall, stats validation, and AI response exception handling gaps, append the following three test cases to the class:

```php
    /**
     * Test Finding 1: Ensure that a batch containing only text-less/empty comments
     * does not stall the comment processing pipeline.
     */
    public function test_empty_comment_batch_does_not_stall_pipeline(): void
    {
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Stall Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create 50 comments with empty text (filtered out)
        for ($i = 0; $i < 50; $i++) {
            LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now(),
                'tiktok_user_id' => "user_empty_{$i}",
                'data' => ['comment' => ''],
                'ai_processed' => false,
            ]);
        }

        // Create 2 valid comments that should be processed in the next batch
        $validComment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(1),
            'tiktok_user_id' => 'user_valid_1',
            'data' => ['comment' => 'Chốt đơn'],
            'ai_processed' => false,
        ]);

        $validComment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(2),
            'tiktok_user_id' => 'user_valid_2',
            'data' => ['comment' => 'giá bao nhiêu'],
            'ai_processed' => false,
        ]);

        // Mock RunwareAiService to only receive the 2 valid comments
        $this->mock(RunwareAiService::class, function ($mock) use ($validComment1, $validComment2) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->withArgs(function ($systemPrompt, $parts) use ($validComment1, $validComment2) {
                    $userMessage = $parts[0]['text'] ?? '';
                    return str_contains($userMessage, "{$validComment1->id}|Chốt đơn")
                        && str_contains($userMessage, "{$validComment2->id}|giá bao nhiêu");
                })
                ->andReturn([
                    'results' => [
                        [
                            'id' => $validComment1->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $validComment2->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Hỏi thông tin',
                            'question_tag' => 'Hỏi giá',
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Đã xử lý bình luận hợp lệ.',
                ]);
        });

        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')->andReturn([
                'audio_b64' => null,
            ]);
        });

        // Run the job (which executes synchronously with 'sync' queue connection)
        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Assert all 52 comments are marked as processed
        $this->assertEquals(52, LiveEvent::where('live_session_id', $session->id)->where('ai_processed', true)->count());

        // Assert valid comments have correct metadata
        $this->assertDatabaseHas('live_events', [
            'id' => $validComment1->id,
            'intent_tag' => 'Chốt đơn',
            'ai_processed' => true,
        ]);
        $this->assertDatabaseHas('live_events', [
            'id' => $validComment2->id,
            'intent_tag' => 'Hỏi thông tin',
            'question_tag' => 'Hỏi giá',
            'ai_processed' => true,
        ]);
    }

    /**
     * Test Finding 2: Validate aggregate stats are incremented correctly and 
     * duplicate lead commenters are deduplicated properly.
     */
    public function test_aggregate_stats_are_updated_correctly(): void
    {
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Stats Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_a',
            'data' => ['comment' => 'Chốt đơn sản phẩm'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSecond(),
            'tiktok_user_id' => 'user_a', // Same user as comment1
            'data' => ['comment' => 'Chốt đơn thêm cái nữa'],
            'ai_processed' => false,
        ]);

        $comment3 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(2),
            'tiktok_user_id' => 'user_b',
            'data' => ['comment' => 'sản phẩm tệ quá'],
            'ai_processed' => false,
        ]);

        $comment4 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(3),
            'tiktok_user_id' => 'user_c',
            'data' => ['comment' => 'tuyệt vời'],
            'ai_processed' => false,
        ]);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment1, $comment2, $comment3, $comment4) {
            $mock->shouldReceive('chatMultimodal')
                ->once()
                ->andReturn([
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
                            'sentiment' => 'positive',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $comment3->id,
                            'sentiment' => 'negative',
                            'intent_tag' => 'Phản hồi SP',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $comment4->id,
                            'sentiment' => 'positive',
                            'intent_tag' => 'Phản hồi SP',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                    'session_note' => 'Stats updated.',
                ]);
        });

        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')->andReturn([
                'audio_b64' => null,
            ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // Refresh stats relation
        $session->load('stats');
        $this->assertNotNull($session->stats);
        $this->assertEquals(3, $session->stats->sentiment_positive);
        $this->assertEquals(0, $session->stats->sentiment_neutral);
        $this->assertEquals(1, $session->stats->sentiment_negative);
        $this->assertEquals(1, $session->stats->leads_count); // 2 orders from 1 user = 1 lead
    }

    /**
     * Test Finding 6: Ensure that an unrecoverable AI response format exception
     * marks poison pill comments as neutral but dispatches the next batch correctly.
     */
    public function test_unrecoverable_ai_exception_does_not_stall_pipeline(): void
    {
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Exception Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Create 50 comments for the first batch
        $firstBatch = [];
        for ($i = 0; $i < 50; $i++) {
            $firstBatch[] = LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'comment',
                'event_at' => now(),
                'tiktok_user_id' => "user_batch1_{$i}",
                'data' => ['comment' => "comment {$i}"],
                'ai_processed' => false,
            ]);
        }
        $firstBatchIds = collect($firstBatch)->pluck('id')->toArray();

        // Create 1 comment for the second batch
        $secondBatchComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(10),
            'tiktok_user_id' => 'user_batch2_1',
            'data' => ['comment' => 'Chốt đơn hàng'],
            'ai_processed' => false,
        ]);

        // Mock RunwareAiService: throws exception on 1st call, succeeds on 2nd
        $this->mock(RunwareAiService::class, function ($mock) use ($secondBatchComment) {
            $mock->shouldReceive('chatMultimodal')
                ->twice()
                ->andReturnUsing(function ($systemPrompt, $parts) use ($secondBatchComment) {
                    static $attempts = 0;
                    $attempts++;

                    if ($attempts === 1) {
                        throw new \RuntimeException('AI Response format is invalid: expected list of results');
                    }

                    return [
                        'results' => [
                            [
                                'id' => $secondBatchComment->id,
                                'sentiment' => 'positive',
                                'intent_tag' => 'Chốt đơn',
                                'question_tag' => null,
                                'product_tag' => null,
                                'has_phone' => false,
                            ],
                        ],
                        'session_note' => 'Đã xử lý batch 2 sau lỗi batch 1.',
                    ];
                });
        });

        $this->mock(TikTokService::class, function ($mock) {
            $mock->shouldReceive('getSnapshot')->andReturn([
                'audio_b64' => null,
            ]);
        });

        $job = new AnalyzeCommentsJob($session->id);
        
        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('AI Response format is invalid: expected list of results', $e->getMessage());
        }

        // Assert first batch comments were marked neutral to prevent deadlock
        $neutralCount = LiveEvent::whereIn('id', $firstBatchIds)
            ->where('ai_processed', true)
            ->where('sentiment', 'neutral')
            ->count();
        $this->assertEquals(50, $neutralCount);

        // Assert second batch comment was successfully processed via recursive dispatch
        $this->assertDatabaseHas('live_events', [
            'id' => $secondBatchComment->id,
            'ai_processed' => true,
            'intent_tag' => 'Chốt đơn',
            'sentiment' => 'positive',
        ]);
    }
```

---

## 6. Verification Method

* Run the PHPUnit tests in the backend workspace:
  ```powershell
  cd d:\Workspace\livestream\backend
  php artisan test --filter=AnalyzeCommentsJobTest
  ```
* Ensure all tests pass, verifying both the fallback logic and new edge cases (empty batch, stats increments, and AI format exception handling).
* To manually check performance, confirm that the SQL statement profile does not contain `SUM(CASE ...)` query scans across the entire historic `live_events` table when processing comment batches.
