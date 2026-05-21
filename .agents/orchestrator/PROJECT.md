# Project: TikTok Livestream Comment Analysis Pipeline Deep Audit

## Architecture
- **Backend Framework**: Laravel
- **Target Component**: Comment analysis job (`AnalyzeCommentsJob`) using Text, Audio, and Memory (LiveSession context).
- **Database Schema**: Migrations adding AI context summary to live sessions.
- **Tests**: Feature tests in `backend/tests/Feature/AnalyzeCommentsJobTest.php`.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Exploration & Reading | Explorer agent reads the target files and drafts the expected behavior contract | none | DONE (ID: 90a7442b-785d-47e3-b669-d2dd58f3eb4f) |
| 2 | Evidence Audit Pass 1-18 | Worker/Explorer drafts the 18-pass matrices (UX, action, copy, API abuse, invariants, etc.) | M1 | DONE (ID: ceb621f4-d0ce-4f75-8004-5fb56b46b242) |
| 3 | Automated Testing | Worker runs `php artisan test` in backend directory and verifies all pass | M2 | DONE (ID: ceb621f4-d0ce-4f75-8004-5fb56b46b242) |
| 4 | Audit Report Generation | Worker writes the final report to the specified output path | M3 | DONE (ID: 341d9ab0-0653-43e1-8cf6-d9b48bbe7998) |
| 5 | Peer Review & Audit Gate | Reviewer reviews report and Forensic Auditor runs integrity checks | M4 | IN_PROGRESS (ID: 558d1224-fa93-4133-a37b-23f2502dec29) |

## Interface Contracts
- **LiveSession Schema**: Added columns for context summary, audio state, etc.
- **AnalyzeCommentsJob**: Receives a `LiveSession` and runs comments analysis logic.
