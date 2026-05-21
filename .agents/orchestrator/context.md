# Project Context

## Target Files
The target files for Solution G (Text + Audio + Memory) TikTok Livestream Comment Analysis Pipeline are:
- `backend/app/Jobs/AnalyzeCommentsJob.php`
- `backend/app/Models/LiveSession.php`
- `backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php`
- `backend/tests/Feature/AnalyzeCommentsJobTest.php`

## Outputs
- **Audit Report path**: `C:\Users\ADMIN\.gemini\antigravity\brain\9e05c9cd-c52d-4900-bfb1-3c02aa45407d\evidence_deep_audit_report.md`

## Verification Command
- Automated test command: `php artisan test` in `backend` directory.

## Constraints
- Do not modify or create source code files.
- All testing and file generation must be performed by subagents.
- Audit report must follow the 18-pass workflow structure specified in `RULE[strict-evidence-audit-v3-12k.md]`.
