# BRIEFING — 2026-05-22T06:51:30Z

## Mission
Scan React pages for hardcoded settings, analyze users table settings and stream limits, and propose APIs/persistences.

## 🔒 My Identity
- Archetype: explorer
- Roles: explorer, auditor, static-analyst
- Working directory: d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_hardcode_scan_3
- Original parent: dc3d3191-596d-4364-ab79-83c5438a4dd9
- Milestone: UI hardcode scanning and dynamic transition proposal

## 🔒 Key Constraints
- Read-only investigation — do NOT implement
- Analyze React files for hardcoded values and propose clean routes/APIs and state persistence (localStorage)
- Review TikTok connection storage in the settings column of the users table
- Review livestream limits validation in Lives/Setup.tsx against limit_streams
- Must produce detailed handoff report in handoff.md under working directory

## Current Parent
- Conversation ID: dc3d3191-596d-4364-ab79-83c5438a4dd9
- Updated: not yet

## Investigation State
- **Explored paths**:
  - `backend/resources/js/Pages/Lives/Index.tsx`
  - `backend/resources/js/Pages/Lives/Setup.tsx`
  - `backend/resources/js/Pages/Lives/Show.tsx`
  - `backend/resources/js/Pages/Settings/Index.tsx`
  - `backend/app/Http/Controllers/SettingsController.php`
  - `backend/app/Http/Controllers/LiveSessionController.php`
  - `backend/app/Models/User.php`
  - `backend/app/Models/LiveSession.php`
  - `backend/database/migrations/` (settings, live_events, etc.)
- **Key findings**:
  - Platform `TikTok` is hardcoded in UI inputs and badges (Index, Setup, Show).
  - TikTok account connection stored in `users.settings.tiktok_username` top-level key.
  - Active streams limit (`limit_streams`) is validated both on frontend and backend.
  - Comments pinning, marking, and orders metadata are saved locally in browser `localStorage`.
  - Storing orders by customer list array index is extremely fragile and leads to wrong mappings when comments lists update dynamically.
- **Unexplored areas**: None.

## Key Decisions Made
- Propose database schema changes (new columns/metadata in `live_events`) to replace `localStorage` keys.
- Propose schema change for `users.settings` from top-level `tiktok_username` to a nested `connections` block for multiple platforms.
- Propose clean backend API endpoints for comments pin, mark, and orders metadata.

## Artifact Index
- d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_hardcode_scan_3\handoff.md — Handoff report containing findings, code snippets, proposals, and verification.
