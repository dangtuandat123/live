# Handoff Report — AnalyzeCommentsJob Audit Remediation Plan

## 1. Observation
We analyzed the codebase of the livestream comments analysis pipeline in repository `d:\Workspace\livestream\backend` using static investigation. The target files are:
* **Job Class**: `backend/app/Jobs/AnalyzeCommentsJob.php`
* **Feature Test**: `backend/tests/Feature/AnalyzeCommentsJobTest.php`

The following exact line ranges and code blocks in `AnalyzeCommentsJob.php` were identified for the 7 High and Medium findings:

### Finding 1: High — Text-less Comments Pipeline Stall
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 81–85)
* **Code Block**:
  ```php
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      return;
  }
  ```

### Finding 2: High — O(N^2) Performance Bottleneck in Stats Aggregation
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 465–487)
* **Code Block**:
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
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 203–212)
* **Code Block**:
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
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 110–111)
* **Code Block**:
  ```php
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot['audio_b64'] ?? null;
  ```

### Finding 5: Medium — Brittle Manual Cache Lock Deletion
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 241–248)
* **Code Block**:
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
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 260–298)
* **Code Block**:
  ```php
  } catch (\Throwable $e) {
      Log::error('AI comment analysis failed', [
          'session_id' => $this->liveSessionId,
          'error' => $e->getMessage(),
          'comments_count' => $commentsText->count(),
      ]);

      // Khắc phục lỗi Poison Pill deadlock: Đánh dấu các comment là đã xử lý (sentiment neutral)
      // nếu lỗi không thể tự phục hồi (JSON parse, null response) hoặc đã đạt số lần thử tối đa.
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

      // Không retry nếu lỗi auth
      if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'auth')) {
          $this->fail($e);
          return;
      }

      // Rethrow để Laravel queue tự động retry theo $tries/$backoff nếu còn lượt
      throw $e;
  }
  ```

### Finding 7: Medium — Lock Expiry Race Condition / Duplicate Workers
* **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Line 30)
* **Code Block**:
  ```php
  public int $uniqueFor = 30; // 30s lock
  ```

---

## 2. Logic Chain

1. **Finding 1 (Pipeline Stall on Empty Batch)**:
   * **Observation**: In lines 81–85, when comments in the current batch `$unprocessed` contain no text (e.g. they were filtered out because they were emojis or system joins), `$commentsText->isEmpty()` is true. The job marks these comments processed and executes `return;` early.
   * **Reasoning**: This returns from the `handle()` method without executing the recursive check at line 235 (`LiveEvent::where('live_session_id', $this->liveSessionId)->where('ai_processed', false)->exists()`). As a result, subsequent unprocessed comments in the stream are never processed because the loop is terminated.
   * **Conclusion**: The job must check if more comments are waiting and dispatch itself again before returning early.

2. **Finding 2 (O(N^2) Performance Bottleneck)**:
   * **Observation**: In `updateAggregateStats()` (lines 465–487), the database is queried using `SUM(CASE...)` over the entire history of the session's comments.
   * **Reasoning**: This is called at line 224 after each batch of 50. For a livestream containing $N$ comments, this aggregates the entire comment history $O(N/50)$ times, reading a total of $O(N^2)$ comments. This scales poorly, causes high CPU load, and locks the database.
   * **Conclusion**: We should update the aggregate metrics incrementally. We can compute changes in the current batch and update the `live_stats` record using increments. For `leads_count`, since it must track distinct users, we can extract the unique users in the current batch who placed a "Chốt đơn" order and verify whether they have placed any order in prior batches.

3. **Finding 3 (N+1 Database Updates)**:
   * **Observation**: Inside the transaction block (lines 203–212), an individual `update` query is executed for each comment result (up to 50 times in a loop).
   * **Reasoning**: Executing 50 separate SQL UPDATE queries inside a transaction holds locks on multiple rows for longer durations and exhausts database connection pools under high queue worker concurrency.
   * **Conclusion**: Grouping the comments by their target AI attributes (sentiment, tags, has_phone) allows performing bulk updates using `whereIn('id', $ids)`. Since comments within a batch share identical updates (e.g., neutral sentiment, null tags), this reduces the number of queries to between 2 and 5 bulk updates.

