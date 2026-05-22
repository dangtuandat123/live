# Handoff Report

## 1. Observation

### R1: Conversion Funnel Distortion
* **Path**: `backend/resources/js/Pages/Lives/Show.tsx` (lines 2193-2198)
  ```typescript
  const funnelData = [
      { stage: 'Người xem', value: stats.total_views },
      { stage: 'Bình luận', value: stats.total_comments },
      { stage: 'Có SĐT/ĐC', value: potentialCustomers.length },
      { stage: 'Chốt đơn', value: stats.leads_count },
  ];
  ```
* **Path**: `backend/app/Http/Controllers/LiveSessionController.php` (lines 403-406, 735-748)
  ```php
  private function getPotentialCustomers(LiveSession $session): array
  {
      return $session->events()
          ->where('event_type', 'comment')
          ->where(function ($q) {
              $q->where('intent_tag', 'Chốt đơn')
                  ->orWhere('has_phone', true);
          })
          ->orderByDesc('event_at')
          ->limit(50)
          ->get()
          ...
  ```
  The query is hard-capped with `->limit(50)`.

### R2: Labeling Alignment
* **Path**: `backend/resources/js/Pages/Lives/Show.tsx` (lines 3008-3013)
  ```typescript
  <div className="text-lg font-bold">
      {stats.leads_count}
  </div>
  <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
      <PhoneIcon className="size-3" />
      KH tiềm năng
  </p>
  ```
  `stats.leads_count` represents unique commenters with `"Chốt đơn"` intent but is labeled `"KH tiềm năng"` with a `"PhoneIcon"`.

### R3: Cache Invalidation Bug
* **Path**: `backend/app/Http/Controllers/LiveSessionController.php` (lines 399-413)
  ```php
  $topProducts = Cache::remember("live_session_{$liveSession->id}_top_products", $cacheTtl, function () ...);
  $potentialCustomers = Cache::remember("live_session_{$liveSession->id}_potential_customers", $cacheTtl, function () ...);
  $topQuestions = Cache::remember("live_session_{$liveSession->id}_top_questions", $cacheTtl, function () ...);
  $statsHistory = Cache::remember("live_session_{$liveSession->id}_stats_history", $historyTtl, function () ...);
  ```
* **Path**: `LiveSessionController::updateEvent()` (lines 1054-1111) and `AnalyzeCommentsJob::handle()` contain no cache clears (`Cache::forget` or `cache()->forget`).

### R4: Redundancy & Clean Code
* **Path**: `backend/resources/js/Pages/Lives/Show.tsx` (lines 3020-3169 & 2392-2506)
  Both components display identical percentage and count metrics for positive, neutral, and negative comments.
* **Path**: `backend/app/Http/Controllers/LiveSessionController.php` (line 290)
  ```php
  'keywords' => $liveSession->keywords->pluck('keyword'),
  ```
  Passed to Inertia but never read or imported anywhere in `Show.tsx`.
* **Path**: `backend/resources/js/Pages/Lives/Show.tsx` (lines 3180-3226)
  The "Từ khóa được nhắc nhiều" card maps over `topQuestions` and `topProducts` and names them questions/products in code:
  ```typescript
  {topQuestions.map((item) => ...)}
  {topProducts.slice(0, 6).map((item) => ...)}
  ```

### R5: Phone Extraction Regex vs AI Sync
* **Path**: `backend/app/Http/Controllers/LiveSessionController.php` (lines 627-628)
  ```php
  $normalized = preg_replace('/[\s.\-]/', '', $event['data']['comment']);
  $hasPhone = (bool) preg_match('/0\d{9,10}/', $normalized);
  ```
* **Path**: `backend/app/Jobs/AnalyzeCommentsJob.php` (lines 308-310 & 545):
  ```php
  LiveEvent::whereIn('id', $ids)
      ->where('live_session_id', $this->liveSessionId)
      ->update($attributes); // $attributes contains 'has_phone' => (bool) ($result['has_phone'] ?? false)
  ```
  If AI output for `has_phone` is `false`, it overrides the DB field initially set to `true` by Regex.

---

## 2. Logic Chain

1. **R1**: Because `potentialCustomers` has a `->limit(50)` cap in `getPotentialCustomers()`, its length used as Stage 3 value in the funnel will never exceed 50. Since `stats.leads_count` (Stage 4) is uncapped, if it exceeds 50, Stage 4 > Stage 3, producing an inverted funnel. Therefore, calculating an uncapped `potentialCustomersCount` query is required.
2. **R2**: `stats.leads_count` represents unique orders (Chốt đơn), so displaying it as "KH tiềm năng" with a phone icon confuses users since "KH tiềm năng" in tabs/funnel represents both orders and phone-only commenters. Renaming the quick stat card to "Chốt đơn" and the funnel stage 3 to "KH tiềm năng" aligns terms logically.
3. **R3**: Updates made to ended streams (3600s cache TTL) via `updateEvent()` or background analysis do not clear the cache, meaning subsequent `fetchEvents` requests return stale cached data for up to an hour. Adding `Cache::forget(...)` calls at the write endpoints resolves this.
4. **R4**: The `session.keywords` Inertia prop is unused in `Show.tsx` and can be removed safely without breaking backend AI prompt generation. The Top Keywords card combines product names and question tags due to lack of a keyword aggregator. Creating a dedicated query to match comment content against session keywords provides accurate keyword analysis.
5. **R5**: AI bulk updates overwrite `has_phone` with `false` when AI fails to classify a comment's phone number. Since `$unprocessed` events are already in memory, checking `$event->has_phone` and forcing `$validated['has_phone'] = true` preserves regex matches while allowing AI to catch spelling or atypical formats.

---

## 3. Caveats
No caveats. All areas identified in the follow-up prompt were fully investigated and verified against the actual database and controller flow.

---

## 4. Conclusion
The 5 requirements are confirmed anomalies/gaps in the current implementation. Implementing the uncapped counts, renaming UI labels, invalidating cache at modification boundaries, removing the redundant Inertia prop, implementing keyword counting, and protecting regex-matched phone flags in memory resolves all reported concerns.

---

## 5. Verification Method

1. **Test Commands**:
   Run the following commands in `d:\Workspace\livestream\backend`:
   * `php artisan test` to confirm test suite integrity.
   * `php artisan test --filter=AnalyzeCommentsJob` to verify job and lock handling.
2. **Verification Checklist**:
   * **Cache Invalidation**: Edit a comment note on an ended stream and call `fetchEvents` immediately. The response must contain the new note.
   * **Funnel shape**: Verify that the funnel chart maintains Stage 3 >= Stage 4 even when there are more than 50 leads.
   * **Phone Sync**: Save a comment with a phone number, verify `has_phone` is `1`. Run `AnalyzeCommentsJob` with a mock response containing `has_phone: false`. The database record must retain `has_phone: 1`.
