# Handoff Report — Subscription UX & Gating Analysis

## 1. Observation

### A. Polling and `error_message` State Resolution
In `backend/resources/js/Pages/Lives/Show.tsx`, the polling loop is configured within a `useEffect` hook (lines 2990-3054) that requests events every 5 seconds.
The frontend resolves and merges the `error_message` state as follows (lines 3026-3044):
```typescript
3026:                     if (data.status) {
3027:                         setSession((prev) => ({
3028:                             ...prev,
3029:                             status: data.status,
3030:                             error_message:
3031:                                 data.status === 'error'
3032:                                     ? (data.error_message ?? prev.error_message)
3033:                                     : prev.error_message,
3034:                             duration: data.duration ?? prev.duration,
...
```
Furthermore, the error banner (lines 3166-3179) is only visible if the status is exactly `'error'`:
```typescript
3166:                         {/* Session Error Message Banner */}
3167:                         {session.status === 'error' && (
3168:                             <div className="bg-destructive/10 border-destructive/20 text-destructive flex shrink-0 items-center gap-2 rounded-lg border px-4 py-3 text-sm">
3169:                                 <AlertTriangleIcon className="text-destructive size-4 shrink-0" />
3170:                                 <div className="flex-1">
3171:                                     <p className="text-sm font-semibold">
3172:                                         Phiên phân tích bị lỗi
...
```

On the backend, `LiveSessionController::checkAndStopIfDurationExceeded()` (lines 1172-1203) updates the status to `'ended'` when the package duration limit is exceeded:
```php
1195:                 $liveSession->update([
1196:                     'status' => 'ended',
1197:                     'ended_at' => now(),
1198:                     'duration_seconds' => (int) $elapsedSeconds,
1199:                     'error_message' => 'Phiên livestream đã tự động kết thúc do vượt quá thời lượng tối đa cho phép của gói dịch vụ ('.$maxDurationHours.' giờ).',
1200:                 ]);
```

### B. "Export CSV" and "Copy all" Buttons
Inside `CustomersPanel()` in `Show.tsx`, the buttons and gating logic are defined as follows:
- **Copy all** button (lines 1598-1610):
```typescript
1598:                         <Button
1599:                             variant="outline"
1600:                             size="sm"
1601:                             className="h-7 gap-1.5 text-xs"
1602:                             onClick={handleCopyAll}
1603:                         >
```
- **Export CSV** button (lines 1611-1626):
```typescript
1611:                         <Button
1612:                             variant="outline"
1613:                             size="sm"
1614:                             className="h-7 gap-1.5 text-xs"
1615:                             onClick={() => {
1616:                                 if (!canExportLeads) {
1617:                                     setShowUpgradeDialog(true);
1618:                                 } else {
1619:                                     exportLeadsCSV(filtered);
1620:                                 }
1621:                             }}
1622:                         >
```
- `handleCopyAll` (lines 1511-1520) and gating variables (lines 1473-1483):
```typescript
1483:     const canExportLeads = auth?.subscription?.features?.export_leads ?? false;
...
1511:     const handleCopyAll = () => {
1512:         if (!canExportLeads) {
1513:             setShowUpgradeDialog(true);
1514:             return;
1515:         }
1516:         copyLeadsToClipboard(filtered);
...
```

### C. Gated Features
1. **Audio Analysis**:
   - Backend logic: `AnalyzeCommentsJob.php` check (lines 151-154):
     ```php
     $audioAnalysisEnabled = $features['audio_analysis'] ?? false;
     if ($audioAnalysisEnabled && $session->tiktok_session_id) { ... }
     ```
   - Frontend exposure: No interactive elements on `Show.tsx` (only chime audio alert system `playOrderChime` on line 309). Configured and visible in package feature lists in `Subscription/Index.tsx` and `Settings/Index.tsx`.
2. **Stream limits (`limit_streams`)**:
   - Gated in `LiveSessionController::store` (lines 134-144) to prevent starting a session if the active count exceeds package limit.
3. **AI Credits (`ai_credits`)**:
   - Gated in `LiveSessionController::refreshInsights` (lines 407-412) and `AnalyzeCommentsJob::handle` (lines 118-171 in `SubscriptionGatingTest.php` context).