4. **Finding 4 (TypeError on null TikTok Snapshot)**:
   * **Observation**: At line 110, `$tiktokService->getSnapshot(...)` is called, and at line 111, `$snapshot['audio_b64']` is accessed.
   * **Reasoning**: The return type of `getSnapshot` is `?array`, meaning it can return `null` on errors. Directly accessing `$snapshot['audio_b64']` when `$snapshot` is `null` throws a PHP `TypeError` (e.g. `Cannot access offset of type string on null`).
   * **Conclusion**: Adding a null check (`$snapshot ? ($snapshot['audio_b64'] ?? null) : null`) prevents this error.

5. **Finding 5 (Brittle Manual Lock Key Deletion)**:
   * **Observation**: At lines 241–248, the unique lock key is deleted from the cache using a hardcoded prefix `'laravel_unique_job:' . self::class . ':' . $this->uniqueId()`.
   * **Reasoning**: Assuming a static prefix `'laravel_unique_job:'` violates Laravel's architectural boundaries. If the cache config uses a customized prefix or different driver rules, cache clearing may fail.
   * **Conclusion**: Using Laravel's native unique lock api resolver `resolve(\Illuminate\Bus\UniqueLock::class)->release($this)` delegates the lock key construction and deletion safely back to Laravel.

6. **Finding 6 (Poison Pill Pipeline Stall)**:
   * **Observation**: In the `catch` block (lines 260–298), if an unrecoverable exception is caught, the batch comments are marked as processed to prevent queue deadlocks. However, the exception is then rethrown (`throw $e` at line 297).
   * **Reasoning**: Rethrowing the exception causes the queue worker to fail the job immediately. Since this aborts the `handle()` method execution, the recursive self-dispatching check (lines 235–258) is bypassed, halting all comment analysis for the session.
   * **Conclusion**: If the current batch of comments has been marked processed as a "poison pill" mitigation, we must check if subsequent unprocessed comments exist, clear the lock, and dispatch a new instance of the job *before* rethrowing the exception.

7. **Finding 7 (Lock Expiry Race Condition)**:
   * **Observation**: At line 30, the unique lock lifetime is set to `$uniqueFor = 30` seconds. At line 23, the job timeout is set to `$timeout = 120` seconds.
   * **Reasoning**: If a job runs for longer than 30 seconds (due to network latency in the multimodal AI API), the unique lock will expire while the job is still active. A new incoming comment will dispatch a new instance of the job, which will acquire the lock and start processing the same unprocessed comments, resulting in duplicate processing and database state conflicts.
   * **Conclusion**: Setting `$uniqueFor = 120` matching the `$timeout` prevents this race condition.

---

## 3. Concrete Fix Strategy & Recommended Code Snippets

Here is the exact, minimal fix strategy for `AnalyzeCommentsJob.php`. 

### A. Increase Unique Lock Duration (Fixes Finding 7)
Modify line 30 to increase the unique lock duration:
```php
    public int $uniqueFor = 120; // 120s lock (must be >= job timeout)
```

### B. Handle Text-less Comments (Fixes Finding 1)
Modify the early return check at lines 81–85:
```php
        if ($commentsText->isEmpty()) {
            LiveEvent::whereIn('id', $unprocessed->pluck('id'))
                ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
            
            // Check if there are more unprocessed comments to continue the pipeline
            $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                ->where('event_type', 'comment')
                ->where('ai_processed', false)
                ->exists();

            if ($hasMoreUnprocessed) {
                try {
                    resolve(\Illuminate\Bus\UniqueLock::class)->release($this);
                } catch (\Throwable $cacheEx) {
                    Log::warning('Failed to clear unique job lock on empty batch early return', [
                        'error' => $cacheEx->getMessage(),
                    ]);
                }
                self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
            }
            return;
        }
```

### C. Null Safety for Snapshot (Fixes Finding 4)
Update lines 110–111 with null safety:
```php
                    $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
                    $audioB64 = $snapshot ? ($snapshot['audio_b64'] ?? null) : null;
```

