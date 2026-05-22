# Plan for Resolving Victory Audit Rejections (orchestrator_ui_sync_2)

## Context
The Victory Audit rejected the previous attempt because:
1. Admin Dashboard statistics (Tổng doanh thu, revenueData growth chart, and recentUsers plan status) were mocked or simulated in `routes/web.php`.
2. Admin Users page index route did not eager load `activeSubscription.package` (potential N+1 query).
3. The Admin Users Index page (`Admin/Users/Index.tsx`) lacked the "Gói" column.

*Note: These changes appear to have been partially or fully implemented in the current code but need to be audited, optimized, verified, and compiled.*

## Milestones & Steps

### Milestone 1: Code Verification & N+1 Optimization
- Inspect `routes/web.php` for `admin.dashboard` and `admin.users.index` routes.
- Verify `User.php` `resolveActiveSubscription()` does not trigger N+1 query when eager loading is used.
- Add eager loading for `subscriptions` and `activeSubscription.package` to prevent N+1 queries.

### Milestone 2: Frontend Verification
- Verify `Admin/Users/Index.tsx` correctly displays the "Gói" column with dynamic badge styling.
- Verify `index.d.ts` is fully updated with type definitions.

### Milestone 3: Execution & Testing
- Spawn a Worker to perform N+1 query optimization on `User::resolveActiveSubscription()` and routes.
- Spawn a Reviewer to verify code changes, run `php artisan test`, and run `npm run build`.
- Spawn an Auditor to run forensic checks on the final implementation.

## Verification
- Run `php artisan test`
- Run `npm run build`

