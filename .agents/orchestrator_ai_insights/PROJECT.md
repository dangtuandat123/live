# Project: AI Insights & Alerts Implementation

## Architecture
- Database: Add `ai_insights` (text, nullable) and `ai_alerts` (json, nullable) to `live_sessions` table.
- Agent: `App\Ai\Agents\LiveSessionAnalyzer.php` (Laravel AI contract, structured JSON output).
- Backend Job/Service logic:
  - Cache key `live_session_{id}_last_insight_time` to track throttle (30s).
  - Automatically run analysis when new comments are analyzed (in `AnalyzeCommentsJob` or similar backend hook).
  - Explicit manual API `lives.refresh-insights` to trigger analysis bypassing throttle.
- Frontend UI:
  - `Show.tsx` React template showing AI Summary (insights) and Alerts.
  - Button "Cập nhật AI Insights" on the panel to manually trigger.
  - Render details with alert severity styling (colors/icons).

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | DB Migration | Create migration to add `ai_insights` and `ai_alerts` fields in `live_sessions`, run migration, update `LiveSession` model. | None | DONE |
| 2 | LiveSessionAnalyzer Agent | Implement `App\Ai\Agents\LiveSessionAnalyzer.php` implementing `Laravel\Ai\Contracts\Agent`, `Laravel\Ai\Contracts\HasStructuredOutput` with correct system prompt, instructions, and JSON schema. | M1 | DONE |
| 3 | Backend Integration & Throttle | Update `AnalyzeCommentsJob` to call `LiveSessionAnalyzer` when comments are processed, checking 30s cache throttle. Create manual refresh endpoint/controller method. | M2 | DONE |
| 4 | Frontend UI & Show.tsx | Update PageProps and LivesShow in `Show.tsx` to handle `ai_insights`/`ai_alerts`, render summary/alerts with color badges and manual refresh button. | M3 | DONE |
| 5 | Testing & Verification | Write PHPUnit test for LiveSessionAnalyzer and manual refresh API. Run tests and frontend compilation. | M4 | DONE |
| 6 | Fix Quality Review Findings | Fix findings in controller (throttling, credit gating, exceptions, credits increment) and frontend error handling. | M5 | DONE |

## Code Layout
- Model: `d:\Workspace\livestream\backend\app\Models\LiveSession.php`
- Agent: `d:\Workspace\livestream\backend\app\Ai\Agents\LiveSessionAnalyzer.php`
- Controller: `d:\Workspace\livestream\backend\app\Http\Controllers\LiveSessionController.php`
- Job: `d:\Workspace\livestream\backend\app\Jobs\AnalyzeCommentsJob.php`
- Page: `d:\Workspace\livestream\backend\resources\js\Pages\Lives\Show.tsx`
- Test: `d:\Workspace\livestream\backend\tests\Feature\LiveSessionAnalyzerTest.php`