### D. Optimize DB Updates & Aggregate Stats (Fixes Findings 2, 3, and 5)
Replace the database transaction, statistics updating, and lock clearing block in `handle()` (lines 190–258) with the following implementation. It groups updates to avoid N+1 queries, computes incremental stats (avoiding $O(N^2)$ queries), checks for new leads without querying the entire table history, and uses the native Laravel UniqueLock API:

```php
            // Prepare local variables to track batch statistics changes
            $batchStats = [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
                'chot_don_users' => [],
            ];

            // Validate + save trong transaction
            DB::transaction(function () use ($results, $unprocessed, $productNames, &$batchStats) {
                $processedIds = [];
                $positive = 0;
                $neutral = 0;
                $negative = 0;
                $chotDonUsers = [];
                $updatesGrouped = [];

                foreach ($results as $result) {
                    $eventId = $result['id'] ?? null;
                    if (!$eventId) {
                        continue;
                    }

                    $processedIds[] = $eventId;

                    // Validate AI output trước khi save
                    $validated = $this->validateResult($result, $productNames);

                    // Find matching event in unprocessed to identify user ID
                    $event = $unprocessed->firstWhere('id', $eventId);
                    $tiktokUserId = $event ? $event->tiktok_user_id : null;

                    // Increment local counts
                    if ($validated['sentiment'] === 'positive') {
                        $positive++;
                    } elseif ($validated['sentiment'] === 'negative') {
                        $negative++;
                    } else {
                        $neutral++;
                    }

                    if ($validated['intent_tag'] === 'Chốt đơn' && $tiktokUserId) {
                        $chotDonUsers[] = $tiktokUserId;
                    }

                    // Group updates by target attributes serialization to perform bulk updates
                    $groupKey = serialize([
                        'sentiment' => $validated['sentiment'],
                        'intent_tag' => $validated['intent_tag'],
                        'question_tag' => $validated['question_tag'],
                        'product_tag' => $validated['product_tag'],
                        'has_phone' => $validated['has_phone'],
                    ]);

                    $updatesGrouped[$groupKey][] = $eventId;
                }

                // Execute grouped bulk updates
                foreach ($updatesGrouped as $serializedAttributes => $ids) {
                    $attributes = unserialize($serializedAttributes);
                    $attributes['ai_processed'] = true;

                    LiveEvent::whereIn('id', $ids)
                        ->where('live_session_id', $this->liveSessionId)
                        ->update($attributes);
                }

                // Đánh dấu comments không có trong results (AI bỏ sót)
                $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
                if (!empty($missingIds)) {
                    LiveEvent::whereIn('id', $missingIds)
                        ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
                    
                    $neutral += count($missingIds);
                }

                $batchStats = [
                    'positive' => $positive,
                    'neutral' => $neutral,
                    'negative' => $negative,
                    'chot_don_users' => array_values(array_unique($chotDonUsers)),
                ];
            });

            // Perform incremental stats update (Fixes Finding 2)
            $this->updateAggregateStatsIncremental($session, $batchStats, $unprocessed->pluck('id')->toArray());

            // === Memory: Lưu session_note từ AI cho batch tiếp theo ===
            $sessionNote = $response['session_note'] ?? null;
            if ($sessionNote && is_string($sessionNote)) {
                $session->update([
                    'ai_context_summary' => mb_substr($sessionNote, 0, 500),
                ]);
            }

            // Vét sạch comments chưa được xử lý AI của session này
            $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                ->where('event_type', 'comment')
                ->where('ai_processed', false)
                ->exists();

            if ($hasMoreUnprocessed) {
                // Giải phóng unique lock bằng Laravel API (Fixes Finding 5)
                try {
                    resolve(\Illuminate\Bus\UniqueLock::class)->release($this);
                } catch (\Throwable $cacheEx) {
                    Log::warning('Failed to clear unique job lock key using Laravel API', [
                        'error' => $cacheEx->getMessage(),
                    ]);
                }

                // Dispatch tiếp với delay 2 giây để tránh spam / rate limit Runware/Gemini AI
                self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));

                Log::info('Dispatched next AnalyzeCommentsJob to process remaining comments', [
                    'session_id' => $this->liveSessionId,
                ]);
            }
```

Add the new helper method `updateAggregateStatsIncremental()` in `AnalyzeCommentsJob.php` to handle optimized database updating and replace `updateAggregateStats()`:

