# UI Sync and Gating Analysis Report

## 1. Scope and Target Files
This investigation covers:
* `backend/resources/js/Pages/Dashboard.tsx` (Dashboard view)
* `backend/resources/js/Pages/Lives/Index.tsx` (Livestreams index view)
* `backend/resources/js/Pages/Lives/Show.tsx` (Livestream monitoring view)
* Related controller, model, middleware, and styling configurations.

**Active Mode**: Core Mode and Critical Path Mode.

---

## 2. Comprehensive Findings

### 2.1 Hardcoded Values and Default Fallbacks
* **Subscription Credits Default**:
  In `backend/resources/js/Components/app-sidebar.tsx`, the maximum AI credit limit defaults to `1000` if the Inertia-provided subscription features are absent:
  ```typescript
  const limit = subscription?.features?.ai_credits ?? 1000
  ```
* **Subscription Package Name Default**:
  In `backend/app/Http/Middleware/HandleInertiaRequests.php`, the package name defaults to `'Free'` if the user has no active subscription:
  ```php
  'package_name' => $activeSub?->package?->name ?? 'Free',
  ```
* **Dashboard and Index Statistics**:
  There are no hardcoded/simulated KPIs or charts in `Dashboard.tsx` or `Lives/Index.tsx`. All metrics are derived from the database (via `DashboardController.php` and `LiveSessionController.php`). However, the Vietnamese text labels (e.g. "Tổng phiên Live", "Tổng bình luận") are static string literals.
* **Livestream Show Fallbacks**:
  In `Lives/Show.tsx`, the `StatsPanel` component uses a static fallback array if `statsHistory` is empty:
  ```typescript
  const activityData = statsHistory && statsHistory.length > 0 ? statsHistory : [
    { time: "Hiện tại", comments: stats.total_comments, viewers: stats.viewer_count },
  ]
  ```

### 2.2 Dead Buttons, Lack of Loaders, and Missing Toasts
* **Transient "Tạo đơn" (Create Order) Feature (No DB Persistence)**:
  In `Show.tsx` (lines 880, 905-909), the "Tạo đơn" button allows users to configure a mock order (quantity, notes, status). However, `saveOrder` only writes to a local React state variable `orders`:
  ```typescript
  const [orders, setOrders] = React.useState<Record<number, { status: string; note: string; qty: number }>>({})
  const saveOrder = () => {
    if (orderDialog.customerIdx === null) return
    setOrders((prev) => ({ ...prev, [orderDialog.customerIdx!]: { ...orderForm } }))
    setOrderDialog({ open: false, customerIdx: null })
  }
  ```
  No backend API is called and there is no database model or table for orders. Consequently, **all orders created on the UI are permanently lost when the page is reloaded**.
* **Transient "Ghim" (Pin) & "Order Mark" Buttons**:
  In `CommentsPanel` (`Show.tsx`, lines 420-433), clicking the pin or order status icon toggles a local React set (`pinnedIds`, `markedOrderIds`). These interactions are **purely client-side**; they are not saved in `live_events` or any other DB table, meaning they disappear on refresh.
* **Lacking Loading Indicators**:
  * **Ending a session**: The "Kết thúc phiên phân tích" button in `Show.tsx` sends a post request directly via `router.post(route('lives.stop', session.id))` but does not transition to a loading state.
  * **Deleting a session**: The "Xác nhận xóa" button in the deletion dialog on the Index page triggers `router.delete(route("lives.destroy", deletingSession.id))` but has no loading indicator.
* **Missing Toast Notifications**:
  * **Copying Leads**: In `Show.tsx` (lines 891-899), clicking "Copy tất cả" updates the text to "Đã copy" for 2 seconds but does not show a toast notification.
  * **Copying Phone Numbers**: Copying individual phone numbers displays a checkmark icon but lacks a toast notification.
  * **Saving Orders**: Clicking "Lưu đơn" in the create order dialog closes the dialog silently without a success toast.
  * **Stopping and Deleting Sessions**: No success toast is shown once Inertia requests complete.

### 2.3 Gating Alignment: Frontend vs Backend
* **Stream Creation Limits**:
  * **Backend**: In `LiveSessionController::store`, the controller checks the `limit_streams` subscription feature. If the number of active sessions (`connecting` or `live`) equals or exceeds the limit, it throws a validation error:
    ```php
    if ($activeSessionsCount >= $limitStreams) {
        throw ValidationException::withMessages([
            'tiktok_username' => ['Bạn đã đạt giới hạn số lượng livestream active tối đa của gói dịch vụ...'],
        ]);
    }
    ```
  * **Frontend**: In `Lives/Setup.tsx`, the frontend does **not** check the user's stream limit. There is no gating on the form or setup page; it allows input submission and relies entirely on the backend to throw the validation exception.
