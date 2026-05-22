## 2026-05-22T07:46:15Z

Target Files to modify:
1. `d:\Workspace\livestream\backend\app\Http\Controllers\LiveSessionController.php`
2. `d:\Workspace\livestream\backend\app\Jobs\AnalyzeCommentsJob.php`
3. `d:\Workspace\livestream\backend\resources\js\Pages\Lives/Show.tsx`

Requirements to implement:
1. R1: Conversion Funnel Distortion:
   - In `LiveSessionController.php`:
     - Add a helper method `getPotentialCustomersCount(LiveSession $session): int` to count the total distinct `tiktok_user_id` who are potential customers (expressed order intent "Chốt đơn" OR have phone numbers).
     - In `show()` page rendering, load and pass `'potentialCustomersCount' => $this->getPotentialCustomersCount($liveSession)` in the Inertia props.
     - In `fetchEvents()`, calculate and cache `potentialCustomersCount` with key `live_session_{$liveSession->id}_potential_customers_count` (TTL is the same as existing cached data). Return it in the JSON response.
   - In `Show.tsx`:
     - Add `potentialCustomersCount: number` to the page component props (ShowProps).
     - Add `potentialCustomersCount: number` to `LiveContext` type, provider default value, and updates.
     - Update the polling response handler to accept and set `potentialCustomersCount` in context.
     - Update `funnelData` in the funnel component to use `potentialCustomersCount` instead of `potentialCustomers.length` for the Stage 3 stage.

2. R2: Labeling Alignment:
   - In `Show.tsx`:
     - Rename Stage 3 label of the funnel chart from `"Có SĐT/ĐC"` to `"KH tiềm năng"`. Its value should be `potentialCustomersCount`.
     - In the Quick Stats cards (left sidebar panel), locate the card displaying `stats.leads_count` with `PhoneIcon`. Change its label from `"KH tiềm năng"` to `"Chốt đơn"`. Change its icon from `PhoneIcon` to `ShoppingCartIcon` (import from `lucide-react` if not already imported).

3. R3: Cache Invalidation Bug:
   - In `LiveSessionController.php`:
     - In `updateEvent()`, right before returning, clear the caches:
       - `live_session_{$sessionId}_top_products`
       - `live_session_{$sessionId}_potential_customers`
       - `live_session_{$sessionId}_top_questions`
       - `live_session_{$sessionId}_stats_history`
       - `live_session_{$sessionId}_potential_customers_count`
       - `live_session_{$sessionId}_top_keywords`
   - In `AnalyzeCommentsJob.php`:
     - In `handle()`, right after saving bulk event updates and updating session stats (and in the poison pill/ending block if necessary), clear the exact same cache keys:
       - `live_session_{$this->liveSessionId}_top_products`
       - `live_session_{$this->liveSessionId}_potential_customers`
       - `live_session_{$this->liveSessionId}_top_questions`
       - `live_session_{$this->liveSessionId}_stats_history`
       - `live_session_{$this->liveSessionId}_potential_customers_count`
       - `live_session_{$this->liveSessionId}_top_keywords`

4. R4: Redundancy & Clean Code:
   - In `LiveSessionController.php`:
     - Remove `'keywords'` from page props in `show()`.
     - Implement `getTopKeywords(LiveSession $session): array` on the controller. Configured keywords are retrieved from `$session->keywords()->pluck('keyword')->toArray()`. For each, query the count of comments containing it using `like`. Return a sorted descending array of `['keyword' => $keyword, 'count' => $count]` where count > 0.
     - Pass `'topKeywords' => $this->getTopKeywords($liveSession)` in `show()` Inertia props.
     - In `fetchEvents()`, retrieve and cache `topKeywords` with key `live_session_{$liveSession->id}_top_keywords` and TTL. Return it in the JSON response.
   - In `Show.tsx`:
     - Add `topKeywords: Array<{ keyword: string; count: number }>` to props and state. Remove unused `keywords` prop.
     - In the "Từ khóa được nhắc nhiều" card (Top Keywords) in the left panel, map over `topKeywords` to render the list of keywords and counts instead of merging `topProducts` and `topQuestions`.

5. R5: Phone Extraction Regex vs AI Synchronization:
   - In `AnalyzeCommentsJob.php`:
     - In `handle()`, when building the `$attributes` for database updates from the AI classification results, check the original event's `has_phone` value in memory (from `$unprocessed` collection).
     - If `$event->has_phone` is `true` (extracted by regex at ingestion), force `$validated['has_phone'] = true` to prevent the AI classification from overwriting it to `false`.

6. Run frontend build `npm run build` inside `d:\Workspace\livestream\backend`, and run backend tests `php artisan test` inside `d:\Workspace\livestream\backend` using your command runner to verify changes compile and pass successfully.
