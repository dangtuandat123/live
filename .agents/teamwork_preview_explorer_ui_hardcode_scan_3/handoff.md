# Handoff Report — UI Hardcode Scan & Transition Plan

## Project Coverage Report
- **Active Depth Mode**: Core Mode & PR/Diff Mode (auditing specific files and their dependencies).
- **Declared Scope**: `backend/resources/js/Pages/Lives/Index.tsx`, `Setup.tsx`, `Show.tsx`, and `Settings/Index.tsx` along with backend models `User.php`, `LiveSession.php`, controllers `SettingsController.php`, `LiveSessionController.php`, and migrations.
- **Full Files Read**:
  - `backend/resources/js/Pages/Lives/Index.tsx` (541 lines)
  - `backend/resources/js/Pages/Lives/Setup.tsx` (387 lines)
  - `backend/resources/js/Pages/Lives/Show.tsx` (lines 1-800, 1350-1410, 2600-2850 read in detail; rest scanned)
  - `backend/resources/js/Pages/Settings/Index.tsx` (503 lines)
  - `backend/app/Http/Controllers/SettingsController.php` (92 lines)
  - `backend/app/Http/Controllers/LiveSessionController.php` (lines 1-800, 920-970 read in detail; rest scanned)
  - `backend/app/Models/User.php` (157 lines)
  - `backend/app/Models/LiveSession.php` (99 lines)
  - `backend/database/migrations/2026_05_20_163406_add_settings_to_users_table.php` (23 lines)
  - `backend/database/migrations/2026_05_21_000004_create_live_events_table.php` (32 lines)
  - `backend/database/migrations/2026_05_21_000008_add_ai_fields_to_live_events.php` (35 lines)
- **Repo-wide Search Terms**: `localStorage`, `settings.tiktok.connect`, `tiktok_username`, `limit_streams`.
- **Inaccessible Areas**: None.

## Evidence Ledger
| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|
| Platforms | Platform `TikTok` is hardcoded in UI inputs and badges | `Setup.tsx` lines 201-208, `Show.tsx` line 2813 | Yes | Yes | None | High | None |
| TikTok Connection | Username connection starts empty rather than loading from user's `settings` | `Setup.tsx` line 67 | Yes | Yes | None | High | None |
| Stream Limits | Limits are validated on both frontend and backend | `Setup.tsx` lines 62-63, `LiveSessionController` lines 131-144 | Yes | Yes | None | High | None |
| LocalState | Comments pinning/marking/orders are saved in unsafe `localStorage` | `Show.tsx` lines 552-585, 1365-1379 | Yes | Yes | None | High | None |

---

## 1. Observation

### A. Hardcoded Platforms and Username Connections
- **`backend/resources/js/Pages/Lives/Setup.tsx`**:
  - The platform is hardcoded to "TikTok" inside a disabled input field:
    ```typescript
    203:                                 <Input
    204:                                     value="TikTok"
    205:                                     disabled
    206:                                     className="bg-muted"
    207:                                 />
    ```
  - The label for the input is hardcoded to TikTok:
    ```typescript
    210:                                 <Label htmlFor="live-url">
    211:                                     Username TikTok
    212:                                 </Label>
    ```
  - The initial value of `tiktok_username` inside the form starts empty:
    ```typescript
    65:     const form = useForm({
    66:         name: '',
    67:         tiktok_username: '',
    ```
