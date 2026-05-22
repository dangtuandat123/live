# Project: LiveStream Comment Analysis Sync & Alignment (Phase 2)

## Architecture
- **Backend (Laravel)**:
  - `LiveSessionController` manages Inertia rendering for dashboard, polling updates (`fetchEvents`), updating events (`updateEvent`), and ingesting TikTok live comments (`fetchAndStoreEvents`).
  - `AnalyzeCommentsJob` processes comments in background batches via AI and updates event tags, status, and stats.
  - Caching optimizes active/ended session querying.
- **Frontend (React + Inertia)**:
  - `Show.tsx` renders the livestream dashboard, including the conversion funnel chart, quick stats, customer lists, sentiment distributions, and top keywords.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Alignment Exploration | Analyze codebase for funnel, labeling, cache, redundancy, and phone extraction sync | none | DONE |
| 2 | Implementation | Update LiveSessionController, AnalyzeCommentsJob, and Show.tsx to sync logic and UI | M1 | DONE |
| 3 | Verification & Review | Run php artisan test, npm run build, and run Forensic Auditor check | M2 | DONE |

## Code Layout
- **Backend Controller**: `backend/app/Http/Controllers/LiveSessionController.php`
- **Backend Job**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Frontend Page**: `backend/resources/js/Pages/Lives/Show.tsx`

## Interface Contracts
### LiveSessionController ↔ Show.tsx
- **Page Prop updates**:
  - Remove `'keywords'` from Inertia page props.
  - Add `'potentialCustomersCount'` (int) representing total unique potential customers.
  - Add `'topKeywords'` (array of `{"keyword": string, "count": number}`) representing frequency counts of session configured keywords.
- **Polling Endpoint (`/lives/{liveSession}/events`)**:
  - Returns JSON containing:
    - `potentialCustomersCount` (int)
    - `topKeywords` (array of `{"keyword": string, "count": number}`)
    - and existing properties.
- **Cache Invalidation**:
  - `updateEvent()` clears caches for `potential_customers`, `top_products`, `top_questions`, `stats_history`, and `potential_customers_count`.
  - `AnalyzeCommentsJob` clears these same caches on completion.