### D. Subscription Package Structure and Global Page Props
- **Packages definition**: Located in `database/seeders/SubscriptionPackageSeeder.php` (lines 15-52):
  - `Free`: price = 0, limit_streams = 1, max_duration_hours = 1, ai_credits = 1000, audio_analysis = false, export_leads = false.
  - `Pro`: price = 299000, limit_streams = 5, max_duration_hours = 4, ai_credits = 50000, audio_analysis = true, export_leads = true.
  - `Enterprise`: price = 999000, limit_streams = -1, max_duration_hours = 24, ai_credits = 500000, audio_analysis = true, export_leads = true.
- **Global Props sharing**: Configured in `app/Http/Middleware/HandleInertiaRequests.php` under `auth.subscription` (lines 53-64).

### E. Frontend Dialog Patterns
- standard Radix-UI based Dialog wrapped in Shadcn style imports, defined in `backend/resources/js/components/ui/dialog.tsx` (`Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter`).
- Reused in `Show.tsx` for `Order Creation Dialog` (lines 1823-1961) and `Upgrade Dialog` (lines 1963-1999).

---

## 2. Logic Chain

1. **State Update Mismatch**:
   - Polling checks if `data.status === 'error'` before updating `error_message`.
   - When the session ends due to duration limit, the backend sets status to `'ended'` and writes a detailed error explanation to `error_message`.
   - Because `data.status` is `'ended'` (not `'error'`), the frontend discards the new `error_message` from the polling response, leaving it `null` or unchanged.
2. **Banner Visibility**:
   - The frontend error banner only displays if `session.status === 'error'`.
   - Consequently, when the status is `'ended'` due to a plan limit, the warning banner is not shown, and the user is left unaware of the reason for the session ending.
3. **Copy / Export Gating Verification**:
   - Both Copy and Export CSV check the `canExportLeads` boolean (`auth.subscription.features.export_leads`).
   - If false, the frontend displays the `showUpgradeDialog` which prompts the user to upgrade to Pro or Enterprise.

---

## 3. Caveats

- We did not investigate alternative routes that could mutate session status outside `fetchEvents` and `show` (e.g. CLI commands or background workers that might end sessions directly).
- The analysis assumes the default behavior of the TikTok service is correct and does not verify VPS-level connection issues that could trigger `'ended'` or `'error'` statuses.

---

## 4. Conclusion

- **Major Bug**: The user is never notified if a session ends due to package limits because the polling state update ignores `error_message` for non-`'error'` statuses, and the UI lacks a banner for `'ended'` sessions containing an error message.
- **Gating mechanism**: Copying and Exporting CSV are gated properly via Inertia props, utilizing a local `Upgrade Dialog` that can be generalized or styled consistently.
- **Audio features**: These are backend-only and do not require UI-level gates in `Show.tsx` as the backend automatically checks package features and falls back to text-only analysis if `audio_analysis` is disabled.

---

## 5. Implementation Plan

### A. Polling State Update (Lives/Show.tsx)
Modify the state resolution to retrieve `error_message` regardless of the session status:
```typescript
// Replace lines 3030-3033 in Show.tsx:
error_message:
    data.error_message !== undefined
        ? data.error_message
        : prev.error_message,
```

### B. Display Session Ended Warning Banner (Lives/Show.tsx)
Add an amber warning banner underneath the offline warning banner for sessions that ended due to package/limit issues:
```typescript
{/* Session Ended with Limit Reached Banner */}
{session.status === 'ended' && session.error_message && (
    <div className="bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-500 flex shrink-0 items-center gap-2 rounded-lg border px-4 py-3 text-sm">
        <AlertTriangleIcon className="text-amber-500 size-4 shrink-0" />
        <div className="flex-1">
            <p className="text-sm font-semibold">
                Phiên phân tích đã kết thúc
            </p>
            <p className="text-xs opacity-90">
                {session.error_message}
            </p>
        </div>
    </div>
)}
```

---

## 6. Verification Method

- Run the feature test suite:
  ```bash
  php artisan test --filter SubscriptionGatingTest
  ```
- Trigger limit exceeded manually in DB by updating a session's `started_at` value to exceed the subscription's `max_duration_hours`.
- Perform a manual request to the polling route and verify the JSON response:
  ```bash
  # Send POST request to /lives/{id}/fetch-events and check response payload contains status: 'ended' and error_message
  ```
