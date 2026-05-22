# Scope: AI System Code-Path Audit

## Architecture
The livestreaming application integrates AI capabilities to analyze comments (sentiment analysis, keyword extraction, etc.) and process audio data.
Key Components:
- **Frontend**: React/TypeScript component `Show.tsx` displaying livestream sessions, real-time analytics, comments, and top keywords.
- **Backend Controllers**: `LiveSessionController.php` managing livestream sessions, event fetching (`fetchEvents`), updating events, stopping sessions.
- **Backend Jobs & Services**:
  - `AnalyzeCommentsJob.php`: Asynchronous job to process batches of comments using AI and extract keywords and sentiment.
  - `CommentAnalyzer.php`: Service wrapper that calls AI provider or Python service to analyze comment batches.
  - `TikTokService.php`: Service handling external TikTok API connections.
- **Infrastructure**: Subscription limits (`ai_credits` / `used_ai_credits`), Redis or other cache mechanisms (`Cache::forget`).

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Code Mapping | Inspect Show.tsx, LiveSessionController, TikTokService, AnalyzeCommentsJob, CommentAnalyzer, subscription credits, and FFmpeg logic. Trace data flows. | none | DONE |
| 2 | Verification & Compilation | Execute `php artisan test` and `npm run build` via a worker. Compile findings into `evidence_deep_audit_report_ai.md`. | M1 | DONE |
| 3 | Review & Certification | Review generated report against strict evidence audit checklist. | M2 | DONE |