* **CSV Export & Copy Leads**:
  * **Frontend**: Gated via the `export_leads` feature:
    ```typescript
    const canExportLeads = auth?.subscription?.features?.export_leads ?? false
    ```
    If `canExportLeads` is false, clicking "Copy tất cả" or "Xuất CSV" triggers `showUpgradeDialog` (the subscription modal).
  * **Backend**: In `LiveSessionController::exportLeads`, the backend verifies `$features['export_leads']` and returns an unauthorized response (403 status) if missing. This is **correctly aligned**.
* **Audio Extraction / Audio Analysis**:
  * **Backend**: Conditioned in `AnalyzeCommentsJob.php` using `$features['audio_analysis']`. If active, it invokes the python audio capture snapshot; if not, it falls back to text-only comment analysis:
    ```php
    $audioAnalysisEnabled = $features['audio_analysis'] ?? false;
    if ($audioAnalysisEnabled && $session->tiktok_session_id) { ... }
    ```
  * **Frontend**: There is **no UI gating or visibility** for audio extraction/analysis. The feature is executed entirely in the background worker and cannot be enabled/disabled or monitored by users on the frontend pages.

### 2.4 Styling, Typography, and Animations
* **Typography**:
  The application is configured to use **Be Vietnam Pro** (`'Be Vietnam Pro', sans-serif`) as the main sans-serif font family. It is imported from `fonts.bunny.net` in `app.blade.php`:
  ```html
  <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:300,400,500,600,700,800&display=swap" rel="stylesheet" />
  ```
  It is mapped to `--font-sans` in `app.css`. No references to **Outfit** or **Inter** fonts exist in the workspace files.
* **Animations**:
  * **AI Scanning Animation**: Defined inline in `Show.tsx` for the comments that are not yet processed by AI (`ai_processed = false`):
    ```css
    @keyframes ai-scan {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    ```
    This creates an attractive moving indigo gradient scanline across comments during background jobs execution.
  * **Comment Entry**: Uses a slide-fade-in animation:
    ```css
    @keyframes slide-fade-in {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    ```
  * **Liveness Pulses**: Active stream indicator badges use `.animate-pulse` classes.
  * **Alert Progress Bar**: The order alerts use `@keyframes shrink` in `app.css` to dynamically deplete the alert indicator width over time.

---

## 3. Evidence Ledger

| File / Component | Area | Claim | Evidence Code Snippet / Key Line | Confidence |
| --- | --- | --- | --- | --- |
| `app-sidebar.tsx` | Fallback | Credit limit fallbacks to 1000 | `const limit = subscription?.features?.ai_credits ?? 1000` (Line 139) | High |
| `HandleInertiaRequests.php` | Fallback | Package name defaults to Free | `'package_name' => $activeSub?->package?->name ?? 'Free'` (Line 46) | High |
| `Show.tsx` | State | "Tạo đơn" button saves only to local transient state | `setOrders((prev) => ({ ...prev, [orderDialog.customerIdx!]: { ...orderForm } }))` (Line 907) | High |
| `Show.tsx` | State | Pin / Order Mark buttons save to local React state sets | `togglePin = (id) => setPinnedIds(...)` (Line 420-433) | High |
| `Setup.tsx` | Gating | Stream creation limit is not checked on frontend setup | Form submits to `lives.store` (Line 74) without subscription checks. | High |
| `Show.tsx` | Gating | Lead export is gated on frontend | `canExportLeads = auth?.subscription?.features?.export_leads ?? false` (Line 875) | High |
| `LiveSessionController.php` | Gating | Lead export is gated on backend | `if (!$features['export_leads']) { abort(403); }` in `exportLeads` | High |
| `AnalyzeCommentsJob.php` | Gating | Audio analysis is gated in background job | `$audioAnalysisEnabled = $features['audio_analysis']` (Line 147) | High |
| `app.css` | Styling | Font family is configured to Be Vietnam Pro | `--font-sans: 'Be Vietnam Pro', sans-serif;` (Line 14) | High |
| `app.blade.php` | Styling | Bunny fonts imports Be Vietnam Pro | `be-vietnam-pro:300,400,500,600,700,800` (Line 23) | High |