- **`backend/resources/js/Pages/Lives/Show.tsx`**:
  - The platform badge is hardcoded to "TikTok":
    ```typescript
    2813:                                     <Badge variant="default">TikTok</Badge>
    ```
  - Profile link construction is hardcoded to TikTok URLs:
    ```typescript
    871:                                                 ? `https://www.tiktok.com/@${comment.unique_id}`
    ```
- **`backend/resources/js/Pages/Settings/Index.tsx`**:
  - The `platforms` list only displays a hardcoded TikTok connection block:
    ```typescript
    124:     const platforms = [
    125:         { name: 'TikTok', connected: isConnected, account: tiktokUsername || 'Chưa kết nối' },
    126:     ];
    ```

### B. Livestream Limit Validation against `limit_streams`
- **`backend/resources/js/Pages/Lives/Setup.tsx`**:
  - Active stream limits are fetched from the Inertia subscription features prop:
    ```typescript
    62:     const limitStreams = auth?.subscription?.features?.limit_streams ?? 1;
    63:     const isGated = limitStreams !== -1 && active_streams_count >= limitStreams;
    ```
  - The submit button is disabled if the limit is reached, and a red alert banner is displayed.
- **`backend/app/Http/Controllers/LiveSessionController.php`**:
  - The backend enforces the stream limit securely on submission:
    ```php
    134:         if ($limitStreams !== -1) {
    135:             $activeSessionsCount = LiveSession::forUser($user->id)
    136:                 ->whereIn('status', ['connecting', 'live'])
    137:                 ->count();
    138: 
    139:             if ($activeSessionsCount >= $limitStreams) {
    140:                 throw ValidationException::withMessages([
    141:                     'tiktok_username' => ['Bạn đã đạt giới hạn số lượng livestream active tối đa của gói dịch vụ ('.$limitStreams.').'],
    142:                 ]);
    143:             }
    144:         }
    ```

### C. Hardcoded State Persistence (`localStorage` Keys)
- **`backend/resources/js/Pages/Lives/Show.tsx`**:
  - Pinning comment IDs:
    ```typescript
    552:     const pinnedKey = `pinned_${session.id}`;
    ...
    576:         localStorage.setItem(pinnedKey, JSON.stringify(Array.from(pinnedIds)));
    ```
  - Marking order comment IDs:
    ```typescript
    553:     const markedKey = `marked_${session.id}`;
    ...
    580:         localStorage.setItem(
    581:             markedKey,
    582:             JSON.stringify(Array.from(markedOrderIds)),
    583:         );
    ```
  - Order metadata (qty, note, status) is stored against the customer's array index (`customerIdx`):
    ```typescript
    1365:     const ordersKey = `orders_${session.id}`;
    1366:     const [orders, setOrders] = React.useState<
    1367:         Record<number, { status: string; note: string; qty: number }>
    1368:     >(() => { ... });
    ...
    1378:         localStorage.setItem(ordersKey, JSON.stringify(orders));
    ```
  - Sound preference is hardcoded to `true` on load and resets on refresh:
    ```typescript
    2592:     const [soundEnabled, setSoundEnabled] = React.useState(true);
    ```

---

## 2. Logic Chain

1. **Unsafe Persistence (Local Storage Mismatch)**:
   - Comment pins, marked orders, and order modifications (notes, quantity, status) are stored in `localStorage` scoped to the current browser/device.
   - *Consequence*: If an administrator edits an order status or pins a comment, another administrator/streamer accessing the same session from a different PC/browser will not see these changes.
   - *Fragile Keying*: Storing order metadata keyed by customer list index (`Record<number, ...>`) is highly dangerous because whenever a new potential customer is detected, the array indexes shift, immediately scrambling/assigning order statuses to the wrong customers.
   - *Correction*: The state must be stored in the database, with records keyed by the unique `live_events.id`.

2. **Unconnected TikTok Connection Form**:
   - Although the system allows connecting a TikTok account inside `Settings/Index.tsx` (storing it inside the `settings` JSON column in the `users` table), the setup form in `Lives/Setup.tsx` starts empty.
   - *Correction*: The form should prefill `tiktok_username` using `auth.user.settings.tiktok_username`.

3. **Inflexible Platforms Schema**:
   - `users.settings.tiktok_username` is a top-level key. If the system expands to Facebook, YouTube, or Shopee, it will create messy schema columns/keys.
   - *Correction*: Restructure the settings JSON to hold a nested `connections` block.

---

## 3. Caveats

- We assumed that `auth.user` passed through Inertia contains the raw settings from the database. If settings are not pre-populated in the session, we will need to merge defaults backend-side.
- Storing order modifications directly on the `live_events` table (or via a related table) might require additional write latency, which should be mitigated using quick database transactions.

---

## 4. Conclusion

The application is transitioning to a dynamic system, but key UI parameters are still hardcoded or improperly synced via local storage. 

To resolve these issues, we recommend the following transition plan:

### Step 1: Restructure Connection settings in `users` Table
Update `settings` structure inside the `User` model to use a unified `connections` object:
```json
{
  "ai_language": "vi",
  "auto_extract_phone": true,
  "auto_extract_address": true,
  "realtime_alerts": true,
  "connections": {
    "tiktok": {
      "username": "@my_tiktok_channel",
      "connected_at": "2026-05-22T06:50:00Z"
    },
    "facebook": null
  }
}
```
Define a computed attribute in `app/Models/User.php` for backward-compatible rendering:
```php
public function getConnectedPlatformsAttribute(): array
{
    $settings = $this->settings ?? [];
    $connections = $settings['connections'] ?? [];
    if (empty($connections) && isset($settings['tiktok_username'])) {
        $connections['tiktok'] = [
            'username' => $settings['tiktok_username'],
            'connected_at' => null,
        ];
    }
    return $connections;
}
```

### Step 2: Prefill `Lives/Setup.tsx`
Update `Lives/Setup.tsx` to read from the user connection settings and support dynamic platform options:
```typescript
    const form = useForm({
        name: '',
        tiktok_username: auth?.user?.settings?.tiktok_username?.replace(/^@/, '') ?? '',
        product_ids: [] as number[],
        keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[],
    });
