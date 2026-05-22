# Handoff Report: UI Hardcode & Static Data Scan

This report details the findings of a scan conducted on React files in `backend/resources/js/Pages/` and related components to identify hardcoded stats, mock data, or static UI labels that should be dynamic, mapping them to their backend data sources or proposing appropriate changes.

## 1. Observation

### Scan of `backend/resources/js/Pages/Dashboard.tsx`
- **KPI Cards (`stats` prop)**:
  - Component is mapped to `stats` prop received via Inertia.
  - File: `backend/app/Http/Controllers/DashboardController.php` (lines 98-123):
    ```php
    $stats = [
        [
            'title' => 'Tổng phiên Live',
            'value' => (string) $totalSessionsCount,
            'change' => "+{$sessionsThisWeekCount} tuần này",
            'trend' => 'up', // Hardcoded trend direction
        ],
        // ...
    ];
    ```
    - The `"Tổng phiên Live"` KPI card has a static `'trend' => 'up'` hardcoded in the controller.

### Scan of `backend/resources/js/Pages/Admin/Dashboard.tsx`
- **KPI Cards (`stats` prop)**:
  - File: `backend/routes/web.php` (lines 131-161):
    ```php
    'stats' => [
        [
            'title' => 'Tổng người dùng',
            'value' => number_format($totalUsers),
            'change' => '+'.User::where('created_at', '>=', now()->startOfMonth())->count().' tháng này',
            'trend' => 'up',
        ],
        [
            'title' => 'Tổng phiên Live',
            'value' => number_format($totalSessions),
            'change' => '+'.LiveSession::where('created_at', '>=', now()->startOfMonth())->count().' tháng này',
            'trend' => 'up',
        ],
        [
            'title' => 'Tổng doanh thu',
            'value' => number_format($totalRevenueVal) . 'đ',
            'change' => '+15% so với tháng trước',
            'trend' => 'up',
        ],
        [
            'title' => 'User hoạt động (7d)',
            'value' => number_format($activeUsers),
            'change' => 'Dựa trên phiên live gần đây',
            'trend' => 'up',
        ],
    ],
    ```
    - **All trend indicators** (`trend => 'up'`) are statically hardcoded.
    - **Total Revenue percentage change** (`change => '+15% so với tháng trước'`) is hardcoded.
    - **Active User change description** (`change => 'Dựa trên phiên live gần đây'`) is a static string instead of showing percentage/trend change.

### Scan of `backend/resources/js/Pages/Reports/Index.tsx`
- **Period Filter Metrics**:
  - Mapped to the `stats` prop computed by `ReportController.php@index`.
  - File: `backend/app/Http/Controllers/ReportController.php` (lines 62-113):
    - Values for views, comments, likes, gifts, follows, shares, viewer peak, leads count, and sentiment percentages are successfully calculated dynamically.
    - However, the trend calculations for comparison stats use a simplified time-delta query that assumes default date ranges (`7d`, `30d`, `90d`) and does not natively sync dynamic custom-date ranges (if passed) for trend calculations.
- **AI Recommendations Section**:
  - File: `backend/resources/js/Pages/Reports/Index.tsx` (lines 538-608):
    - Mapped to the `recommendations` prop.
  - File: `backend/app/Http/Controllers/ReportController.php` (lines 182-205):
    - The backend returns dynamic recommendations successfully via $recommendations.

### Scan of Global Components (`app-sidebar.tsx` & `nav-user.tsx`)
- **AI Credits UI**:
  - File: `backend/resources/js/Components/app-sidebar.tsx` (lines 187-203):
    ```typescript
    const { auth, activeSubscription, used_ai_credits } = usePage<any>().props;
    ```
    - Mapped to global props from `HandleInertiaRequests.php`.
  - File: `backend/app/Http/Middleware/HandleInertiaRequests.php` (lines 62-67):
    - Dynamic values for `activeSubscription` and `used_ai_credits` are populated properly from user's current session database values.
- **Support Contact / Quick Actions**:
  - Hardcoded Facebook Messenger, Zalo, or Support Email coordinates exist in UI static configurations in layouts/components but are not dynamically served from backend config files/settings.

---

## 2. Logic Chain

1. **User Dashboard KPI Trend**:
   - In `DashboardController.php`, the `'trend'` value for `"Tổng phiên Live"` is hardcoded as `'up'`.
   - **Reasoning**: This can be calculated dynamically by comparing the current week's session count (`$sessionsThisWeekCount`) against the previous week's session count (`$sessionsPrevWeekCount = count($sessionsPrevWeek)`). If `$sessionsThisWeekCount >= $sessionsPrevWeekCount`, trend is `'up'`; otherwise, `'down'`.

2. **Admin Dashboard KPI Trends**:
   - In `routes/web.php` (`admin.dashboard` route closure), the `stats` array contains entirely hardcoded trend flags and static growth rates.
   - **Reasoning**:
     - `Tổng người dùng` change & trend: Calculate users registered this month vs last month.
     - `Tổng phiên Live` change & trend: Calculate live sessions this month vs last month.
     - `Tổng doanh thu` change & trend: Calculate total subscription payments received this month vs last month.
     - `User hoạt động (7d)` change & trend: Calculate active users this week vs previous week.

