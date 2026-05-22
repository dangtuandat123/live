# Project Plan: UX/UI Refinement of Subscription Limits

## Architecture & Context
This project focuses on enhancing the UX/UI of the Subscription limits across the livestream system:
- **Frontend**: React (Inertia.js), TypeScript, Tailwind CSS, Shadcn UI components.
- **Backend**: Laravel, providing session states, subscription details, and API endpoints.

## Milestones

| # | Milestone Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Exploration & Analysis | Audit target files, identify models, props, and existing components. | None | DONE |
| 2 | R1: Low Time Warning & Badge | Show.tsx warning banner, UpgradeDurationDialog update, Index/Dashboard badges. | M1 | DONE |
| 3 | R2: Low Credits Warning | Show.tsx credit alert, app-sidebar progress bar highlight. | M1 | DONE |
| 4 | R3: Setup Limits Card & Gating | Setup.tsx package info card, gating stream creation when limit is reached. | M1 | DONE |
| 5 | R4: Audio Analysis Gating UI | Audio Analysis indicator, Lock icon, upgrade Dialog when clicked on Show.tsx. | M1 | DONE |
| 6 | Verification & Forensic Audit | Run backend tests and frontend builds, perform forensic audit checks. | M2, M3, M4, M5 | DONE |

## Detailed File Changes Planned
1. **backend/resources/js/Components/ui/progress.tsx**:
   - Add `indicatorClassName?: string` to `ProgressProps` to allow coloring progress indicator dynamically.
2. **backend/resources/js/Components/app-sidebar.tsx**:
   - Under 10% remaining (used >= 90%), color progress bar red (`bg-destructive`) and badge red.
   - Under 20% remaining (used >= 80%), color progress bar amber (`bg-amber-500`) and badge amber.
3. **backend/resources/js/Pages/Lives/Setup.tsx**:
   - Add responsive card showing subscription limits: concurrent streams (used/limit), max duration, AI credits, and premium status for audio analysis / export leads.
   - Disable submit button and add "Nâng cấp gói ngay" link pointing to `/subscription` and link back to list of live sessions when active streams reach the limit.
4. **backend/app/Http/Controllers/LiveSessionController.php** and **backend/app/Http/Controllers/DashboardController.php**:
   - Include `error_message` in the list query responses so frontend knows if the session was stopped due to limit.
5. **backend/resources/js/Pages/Lives/Index.tsx** and **backend/resources/js/Pages/Dashboard.tsx**:
   - Display Badge "Bị ngắt (Hết giờ)" when session is ended and `error_message` mentions duration limit.
   - Display Badge "Đạt giới hạn" when session status is error and `error_message` mentions credit limit.
6. **backend/resources/js/Pages/Lives/Show.tsx**:
   - Display amber banner when session is active/connecting and run-time is >=85% of limit or <10 mins left.
   - Display amber banner when AI credit used >= 90% of limit (when limit != -1).
   - Display Audio Analysis UI with a lock icon and "Bản nâng cấp" badge if missing `audio_analysis` feature, opening Upgrade Dialog when clicked.
   - Hoist/consolidate `UpgradeDialog` in `LivesShow` to be triggered via `LiveContext` by other components (e.g. `CustomersPanel`).
   - Add DB storage explanation inside `UpgradeDurationDialog`.

