# Project: Dynamic UI Sync

## Architecture
- React Inertia.js frontend in `backend/resources/js/Pages`
- Laravel Backend in `backend/app`
- Shared data middleware: `backend/app/Http/Middleware/HandleInertiaRequests.php`
- Key controllers/routes:
  - `SubscriptionController` / `Checkout` API
  - `DashboardController`
  - `ReportController`
  - `LiveSessionController`

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | UI Hardcode Scan | Scan React Pages and identify hardcoded/static values and their sources in Backend | none | DONE |
| 2 | Backend & Middleware Dynamic Props | Update Laravel Controllers and HandleInertiaRequests to fetch and share the necessary dynamic data | M1 | DONE |
| 3 | Frontend Integration & Cleanup | Update React Pages to use dynamic props and handle fallbacks correctly | M2 | DONE |
| 4 | Testing & Verification | Run tests and npm run build to verify correctness and integrity | M3 | DONE |

## Interface Contracts
- Standard Inertia page props and `auth` shared data structure.

## Milestone 1 Outputs (Key Findings)
1. **Bank Beneficiary Details**:
   - `Subscription/Index.tsx` and `SubscriptionController.php` contain hardcoded fallbacks ('MB Bank', 'DANG TUAN DAT', '11183041').
   - Solution: Remove hardcoded fallbacks and enforce dynamic retrieval from `PaymentConfig` active configuration. Throw 503 if config is incomplete.
2. **Dynamic Features List**:
   - `Subscription/Index.tsx` hardcodes feature checking.
   - Solution: Expose dynamic `features_list` array from packages and map it directly in React.
3. **KPI Trend and Growth Stats**:
   - `DashboardController.php` and `routes/web.php` hardcode trend arrows and growth percentages.
   - Solution: Compute user registrations, session counts, and payments this month/week vs last month/week dynamically in the backend.
4. **TikTok Connections and Platform Connection**:
   - `Lives/Setup.tsx` hardcodes value="TikTok" and does not prefill `tiktok_username`.
   - `Settings/Index.tsx` hardcodes TikTok block.
   - Solution: Restructure user connection settings, prefill setup form from `auth.user.settings.tiktok_username`.
5. **State Persistence (localStorage)**:
   - Comment pinning, marking, and orders metadata are saved via `localStorage` keyed by `customerIdx` which is fragile.
   - Solution: Create migration for `live_events` table (`is_pinned`, `is_marked`, `order_status`, `order_note`, `order_qty`) and implement backend endpoints (`/lives/{liveSession}/events/{liveEvent}/pin`, `mark`, `order`) to save to database.

