# Project: TikTok Livestream Comment Analysis Pipeline Bug Fixes

## Architecture
- **Backend Framework**: Laravel 11.x, PHP 8.x
- **Target Component**: Comment analysis job (`AnalyzeCommentsJob`) using Text, Audio, and Memory (LiveSession context).
- **External APIs**: Mocked Gemini Multimodal API via `RunwareAiService`, TikTok Snapshot service.
- **Database Schema**: `live_sessions` (contains `ai_context_summary`), `live_events` (comments, sentiment, intent, tags), `live_stats` (aggregated statistics).
- **Tests**: PHPUnit feature tests in `backend/tests/Feature/AnalyzeCommentsJobTest.php`.

## Code Layout
- **Job Class**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Model Classes**: `backend/app/Models/LiveSession.php`, `backend/app/Models/LiveEvent.php`, `backend/app/Models/LiveStats.php` (if exists)
- **Feature Tests**: `backend/tests/Feature/AnalyzeCommentsJobTest.php`

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Exploration & Strategy | Explorer analyzes files and drafts exact fix strategy | none | DONE (IDs: 1ae6f410, eaa0403a) |
| 2 | Implementation | Worker implements the fixes in `AnalyzeCommentsJob.php` | M1 | DONE (ID: 1b7aab23) |
| 3 | Verification (Review/Challenge/Audit) | Reviewers, Challengers, and Forensic Auditor verify correctness and integrity | M2 | IN_PROGRESS |
| 4 | Final Verification & Merge | Run all feature tests, verify zero stalls and optimized queries | M3 | PLANNED |

## Interface Contracts
- **AnalyzeCommentsJob**: Processes unprocessed comment events, fetches audio, interacts with Runware AI, updates event records, and aggregates statistics in `live_stats`.
- **LiveSession ↔ LiveEvent ↔ LiveStats**: Relationships for storing stream status, individual comments/intent/sentiment, and session-wide statistics respectively.
