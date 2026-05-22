# BRIEFING — 2026-05-22T07:43:00Z

## Mission
Analyze the codebase for 5 follow-up requirements regarding conversion funnel, labeling alignment, cache invalidation, redundancy, and phone extraction Regex vs AI sync.

## 🔒 My Identity
- Archetype: explorer
- Roles: Teamwork explorer
- Working directory: d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_sync
- Original parent: 021e1ff8-5b4d-44d3-ad91-827b5dd4ebf5
- Milestone: Analysis of follow-up requirements completed.

## 🔒 Key Constraints
- Read-only investigation — do NOT implement.
- Code-only network mode.
- Focus on the specified target files and requirements.

## Current Parent
- Conversation ID: 021e1ff8-5b4d-44d3-ad91-827b5dd4ebf5
- Updated: 2026-05-22T07:43:00Z

## Investigation State
- **Explored paths**:
  - `backend/app/Http/Controllers/LiveSessionController.php`
  - `backend/app/Jobs/AnalyzeCommentsJob.php`
  - `backend/app/Models/LiveSession.php`
  - `backend/resources/js/Pages/Lives/Show.tsx`
  - `backend/app/Http/Controllers/DashboardController.php`
- **Key findings**:
  - R1: Funnel distortion is due to Stage 3 calling a 50-limit `getPotentialCustomers` list while Stage 4 `leads_count` is uncapped.
  - R2: Labeling alignment issues where `leads_count` has conflicting names and Stage 3 has mixed semantic definitions.
  - R3: Missing cache invalidation in `updateEvent` and at the end of `AnalyzeCommentsJob`.
  - R4: Duplicated sentiment cards, unused `session.keywords` Inertia prop, and misleading "Từ khóa được nhắc nhiều" card combining questions and products.
  - R5: Background AI analysis bulk updates overwrite Regex-captured `has_phone` value back to `false` when AI misses it.
- **Unexplored areas**:
  - None. Full coverage achieved within scope.

## Key Decisions Made
- Suggested using `distinct('tiktok_user_id')->count()` query for uncapped potential customers count.
- Structured consistent renaming strategy to align labels across quick stats, funnel, and tabs.
- Recommended precise Cache key invalidation in `updateEvent` and `AnalyzeCommentsJob`.
- Outlined a custom query tracking configured keywords frequency to replace combined question/product tags.
- Designed in-memory check of `$event->has_phone` prior to AI result update to protect regex matches.

## Artifact Index
- d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_sync\analysis.md — Detailed analysis report for the 5 requirements.
- d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_sync\handoff.md — Self-contained handoff report.
