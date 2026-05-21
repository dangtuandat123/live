# Audit Report: TikTok Livestream Comment Analysis Pipeline (Solution G)

**Date**: 2026-05-21  
**Scope**: Solution G (Text + Audio + Memory) for TikTok Livestream Comment Analysis  
**Mode**: static/code-path audit  
**Confidence**: High  
**Findings**: 1 High/Medium Bug, 2 High Performance issues, 1 Medium Bug, 1 Medium Reliability/Architecture concern, 1 Medium Test Gap.

---

## Scope, Stack, and Source of Truth

| Item | Value |
|---|---|
| Target Files | <ul><li>`backend/app/Jobs/AnalyzeCommentsJob.php`</li><li>`backend/app/Models/LiveSession.php`</li><li>`backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php`</li><li>`backend/tests/Feature/AnalyzeCommentsJobTest.php`</li></ul> |
| Stack/framework | Laravel 11.x, PHP 8.x, MySQL/SQLite, Runware AI (Gemini Flash Lite 3.1 multimodal model), TikTok Python Live Snapshot Service (FFmpeg audio capture) |
| Expected user behavior | Streamer runs a TikTok LIVE session. Viewers comment in real-time. The system captures the comments, combines them with 3s audio snapshots and memory context (previous batch summary), and runs Gemini AI to classify sentiment, intent, questions, products, and contact numbers. Streamer views live metrics (leads count, sentiment score) updated dynamically. |
| Expected backend/data behavior | Background jobs process comments in batches of 50. For each batch, comments are mapped, TikTok audio is fetched, a system prompt with product context + memory is sent to Runware chat completions, and responses are validated and written to `live_events`. The system updates `live_stats` aggregates and recursively dispatches the next batch. |
| Source of truth | Target PHP source code and migration files. |
| Exclusions | Python livestream ingestion service, actual external HTTP connection to Runware AI. |

---

## Coverage Ledger

| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 0 | 0 | 0 | No UI components in scope (Backend only). |
| User actions | 0 | 0 | 0 | Main action is background queue job trigger. |
| API/actions | 2 | 2 | 0 | `RunwareAiService::chatMultimodal` and `TikTokService::getSnapshot` |
| Services/domain | 5 | 5 | 0 | `AnalyzeCommentsJob::handle`, `buildSystemPrompt`, `validateResult`, `matchProductTag`, `updateAggregateStats` |
| DB/schema/config | 3 | 3 | 0 | `live_sessions`, `live_events`, and `live_stats` table schemas and migrations |
| Auth/permissions | 0 | 0 | 0 | Standard Laravel queue context (no auth header needed; runs in CLI/daemon). |
| State/cache | 1 | 1 | 0 | Laravel Unique Job lock cache key `laravel_unique_job:...` |
| Tests | 7 | 7 | 0 | `Tests\Feature\AnalyzeCommentsJobTest` (7 test cases) |

---

## Expected Behavior Contract

| Component / File | Input | Output / Returns | Side Effects | Invariants |
|---|---|---|---|---|
| `AnalyzeCommentsJob` | `liveSessionId` (int) | `void` | Updates `live_events` rows (sentiment, tags, ai_processed). Updates `live_stats` rows (positive, neutral, negative counts, leads). Updates `live_sessions` row (ai_context_summary). Dispatches new `AnalyzeCommentsJob`. | Can only process comments if session status is `live` or `connecting`. Unique lock prevents multiple concurrent runs. |
| `LiveSession` Model | DB columns | Eloquent model instance | None | Status must map to correct states (`live`, `connecting`, `ended`, etc.). |
| Migration `..._add_ai_context_summary_...` | Blueprint table | DB Schema change | Adds `ai_context_summary` nullable text field to `live_sessions` table. | Cannot break backward compatibility. |
| `AnalyzeCommentsJobTest` | Test Setup | Test status (pass/fail) | Creates dummy database records, mocks API calls. | DB refreshed after each test via `RefreshDatabase`. |

---

## Static UX Matrix
*Note: This is a backend-only pipeline. No UI elements are checked.*

| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| Dashboard/Stream View | Metrics display | `LiveStat` model | Displays real-time sentiment and leads count | Relies on backend `live_stats` table data | No direct UI code in scope |

---

## Action Matrix

| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Comment ingestion & analysis | `AnalyzeCommentsJob::handle` | Session status check, empty batch check, sentiment/tags domain validation. | Relies on Laravel Queue worker execution limits. | Logs success or failures. Rethrows exceptions for retries. | Runware AI Chat Multimodal, TikTok Live Snapshot | Pipeline stall if text-less comment batch is processed. |

---

## Copy/Text Matrix

| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| System Prompt Instructions | `AnalyzeCommentsJob::buildSystemPrompt()` | Guide AI accurately on Vietnamese comment patterns (Chốt đơn, SĐT, minigame vs orders). | Correctly mentions rules for Vietnamese live commerce (e.g. SĐT, intents). | None. Prompt layout is highly structured and clean. |

---

## Frontend-Backend Matrix
*Note: No frontend consumer code is directly in scope. Checked backend API boundaries.*

| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| View Lead Stats | N/A | `LiveStat` model query | Fetch session stats | Scoped by session ID | Read from `live_stats` | Updates UI stats | Stats are updated dynamically at the end of each batch |

---

## Backend Abuse Matrix

| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| Queue Job execution | Job runs in background daemon; not directly exposable. | Input is `liveSessionId`. | Wrong `liveSessionId` results in job returning immediately. | Replay causes duplicate DB reads but no corruption (idempotent updates). | Safe since it verifies against DB `live_sessions` table status. |

---

## Invariant and State Matrix

| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Session status gate | `AnalyzeCommentsJob:60-62` | Session status is 'ended' | Returns early | Exits without modifications or queue re-dispatching. |
| Unique Job lock | `AnalyzeCommentsJob:30,47` | Duplicate jobs triggered | Unique lock key active for 30s | Only one instance runs at a time. |

---

## State/Async/Race Matrix

| Async / Race Condition | Mechanism | Evidence | Impact | Risk / Mitigator |
|---|---|---|---|---|
| Concurrent execution due to cache lock expiry | `uniqueFor = 30` lock expires while AI API call hangs. | Lock expires; another worker pulls next job and queries same unprocessed comments. | Dual AI calls for same comments; race conditions in updating. | Low probability unless API latency exceeds 30s. Can be mitigated by increasing `uniqueFor`. |
| Recursive queue loop execution | Manually clearing lock via `cache()->forget()` and self-dispatching. | `AnalyzeCommentsJob:241-253` | Can lead to high rate-limit hits or stack overflow/queue flooding if queue workers are backed up. | Delayed dispatch (2s) acts as throttler. |

---

## Security/Privacy Matrix

| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| API Keys | Unauthorized view | Hardcoded secrets | None | Keys are fetched via `config('services...')` with `.env` fallback. | Safe |
| User phone numbers | Data breach | Plain text `has_phone` scan | Saved in DB | Stored in `live_events` table as plain text. | Low (only boolean flag `has_phone` is stored in structured field, actual number is in unstructured `data` column). |

---

## Duplicate/Dead Flow Matrix

| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| Redundant cache key format | `laravel_unique_job:` | Cache lock key prefix might mismatch if Laravel version or config prefix changes. | Job uses hardcoded cache prefix string to manually clear lock. |

---

## Test/Mutation Gaps

| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Text-less comment batch stall | None | Empty comments text batch returns early without checking/dispatching more. | No | Test asserting that if a batch has text-less comments, remaining comments in the session are still processed. |
| Stats aggregation logic | None | Modify `updateAggregateStats` to return wrong calculations or skip update. | No | Test asserting that `live_stats` aggregates match processed events. |
| Invalid JSON array format | None | Mock Runware AI returning invalid structure. | No | Test verifying error handling and queue fallback for invalid formats. |

---

## Findings

### 1. High/Medium — Text-less Comments Pipeline Stall
- **Type**: Confirmed Bug (Pipeline stall)
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 81-85)
- **Evidence**:
  ```php
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      return;
  }
  ```
