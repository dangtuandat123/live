# Project: UX Refinement of Subscription Limits

## Architecture
- **Backend (Laravel)**:
  - `LiveSessionController` provides endpoints for live sessions including status, package limits, and AI credits.
  - Subscription details are retrieved and passed via Inertia properties or polling.
- **Frontend (React + Inertia)**:
  - `Show.tsx` is the live session dashboard. It polls events and checks session status and error messages.
  - Dialogs for duration limit and credit limit are presented to prompt package upgrades.
  - Locks and gated triggers exist on "Export CSV" and "Copy all" buttons.
  - Subscription status banner displays active plan, duration limits, and credits progress.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Exploration & Research | Analyze Show.tsx, check subscription properties passed, analyze error messages and polling structure | None | DONE |
| 2 | UI/UX Implementation | Implement Upgrade Duration/Credits Dialogs, Gated Features, and Subscription Status Banner. Ensure error_message sync. | M1 | DONE |
| 3 | Review & Verification | Run unit/feature tests, compile production assets (npm run build), review frontend code and run Forensic Auditor. | M2 | DONE |

## Code Layout
- **Frontend View**: `backend/resources/js/Pages/Lives/Show.tsx`
- **Backend Controller**: `backend/app/Http/Controllers/LiveSessionController.php`
- **Tests**: `backend/tests/Feature/SubscriptionGatingTest.php`

## Interface Contracts
- **Session Polling Response**:
  - `session` object needs to contain:
    - `status`: `'ended'`, `'error'`, etc.
    - `error_message`: string or null
- **Subscription Information**:
  - Details about the user's active subscription, such as package name (Free, Trial, Pro, Enterprise), max duration, used credits, and total limit must be available in Inertia page props or session details.