---

## 3. Caveats
- No code changes have been performed as this is a **read-only investigation** as mandated by the project instructions.
- We assumed that database tables `users`, `live_sessions`, `live_stats`, and `payments` are populated with valid timestamps `created_at` to perform proper chronological period comparisons.

---

## 4. Conclusion
To transition all static and hardcoded indicators to fully dynamic values, we propose the following backend enhancements:

### Proposal 1: Make User Dashboard KPI Trend Dynamic
**File**: `backend/app/Http/Controllers/DashboardController.php`

**Before (Lines 100-104)**:
```php
[
    'title' => 'Tổng phiên Live',
    'value' => (string) $totalSessionsCount,
    'change' => "+{$sessionsThisWeekCount} tuần này",
    'trend' => 'up',
],
```

**Proposed After**:
```php
$sessionsPrevWeekCount = count($sessionsPrevWeek);
$sessionsTrend = $sessionsThisWeekCount >= $sessionsPrevWeekCount ? 'up' : 'down';

// In $stats definition:
[
    'title' => 'Tổng phiên Live',
    'value' => (string) $totalSessionsCount,
    'change' => "+{$sessionsThisWeekCount} tuần này",
    'trend' => $sessionsTrend,
],
```

### Proposal 2: Make Admin Dashboard KPI Stats Dynamic
**File**: `backend/routes/web.php` (inside `admin/dashboard` route)

**Proposed Implementation**:
```php
// 1. Calculate User Growth (this month vs last month)
$usersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();
$usersLastMonth = User::where('created_at', '>=', now()->subMonth()->startOfMonth())
    ->where('created_at', '<', now()->startOfMonth())
    ->count();
$userTrend = $usersThisMonth >= $usersLastMonth ? 'up' : 'down';

// 2. Calculate Session Growth (this month vs last month)
$sessionsThisMonth = LiveSession::where('created_at', '>=', now()->startOfMonth())->count();
$sessionsLastMonth = LiveSession::where('created_at', '>=', now()->subMonth()->startOfMonth())
    ->where('created_at', '<', now()->startOfMonth())
    ->count();
$sessionTrend = $sessionsThisMonth >= $sessionsLastMonth ? 'up' : 'down';

// 3. Calculate Revenue Growth (this month vs last month)
// Let's assume total revenue calculation from payments table is performed
$revenueThisMonth = \DB::table('payments')
    ->where('status', 'success') // or relevant paid state
    ->where('created_at', '>=', now()->startOfMonth())
    ->sum('amount');
$revenueLastMonth = \DB::table('payments')
    ->where('status', 'success')
    ->where('created_at', '>=', now()->subMonth()->startOfMonth())
    ->where('created_at', '<', now()->startOfMonth())
    ->sum('amount');
$revenueDiffPercent = 0;
if ($revenueLastMonth > 0) {
    $revenueDiffPercent = (int) round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100);
} else {
    $revenueDiffPercent = $revenueThisMonth > 0 ? 100 : 0;
}
$revenueTrend = $revenueDiffPercent >= 0 ? 'up' : 'down';
$revenueChangeStr = ($revenueDiffPercent >= 0 ? '+' : '') . $revenueDiffPercent . '% so với tháng trước';

// 4. Calculate Active User Trend (7d vs previous 7d)
$activeUsersThisWeek = LiveSession::where('created_at', '>=', now()->subDays(7))
    ->distinct('user_id')
    ->count('user_id');
$activeUsersPrevWeek = LiveSession::where('created_at', '>=', now()->subDays(14))
    ->where('created_at', '<', now()->subDays(7))
    ->distinct('user_id')
    ->count('user_id');
$activeUsersTrend = $activeUsersThisWeek >= $activeUsersPrevWeek ? 'up' : 'down';
$activeDiffPercent = 0;
if ($activeUsersPrevWeek > 0) {
    $activeDiffPercent = (int) round((($activeUsersThisWeek - $activeUsersPrevWeek) / $activeUsersPrevWeek) * 100);
} else {
    $activeDiffPercent = $activeUsersThisWeek > 0 ? 100 : 0;
}
$activeChangeStr = ($activeDiffPercent >= 0 ? '+' : '') . $activeDiffPercent . '% so với tuần trước';
```

---

## 5. Verification Method

To verify the dynamic data binding:
1. **Database Seeding**: Seed test sessions/users across multiple weeks:
   - For User Dashboard: Insert 5 live sessions in the current week, and 10 in the previous week. Check that the trend for "Tổng phiên Live" switches to `down`.
   - For Admin Dashboard: Seed payments with varying amounts between this month and last month. Verify that the total revenue percent changes and trend flags match the actual ratios.
2. **Component Inspection**: Check that React components dynamically render UI classes based on `trend` values:
   - Verify `Dashboard.tsx` correctly applies green styles/icons for `up` trend and red styles/icons for `down` trend.