- **Cross-check**: Tested against recursive execution check on lines 234-258. The return bypasses these lines entirely.
- **Why wrong/risky**: If a batch contains only comments with empty text (e.g. system join/likes registered under comments, or comments with only emojis/invalid data), the job marks them as processed and exits immediately (`return;`). Because it exits without checking if there are more unprocessed comments and without dispatching the next job, the analysis pipeline stalls. Subsequent comments will never be processed.
- **Impact**: Livestream comment analysis halts permanently mid-stream if a batch contains only text-less comments.
- **Scenario**: A user receives 50 comments containing only emojis. The pipeline processes them, updates them, returns early, and halts. Any new comments from that point on are ignored.
- **Minimal fix**:
  Before returning on line 84, check if more unprocessed comments exist and dispatch the next job:
  ```php
  if ($commentsText->isEmpty()) {
      LiveEvent::whereIn('id', $unprocessed->pluck('id'))
          ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
      
      // Dispatch next job if there are more unprocessed events
      $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
          ->where('event_type', 'comment')
          ->where('ai_processed', false)
          ->exists();

      if ($hasMoreUnprocessed) {
          $lockKey = 'laravel_unique_job:' . self::class . ':' . $this->uniqueId();
          cache()->forget($lockKey);
          self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
      }
      return;
  }
  ```
- **Validation**: Write a test in `AnalyzeCommentsJobTest` where a batch of empty comments is followed by valid comments, and assert that the valid comments are eventually processed.
- **Confidence**: High

### 2. High — O(N^2) Performance Bottleneck in Stats Aggregation
- **Type**: Performance Bottleneck
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 465-487)
- **Evidence**:
  ```php
  $stats = LiveEvent::where('live_session_id', $session->id)
      ->where('event_type', 'comment')
      ->where('ai_processed', true)
      ->selectRaw("
          SUM(...) as positive,
          ...
      ")
      ->first();
  ```
- **Cross-check**: Check against database design and query frequency. This is run at the end of every single batch (every 50 comments processed).
- **Why wrong/risky**: For a live session with tens of thousands of comments, this aggregates the entire history of the session repeatedly. On batch 200 (10,000 comments), it queries 10,000 rows. By the end of a session, the database spends exponentially more CPU and I/O scanning the same rows, leading to $O(N^2)$ reads. This will severely degrade database performance, cause locks, and slow down processing.
- **Impact**: Database CPU spikes, transaction locks, and increased latency as a live stream grows longer.
- **Minimal fix**:
  1. Increment stats dynamically using the current batch's results (using database increments/decrements):
     ```php
     $session->stats()->updateOrCreate(
         ['live_session_id' => $session->id],
         [
             'sentiment_positive' => DB::raw("sentiment_positive + {$batchPositive}"),
             // ...
         ]
     );
     ```
  2. Or delegate statistics calculation to a debounced/delayed job or a cron schedule rather than running a heavy aggregate query synchronously inside the main pipeline worker loop.
- **Validation**: Run benchmark with 5,000+ comment records and measure execution time.
- **Confidence**: High

### 3. Medium — N+1 Database Write Operations in Transaction Loop
- **Type**: Performance Issue
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 203-212)
- **Evidence**:
  ```php
  foreach ($results as $result) {
      ...
      LiveEvent::where('id', $eventId)
          ->where('live_session_id', $this->liveSessionId)
          ->update([
              'sentiment' => $validated['sentiment'],
              'intent_tag' => $validated['intent_tag'],
              ...
          ]);
  }
  ```
- **Cross-check**: A standard batch has up to 50 comments, meaning up to 50 UPDATE queries executed sequentially inside a database transaction block.
- **Why wrong/risky**: Running individual UPDATE queries in a loop is slow and creates database write overhead and locking contention, especially when running multiple concurrent queue workers.
- **Impact**: Slower job execution times and database transaction overhead.
- **Minimal fix**:
  Gather all changes and perform a bulk update or use a database feature like `upsert()`, or execute SQL `CASE` statements to update multiple rows in a single query.
- **Validation**: Measure database write latency under queue concurrency.
- **Confidence**: High

