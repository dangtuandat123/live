# Analysis Report — Follow-up Investigation

This report presents a read-only investigation and proposal for the 5 key requirements in the livestream analysis system. All analysis is coverage-based and supported by concrete codebase evidence.

---

## 1. R1: Conversion Funnel Distortion

### Direct Observation & Root Cause
In the frontend dashboard component `Show.tsx` (lines 2193-2198), the funnel stages are calculated as follows:
```typescript
const funnelData = [
    { stage: 'Người xem', value: stats.total_views },
    { stage: 'Bình luận', value: stats.total_comments },
    { stage: 'Có SĐT/ĐC', value: potentialCustomers.length },
    { stage: 'Chốt đơn', value: stats.leads_count },
];
```

In the backend controller `LiveSessionController.php` (lines 307 and 403), the `potentialCustomers` variable is populated by calling `getPotentialCustomers($liveSession)`. This method is defined as:
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
        ->limit(50)  // <-- HARD LIMIT OF 50 RECORDS
        ->get()
        ...
}
```

* **The Bug**: Since the query for `potentialCustomers` has a hard limit of `50`, the value of Stage 3 (`potentialCustomers.length` in the frontend) can never exceed `50`. However, `stats.leads_count` (Stage 4) is an aggregated count of all unique users who place an order, which can grow infinitely (e.g., 60, 100, or more).
* **The Result**: When there are more than 50 orders, the funnel chart displays a value for Stage 4 that is higher than Stage 3 (e.g. Stage 3 = 50, Stage 4 = 85), causing a distorted, upside-down funnel bar chart.

### Proposed Code Changes

1. **Backend (`LiveSessionController.php`)**:
   Introduce a separate, uncapped method `getPotentialCustomersCount(LiveSession $session)` to compute the total count of unique users who have left contact info or expressed checkout intent.
   
   Add this helper method:
   ```php
   private function getPotentialCustomersCount(LiveSession $session): int
   {
       return $session->events()
           ->where('event_type', 'comment')
           ->where(function ($q) {
               $q->where('intent_tag', 'Chốt đơn')
                 ->orWhere('has_phone', true);
           })
           ->distinct('tiktok_user_id')
           ->count('tiktok_user_id');
   }
   ```
   
   In `show()` and `fetchEvents()`, retrieve and pass this count:
   ```php
   // In show():
   return Inertia::render('Lives/Show', [
       ...
       'potentialCustomersCount' => $this->getPotentialCustomersCount($liveSession),
   ]);

   // In fetchEvents():
   $potentialCustomersCount = Cache::remember("live_session_{$liveSession->id}_potential_customers_count", $cacheTtl, function () use ($liveSession) {
       return $this->getPotentialCustomersCount($liveSession);
   });
   return response()->json([
       ...
       'potentialCustomersCount' => $potentialCustomersCount,
   ]);
   ```

2. **Frontend (`Show.tsx`)**:
   Accept `potentialCustomersCount` in the props and context, then update the funnel data generation:
   ```typescript
   // Inside StatsPanel / Page component:
   const { stats, potentialCustomersCount } = useLiveData();

   const funnelData = [
       { stage: 'Người xem', value: stats.total_views },
       { stage: 'Bình luận', value: stats.total_comments },
       { stage: 'Có SĐT/ĐC', value: potentialCustomersCount },
       { stage: 'Chốt đơn', value: stats.leads_count },
   ];
   ```
   Because `potentialCustomersCount` represents a superset of unique users (Chốt đơn OR Phone), it is mathematically guaranteed to be greater than or equal to `stats.leads_count` (Chốt đơn only), fully preventing funnel distortion.

---

## 2. R2: Labeling Alignment

### Semantic Inconsistencies Detected
1. **Quick Stats Panel (Left Column)**:
   * **Actual Code**:
     ```typescript
     <div className="text-lg font-bold">{stats.leads_count}</div>
     <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
         <PhoneIcon className="size-3" />
         KH tiềm năng
     </p>
     ```
     This displays `stats.leads_count` (the checkout count) but labels it as "KH tiềm năng" (Potential Customers) alongside a `PhoneIcon` (implying phone count).
2. **Funnel Chart**:
   * Stage 3 is labeled `"Có SĐT/ĐC"` but represents the length of `potentialCustomers` (which includes both "Chốt đơn" and "has_phone" comments).
   * Stage 4 is labeled `"Chốt đơn"` and uses `stats.leads_count`.
3. **Sidebar / Tabs**:
   * The `"KH tiềm năng"` tab lists the comments returned in `potentialCustomers` (Chốt đơn + Phone, capped at 50).

### Proposed Alignment Strategy
* **"Chốt đơn" (Confirmed Lead / Order Intent)**: Unique users who have explicitly expressed an intent to purchase (e.g. `intent_tag = 'Chốt đơn'`). Value: `stats.leads_count`.
* **"KH tiềm năng" (Potential Customer)**: Unique users who are in the broader pool of interest (either left a phone/address OR chốt đơn). Value: `potentialCustomersCount`.

#### Alignment Changes in `Show.tsx`:
1. **Quick Stats Card (left column)**:
   * Change label from `"KH tiềm năng"` to **`"Chốt đơn"`**.
   * Change icon from `PhoneIcon` to **`ShoppingCartIcon`** or **`PackageIcon`**.
   * Keep value as `stats.leads_count`.
   * *Rationale*: This displays the actual checkout success count, matching the final stage of the funnel.
2. **Funnel Chart**:
   * Change Stage 3 label from `"Có SĐT/ĐC"` to **`"KH tiềm năng"`** (value: `potentialCustomersCount` as calculated in R1).
   * Keep Stage 4 label as **`"Chốt đơn"`** (value: `stats.leads_count`).
   * *Rationale*: Represents a logical progression from Viewer -> Commenter -> Potential Customer (exhibited interest/contact info) -> Confirmed Order.

---

## 3. R3: Cache Invalidation Bug

### Direct Observation & Root Cause
In `LiveSessionController.php` (lines 399-413), data for live session views is fetched using caching to optimize polling performance:
```php
$topProducts = Cache::remember("live_session_{$liveSession->id}_top_products", $cacheTtl, function () ...);
$potentialCustomers = Cache::remember("live_session_{$liveSession->id}_potential_customers", $cacheTtl, function () ...);
$topQuestions = Cache::remember("live_session_{$liveSession->id}_top_questions", $cacheTtl, function () ...);
$statsHistory = Cache::remember("live_session_{$liveSession->id}_stats_history", $historyTtl, function () ...);
```
* **The Bug**:
  1. When a user updates an event (e.g. tags, pinned state, quantities) via `LiveSessionController::updateEvent()`, these changes modify `live_events` in the database, but **no cache invalidation occurs**.
  2. When the background job `AnalyzeCommentsJob` finishes processing comments and updates classification tags, sentiment scores, and stats in the database, **no cache invalidation occurs**.
  3. During active streams, this leads to a 5-10 second data sync lag. For **ended** streams (where `$cacheTtl = 3600`), the cache persists for **1 hour**. Updates made to ended stream logs (e.g., cleaning up customer addresses or editing notes) will not reflect in the UI for up to an hour.

### Proposed Code Changes

Define a centralized cache invalidation block to clear all caches associated with a session ID:
```php
$sessionId = $session->id;
Cache::forget("live_session_{$sessionId}_top_products");
Cache::forget("live_session_{$sessionId}_potential_customers");
Cache::forget("live_session_{$sessionId}_top_questions");
Cache::forget("live_session_{$sessionId}_stats_history");
Cache::forget("live_session_{$sessionId}_potential_customers_count"); // Clear new count from R1
```

#### Places to insert invalidation code:

1. **`LiveSessionController::updateEvent`** (Add before returning the JSON response, around line 1105):
   ```php
   // Clear cache so frontend receives updated details
   $sessionId = $liveEvent->live_session_id;
   Cache::forget("live_session_{$sessionId}_top_products");
   Cache::forget("live_session_{$sessionId}_potential_customers");
   Cache::forget("live_session_{$sessionId}_top_questions");
   Cache::forget("live_session_{$sessionId}_stats_history");
   Cache::forget("live_session_{$sessionId}_potential_customers_count");
   ```

2. **`AnalyzeCommentsJob.php`** (Add inside the transaction/completion block of `handle()` right after updating stats, around line 335):
   ```php
   // Invalidate cache after AI classification batch updates DB
   cache()->forget("live_session_{$this->liveSessionId}_top_products");
   cache()->forget("live_session_{$this->liveSessionId}_potential_customers");
   cache()->forget("live_session_{$this->liveSessionId}_top_questions");
   cache()->forget("live_session_{$this->liveSessionId}_stats_history");
   cache()->forget("live_session_{$this->liveSessionId}_potential_customers_count");
   ```

---

## 4. R4: Redundancy & Clean Code

### 1. Duplicated Sentiment Charts
* **Left Column**: Displays the "Phân tích cảm xúc" card (lines 3020-3169), showing percentages, raw counts, and horizontal progress bars for positive, neutral, and negative sentiment.
* **"Thống kê" Tab**: Displays the "Phân bổ cảm xúc" card (lines 2392-2506), rendering a `PieChart` with the exact same counts, percentages, and breakdowns.
* **Proposal**: Keep the quick breakdown bars in the left column for general monitoring, and display the detailed Pie Chart in the "Thống kê" tab. This duplication is a design choice (quick view vs full chart), but we can refactor `Show.tsx` to reuse a helper function or a custom `<SentimentBreakdown />` component to avoid duplicate percentage/color mapping logic.

### 2. Redundant Keywords Prop
* **Actual Code**: In `LiveSessionController::show()` (line 290):
  `'keywords' => $liveSession->keywords->pluck('keyword'),`
  This array of session keywords is loaded and passed to Inertia.
* **Inconsistency**: The frontend file `Show.tsx` defines the keyword prop in its interface but **never uses it anywhere** in the UI.
* **Proposal**: Remove `'keywords' => ...` from the Inertia page props inside `LiveSessionController::show()`. Note that we **must keep** the relation `keywords()` on `LiveSession` and its usage in `AnalyzeCommentsJob` for AI system prompts.

### 3. Misleading "Từ khóa được nhắc nhiều" (Top Keywords) Card
* **Actual Code**: In `Show.tsx` (lines 3171-3226), the card "Từ khóa được nhắc nhiều" merges and renders the contents of `topQuestions` (categories of questions like "Hỏi giá") and `topProducts` (product tags like "Áo thun").
* **Why it's wrong**: Question tags and product tags are already displayed in detail in the "Sản phẩm" and "Câu hỏi" tabs. This card does not display *actual* keywords (frequent words in comments) or track the session's configured keywords.
* **Proposal**:
  Modify the backend to aggregate frequency counts of the session's configured tracking keywords, and pass them to the frontend:
  ```php
  // In LiveSessionController.php
  private function getTopKeywords(LiveSession $session): array
  {
      $keywords = $session->keywords()->pluck('keyword')->toArray();
      if (empty($keywords)) {
          return [];
      }
      
      $counts = [];
      foreach ($keywords as $keyword) {
          $count = $session->events()
              ->where('event_type', 'comment')
              ->where('data->comment', 'like', "%{$keyword}%")
              ->count();
          if ($count > 0) {
              $counts[] = ['keyword' => $keyword, 'count' => $count];
          }
      }
      
      usort($counts, fn($a, $b) => $b['count'] <=> $a['count']);
      return $counts;
  }
  ```
  Send this as `topKeywords` to the frontend, and update `Show.tsx` to map over `topKeywords` in this card instead.

---

## 5. R5: Phone Extraction Regex vs AI Sync

### Direct Observation & Overwrite Bug
1. **At Ingestion (`LiveSessionController::fetchAndStoreEvents`)**:
   The controller runs a Regex match on incoming comment texts (lines 627-628):
   ```php
   $normalized = preg_replace('/[\s.\-]/', '', $event['data']['comment']);
   $hasPhone = (bool) preg_match('/0\d{9,10}/', $normalized);
   ```
   If a comment contains a phone number (e.g. `0912345678`), `has_phone` is saved as `true` in the database immediately (providing "Instant Phone Capture").

2. **In Background (`AnalyzeCommentsJob`)**:
   The job processes comments in batches, sending them to the AI analyzer. The AI classifies comments and returns `has_phone: true/false`.
   In `validateSingleResult()` (line 545):
   ```php
   'has_phone' => (bool) ($result['has_phone'] ?? false),
   ```
   The job then groups these comments and runs a bulk database update (lines 308-310):
   ```php
   LiveEvent::whereIn('id', $ids)
       ->where('live_session_id', $this->liveSessionId)
       ->update($attributes); // $attributes contains 'has_phone' => false from AI
   ```
* **The Bug**: If the AI returns `has_phone: false` (due to context omission, bad parsing, or translation errors), the bulk update **overwrites** the Regex-captured value in the database, setting it back to `false`.

### Proposed Code Changes
To prevent the background job from erasing phone numbers captured by Regex, we can perform an in-memory check against the loaded `$event` records in the `AnalyzeCommentsJob::handle()` loop:

```php
// In AnalyzeCommentsJob.php (around line 260, inside the loop processing AI results):
foreach ($results as $result) {
    $eventId = (int) ($result['id'] ?? 0);
    $event = $unprocessed->firstWhere('id', $eventId);
    if (! $event) {
        continue;
    }

    $validated = $this->validateSingleResult($result, $productNames);

    // SYNCHRONIZATION GUARD: If Regex already captured a phone number, preserve it
    if ($event->has_phone) {
        $validated['has_phone'] = true;
    }
    
    ...
```

* **Rationale**:
  * Since the `$unprocessed` event models are already loaded in memory, `$event->has_phone` is cheap to check.
  * Overriding `$validated['has_phone'] = true` ensures that this comment is serialized and updated under a group key where `'has_phone' => true`.
  * If the AI detects a phone number that the Regex missed (e.g., spelled out or written in an atypical format), the AI's `true` result is still applied. This results in a robust logical OR: `has_phone = (Regex matched) OR (AI matched)`.

---

## Verification Plan

To verify these improvements:
1. **PHPUnit Tests**: Run `php artisan test` to confirm no regressions are introduced in existing stats or analysis logic.
2. **Funnel Graph Proof**: Inspect the funnel with more than 50 leads; if Stage 3 (`potentialCustomersCount`) is uncapped, Stage 3 value must be `>=` Stage 4 value (`stats.leads_count`).
3. **Regex Retention Proof**: Seed a comment with a phone number (e.g. `0987654321`), verify it gets `has_phone = 1`. Dispatch the AI analyzer job mock-returning `has_phone = false`. Confirm that `has_phone` remains `1` in the database.
4. **Cache Proof**: Call `updateEvent` on an ended stream, verify that cache keys are cleared and a subsequent `fetchEvents` request serves the updated database record instantly.