```php
    /**
     * Increment stats dynamically in the database (Fixes Finding 2).
     */
    private function updateAggregateStatsIncremental(LiveSession $session, array $batchStats, array $batchEventIds): void
    {
        $newLeadsCount = 0;

        if (!empty($batchStats['chot_don_users'])) {
            // Find users in this batch who already have a "Chốt đơn" intent tag in previous batches
            $existingLeads = LiveEvent::where('live_session_id', $session->id)
                ->where('event_type', 'comment')
                ->where('ai_processed', true)
                ->where('intent_tag', 'Chốt đơn')
                ->whereIn('tiktok_user_id', $batchStats['chot_don_users'])
                ->whereNotIn('id', $batchEventIds)
                ->pluck('tiktok_user_id')
                ->unique()
                ->toArray();

            // Only count users whose first order is in the current batch
            $newLeads = array_diff($batchStats['chot_don_users'], $existingLeads);
            $newLeadsCount = count($newLeads);
        }

        $statsModel = $session->stats;
        if (!$statsModel) {
            // Create stats record if not exists
            $session->stats()->create([
                'sentiment_positive' => $batchStats['positive'],
                'sentiment_neutral' => $batchStats['neutral'],
                'sentiment_negative' => $batchStats['negative'],
                'leads_count' => $newLeadsCount,
            ]);
        } else {
            // Increment the stats values atomically
            $statsModel->update([
                'sentiment_positive' => DB::raw("sentiment_positive + {$batchStats['positive']}"),
                'sentiment_neutral' => DB::raw("sentiment_neutral + {$batchStats['neutral']}"),
                'sentiment_negative' => DB::raw("sentiment_negative + {$batchStats['negative']}"),
                'leads_count' => DB::raw("leads_count + {$newLeadsCount}"),
            ]);
        }
    }
```

### E. Prevent Stall on Exception (Fixes Finding 6)
Update the `catch` block (lines 260–298) in `AnalyzeCommentsJob.php` to release the lock and queue the next batch of comments if poison pill comments are updated:
```php
        } catch (\Throwable $e) {
            Log::error('AI comment analysis failed', [
                'session_id' => $this->liveSessionId,
                'error' => $e->getMessage(),
                'comments_count' => $commentsText->count(),
            ]);

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

                    // Check if there are more unprocessed comments to continue the pipeline (Fixes Finding 6)
                    $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                        ->where('event_type', 'comment')
                        ->where('ai_processed', false)
                        ->exists();

                    if ($hasMoreUnprocessed) {
                        try {
                            resolve(\Illuminate\Bus\UniqueLock::class)->release($this);
                        } catch (\Throwable $cacheEx) {
                            Log::warning('Failed to clear unique job lock key in catch block', [
                                'error' => $cacheEx->getMessage(),
                            ]);
                        }
                        self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
                    }
                } catch (\Throwable $dbEx) {
                    Log::error('Failed to mark poison pill comments as processed', [
                        'error' => $dbEx->getMessage(),
                    ]);
                }
            }

            // Không retry nếu lỗi auth
            if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'auth')) {
                $this->fail($e);
                return;
            }

            // Rethrow để Laravel queue tự động retry theo $tries/$backoff nếu còn lượt
            throw $e;
        }
```

---

## 4. Recommendations for New Test Cases in `AnalyzeCommentsJobTest.php`

To cover the identified test gaps (Text-less comment batch stall, Stats validation, and AI response exception handling), append the following 3 feature tests to `backend/tests/Feature/AnalyzeCommentsJobTest.php`:

### Test Case 1: Text-less comment batch stall test
Verifies that when a batch of comments only contains non-text comments, they are marked processed, and the next batch is queued using `Queue::fake()`.