### 4. Medium — TypeError on TikTok Snapshot Failure
- **Type**: Type Safety / Bug
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 110-111)
- **Evidence**:
  ```php
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot['audio_b64'] ?? null;
  ```
- **Cross-check**: `TikTokService::getSnapshot` returns `?array` and returns `null` on exception or network failure.
- **Why wrong/risky**: If the service is down or returns an HTTP failure, `$snapshot` is `null`. Attempting to access `$snapshot['audio_b64']` throws a PHP `TypeError` in PHP 8.x. Although this is wrapped in a `try-catch (\Throwable $snapEx)`, it causes an unnecessary PHP exception to be generated and thrown for normal flow control, which is bad practice and noisy for exception monitoring.
- **Impact**: Unnecessary internal exceptions thrown, creating noise in logs.
- **Minimal fix**:
  Verify `$snapshot` is not null before accessing its keys:
  ```php
  $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
  $audioB64 = $snapshot ? ($snapshot['audio_b64'] ?? null) : null;
  ```
- **Validation**: Call snapshot logic in tests returning mock null and confirm that a PHP TypeError is no longer thrown (the warning catch block isn't triggered by a TypeError but rather bypassed clean).
- **Confidence**: High

### 5. Medium — Brittle Manual Cache Lock Deletion
- **Type**: Reliability / Architecture Risk
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php` (Lines 241-248)
- **Evidence**:
  ```php
  $lockKey = 'laravel_unique_job:' . self::class . ':' . $this->uniqueId();
  try {
      cache()->forget($lockKey);
  }
  ```
- **Cross-check**: Laravel's internal unique job locking key format relies on the cache prefix config, the serialization driver, and the version of framework used.
- **Why wrong/risky**: Hardcoding the key string `'laravel_unique_job:'` breaks abstraction. If the application configures a custom cache prefix or uses Redis with different configuration keys, manual deletion might target the wrong cache key or fail. Furthermore, clearing unique locks manually while the current job is still executing can lead to race conditions where jobs overlap.
- **Impact**: Premature cache key deletion leading to overlapping job execution, or failing to clear the lock entirely.
- **Minimal fix**:
  Use Laravel's `Illuminate\Contracts\Cache\Repository` or custom lock abstractions rather than guessing the key name, or adjust `uniqueFor` time constraints.
- **Validation**: Test cache locks using different cache configurations (e.g. Redis vs Database vs File cache).
- **Confidence**: High

### 6. Medium — Test Gap: Missing Assertions on LiveStats Updates
- **Type**: Test Gap
- **Location**: `backend/tests/Feature/AnalyzeCommentsJobTest.php`
- **Evidence**: The tests mock and execute the job but never verify that the `live_stats` record is updated with correct counts of positive/neutral/negative sentiment and lead counts.
- **Why wrong/risky**: Regressions in stats calculation code (`updateAggregateStats`) won't be caught by the test suite.
- **Impact**: Potential calculation errors shown on frontend dashboard without developer awareness.
- **Minimal fix**:
  Add assertions in `test_it_analyzes_comments_and_saves_ai_tags`:
  ```php
  $this->assertDatabaseHas('live_stats', [
      'live_session_id' => $session->id,
      'sentiment_neutral' => 2,
      'leads_count' => 2, // distinct users
  ]);
  ```
- **Validation**: Run the tests and verify assertions.
- **Confidence**: High

---

## Suggested Fix Order
1. **Fix 1 (Text-less Comments Pipeline Stall)**: Critical bug preventing the analysis pipeline from processing subsequent batches.
2. **Fix 2 (O(N^2) Stats Aggregation)**: Critical performance bottleneck that will degrade DB performance as comments grow.
3. **Fix 4 (TypeError on TikTok Snapshot)**: Quick, clean type-safety check.
4. **Fix 3 (N+1 Database Updates)**: Performance optimization for database writes.
5. **Fix 5 (Brittle cache lock key deletion)**: Refactoring to ensure lock reliability.
6. **Fix 6 (Test Gaps)**: Add validation for stats aggregation and empty batches.

---

## Audit Decision

**Decision**: **Fix before merge** (Due to the high-severity pipeline stall and $O(N^2)$ database bottlenecks).
