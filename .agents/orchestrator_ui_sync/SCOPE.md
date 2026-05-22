# Scope: UI Sync and Audit Mission

## Architecture
- **Backend**: Laravel 11.x MVC
- **Frontend**: React 18 + Inertia.js + TailwindCSS + shadcn/ui

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Exploration & Audit | Run deep exploration on frontend page files (Pages in resources/js/Pages) to identify hardcoded text, dead buttons, typography inconsistencies, and gating misalignment. | None | DONE |
| 2 | Code Alignment & Gating | Eliminate all hardcoded subscription text, AI credits, and stream limits. Integrate auth.user/API values and enforce frontend gating. | M1 | DONE |
| 3 | UI/UX Flow & Style Polish | Fix dead buttons, add loaders, toasts, hover animations, Outfit/Inter typography. | M2 | DONE |
| 4 | Verification & Compilation | Validate npm run build and php artisan test. | M3 | DONE |
| 5 | Forensic Audit | Run forensic integrity checks. | M4 | DONE |

## Interface Gating Contracts
- **limit_streams**: Free: 1, Pro: 5, Enterprise: infinite (-1).
- **max_duration_hours**: Free: 1 hr, Pro: 4 hrs, Enterprise: 24 hrs.
- **ai_credits**: Free: 1,000, Pro: 50,000, Enterprise: 500,000.
- **audio_analysis**: Free: false, Pro: true, Enterprise: true.
- **export_leads**: Free: false, Pro: true, Enterprise: true.

## Frontend Pages to Audit
- User Pages:
  - `resources/js/Pages/Dashboard.tsx`
  - `resources/js/Pages/Lives/Index.tsx`
  - `resources/js/Pages/Lives/Show.tsx`
  - `resources/js/Pages/Subscription/Index.tsx`
- Admin Pages:
  - `resources/js/Pages/Admin/Packages/Index.tsx`
  - `resources/js/Pages/Admin/Payments/Index.tsx`
