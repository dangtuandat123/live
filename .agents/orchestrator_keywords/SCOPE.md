# Scope: AI Auto-Discovery Keywords

## Architecture
- **Backend (Laravel)**:
  - `LiveSessionController`: Controls Inertia endpoints (`Show.tsx`, `Setup.tsx`), removes manual keyword input, counts keywords dynamically using SQL LIKE.
  - `AnalyzeCommentsJob`: Prompt update, auto-extracts ~5 keywords from comments, standardizes them, saves to `live_session_keywords` table.
- **Frontend (React + Inertia)**:
  - `Setup.tsx`: Remove "Từ khóa theo dõi" config input.
  - `Show.tsx`: Render extracted top keywords.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Analysis | Read Setup.tsx, LiveSessionController, AnalyzeCommentsJob, and Show.tsx. | none | DONE |
| 2 | Implementation | Worker implements R1, R2, R3 changes. | M1 | DONE (Conv: 6aa45d98-0820-4713-bdb1-1208b167ece7) |
| 3 | Verification & Review | Reviewer verifies changes, runs tests and builds. | M2 | DONE (Conv 1: 549866c9-9c71-4d6e-8703-c6b7c5bb45a2, Conv 2: 63627c08-6eb8-46c3-a4be-0f15fb135ada) |
| 4 | Audit | Forensic Auditor runs checks and verifies integrity. | M3 | DONE (Conv: b2836c67-bbc9-4bee-8d81-5db0f8f23cb8) |

## Interface Contracts
### LiveSessionController ↔ Show.tsx / Setup.tsx
- `Setup.tsx` payload doesn't include manual keywords.
- `LiveSessionController` no longer validates or saves manual keywords.
- `AnalyzeCommentsJob` returns `extracted_keywords` in its LLM response, standardizes them, and persists them to `live_session_keywords` table with a limit of 30 keywords per session.
- `LiveSessionController::getTopKeywords` dynamically counts frequencies of these keywords using SQL `LIKE` query over comments.
