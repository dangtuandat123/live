## 2026-05-22T09:59:28Z

You are a worker agent. Your task is to implement Milestones 2 and 3 (AI Agent & Backend Integration) for the AI Insights and Alerts project.

## Requirements:
1. **AI Agent definition**:
   - Create `App\Ai\Agents\LiveSessionAnalyzer.php` implementing `Laravel\Ai\Contracts\Agent` and `Laravel\Ai\Contracts\HasStructuredOutput` (similar to `CommentAnalyzer.php`).
   - Define attributes:
     - `#[Provider('deepseek')]`
     - `#[Model('deepseek-v4-flash')]`
     - `#[Temperature(0)]`
     - `#[MaxTokens(4096)]`
   - Implement `instructions()` to return system instructions for livestream analysis (providing summary and alerts JSON structure).
   - Implement `schema(JsonSchema $schema)` to return the schema matching the required JSON output (summary: string, alerts: array of type/title/desc/action).
2. **Backend Controller & Endpoint**:
   - Update `LiveSessionController` to add `refreshInsights(Request $request, LiveSession $liveSession)`.
     - Perform ownership/authorization check (must belong to authenticated user: `$liveSession->user_id === $request->user()->id`).
     - Bypasses the 30-second throttle.
     - Fetches recent 150 comments, current stats, products, keywords, and old memory (`ai_context_summary` or `session_note`).
     - Calls the Runware AI service using `chatJson` with `LiveSessionAnalyzer` instructions and inputs.
     - Saves the output: updates `ai_insights` and `ai_alerts` fields on the `LiveSession` model.
     - Updates the cache key `live_session_{$id}_last_insight_time` to current timestamp.
     - Clears/updates the session cache if needed.
     - Returns a JSON response with `ai_insights` and `ai_alerts`.
   - Update `fetchEvents` in `LiveSessionController` to return `ai_insights` and `ai_alerts` in its JSON response.
   - Update `routes/web.php` to define the POST route `lives.refresh-insights` for `refreshInsights`.
3. **AnalyzeCommentsJob Integration**:
   - Update `AnalyzeCommentsJob` to automatically trigger insights analysis:
     - Check throttle: if `live_session_{id}_last_insight_time` cache key is not set or is older than 30 seconds.
     - If allowed, execute the same analysis logic (fetch 150 comments, stats, etc.), call Runware AI service using `LiveSessionAnalyzer`, and update the `live_sessions` table (`ai_insights` and `ai_alerts` columns) and throttle cache key.
4. **Verification**:
   - Run `php artisan test` to verify everything works and no existing tests are broken.
   - Add new tests in `tests/Feature/LiveSessionAnalyzerTest.php` or similar test file to cover both the auto-trigger logic in `AnalyzeCommentsJob` and the manual refresh endpoint in `LiveSessionController`.

## Workspace & Output:
- Working directory: `d:\Workspace\livestream\.agents\worker_backend_integration_2`.
- Write your status updates, command output, and handoff report in `d:\Workspace\livestream\.agents\worker_backend_integration_2\handoff.md`.

> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.