```

### Step 3: Database Schema Migration for pins, marks, and orders
We propose a migration to add columns directly to the `live_events` table (or create a related `live_event_orders` table):
```php
Schema::table('live_events', function (Blueprint $table) {
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_marked')->default(false);
    $table->string('order_status', 20)->default('pending'); // pending, confirmed, cancelled
    $table->text('order_note')->nullable();
    $table->integer('order_qty')->default(1);
});
```

### Step 4: Propose Clean Backend API Endpoints
We propose the following API routes in `routes/web.php` (under `auth` middleware):
```php
Route::prefix('/lives/{liveSession}/events/{liveEvent}')->group(function () {
    Route::post('/pin', [LiveSessionController::class, 'togglePin'])->name('lives.events.pin');
    Route::post('/mark', [LiveSessionController::class, 'toggleMark'])->name('lives.events.mark');
    Route::post('/order', [LiveSessionController::class, 'updateOrder'])->name('lives.events.order');
});
```

Controller implementation:
```php
public function togglePin(Request $request, LiveSession $liveSession, LiveEvent $liveEvent)
{
    if ($liveSession->user_id !== $request->user()->id || $liveEvent->live_session_id !== $liveSession->id) {
        abort(403);
    }
    $liveEvent->update(['is_pinned' => !$liveEvent->is_pinned]);
    return back();
}

public function toggleMark(Request $request, LiveSession $liveSession, LiveEvent $liveEvent)
{
    if ($liveSession->user_id !== $request->user()->id || $liveEvent->live_session_id !== $liveSession->id) {
        abort(403);
    }
    $liveEvent->update(['is_marked' => !$liveEvent->is_marked]);
    return back();
}

public function updateOrder(Request $request, LiveSession $liveSession, LiveEvent $liveEvent)
{
    if ($liveSession->user_id !== $request->user()->id || $liveEvent->live_session_id !== $liveSession->id) {
        abort(403);
    }
    $validated = $request->validate([
        'status' => ['required', 'string', 'in:pending,confirmed,cancelled'],
        'note' => ['nullable', 'string'],
        'qty' => ['required', 'integer', 'min:1'],
    ]);

    $liveEvent->update([
        'order_status' => $validated['status'],
        'order_note' => $validated['note'],
        'order_qty' => $validated['qty'],
    ]);

    return back();
}
```

This ensures full real-time database synchronization and eliminates local storage reliance.

---

## 5. Verification Method

To verify these changes after implementation:
1. Run the backend PHP test suite to ensure no regressions in session setup or settings updates:
   ```powershell
   php artisan test
   ```
2. Manually check that the Inertia page setup loads the user settings by checking the shared props in browser Vue/React DevTools (`$page.props.auth.user.settings`).
3. Verify connection settings save successfully by hitting the endpoints and asserting DB content.