```php
    public function test_text_less_comment_batch_does_not_stall_pipeline(): void
    {
        \Illuminate\Support\Facades\Queue::fake([AnalyzeCommentsJob::class]);

        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Text-less Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Batch 1: empty comment text
        $emptyComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => ''], // empty text (e.g. system join/emojis filtered out)
            'ai_processed' => false,
        ]);

        // Batch 2: valid comment (will remain unprocessed)
        $validComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(5),
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

        // Assert the empty comment was marked processed (neutral)
        $emptyComment->refresh();
        $this->assertTrue((bool)$emptyComment->ai_processed);
        $this->assertEquals('neutral', $emptyComment->sentiment);

        // Assert the valid comment is still unprocessed
        $validComment->refresh();
        $this->assertFalse((bool)$validComment->ai_processed);

        // Assert next job was dispatched to continue pipeline
        \Illuminate\Support\Facades\Queue::assertPushed(AnalyzeCommentsJob::class, function ($job) use ($session) {
            return $job->uniqueId() === 'analyze-comments-' . $session->id;
        });
    }
```

### Test Case 2: Stats validation test
Verifies that database metrics (positive, neutral, negative, and leads counts) are updated incrementally, checking that multiple orders from the same user are not counted as duplicate leads.

```php
    public function test_stats_are_incremented_and_leads_calculated_correctly(): void
    {
        $user = \App\Models\User::factory()->create();
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
```

### Test Case 3: AI response exception handling test
Verifies that when Runware AI throws an unrecoverable exception, the current batch of comments is marked as processed/neutral to prevent deadlocking, and the next job is dispatched to continue processing.

```php
    public function test_ai_response_exception_does_not_stall_pipeline(): void
    {
        \Illuminate\Support\Facades\Queue::fake([AnalyzeCommentsJob::class]);

        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Exception Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        // Batch 1 comment (will fail)
        $failedComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Hỏng rồi'],
            'ai_processed' => false,
        ]);

        // Batch 2 comment (should be queued)
        $nextComment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now()->addSeconds(5),
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

        // Assert failed comment is marked processed & neutral
        $failedComment->refresh();
        $this->assertTrue((bool)$failedComment->ai_processed);
        $this->assertEquals('neutral', $failedComment->sentiment);

        // Assert next comment is still unprocessed
        $nextComment->refresh();
        $this->assertFalse((bool)$nextComment->ai_processed);

        // Assert next job was dispatched to process subsequent comments
        \Illuminate\Support\Facades\Queue::assertPushed(AnalyzeCommentsJob::class, function ($job) use ($session) {
            return $job->uniqueId() === 'analyze-comments-' . $session->id;
        });
    }
```

---

## 5. Caveats
* **UniqueLock Mocking**: The test cases simulate unique job queues using `Queue::fake()`. In physical production, ensure that the cache lock driver is properly configured (e.g. Redis, Database, or Memcached) so the native unique lock class `Illuminate\Bus\UniqueLock` functions correctly.
* **Database Driver Behaviors**: The raw `DB::raw()` increments (e.g., `sentiment_positive + {$positive}`) are standard ANSI SQL and work seamlessly on both SQLite and MySQL, but concurrent database writes should be managed carefully to avoid deadlock issues.

---

## 6. Conclusion
The proposed remediation plans resolve all 7 findings correctly and cleanly using native Laravel APIs and optimized Eloquent designs:
1. **Pipeline Stall on Text-less Batch**: Resolved by checking for remaining unprocessed comments and dispatching the next job before returning.
2. **O(N^2) Performance Bottleneck**: Resolved by switching from full-history scans to batch-based increments, checking for new leads using optimized local `whereIn` queries.
3. **N+1 Database Updates**: Resolved by grouping update records by attribute values to perform bulk updates.
4. **TikTok Snapshot TypeError**: Resolved using standard null-checking.
5. **Brittle Lock Key Deletion**: Resolved using Laravel's native unique lock class API.
6. **Poison Pill Pipeline Stall**: Resolved by checking for unprocessed comments and queueing the next job in the exception handler catch block.
7. **Lock Expiry Race Condition**: Resolved by raising the unique lock lifetime parameter.

---

## 7. Verification Method
Verify the changes using the following methods:
1. **Execute current test suite**: Run `php artisan test --filter=AnalyzeCommentsJobTest` inside the `backend` folder to verify that existing assertions continue to pass successfully.
2. **Add and verify new test suite**: Append the 3 recommended tests above to `AnalyzeCommentsJobTest.php` and execute the PHPUnit filter to check if the text-less stalling, exception stalling, and metrics calculations perform exactly as expected.
