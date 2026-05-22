# Audit and Handoff Report

## Verdict: FAIL (Request Changes)

---

## 1. Observation

### Observation A: Frontend PUT requests in `Show.tsx`
In `backend/resources/js/Pages/Lives/Show.tsx`, the frontend component sends real-time updates to `/api/live-events/${id}` via three distinct fetch calls:

Line 578:
```typescript
        try {
            await fetch(`/api/live-events/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ is_pinned: newPinned }),
            });
        } catch (e) {
            console.error(e);
        }
```

Line 607:
```typescript
        try {
            await fetch(`/api/live-events/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ is_highlighted: newHighlighted }),
            });
        } catch (e) {
            console.error(e);
        }
```

Line 1435:
```typescript
    const saveOrder = async () => {
        if (orderDialog.customerIdx === null) return;
        const customer = filtered[orderDialog.customerIdx];
        
        try {
            const res = await fetch(`/api/live-events/${customer.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    qty: orderForm.qty,
                    note: orderForm.note,
                    status: orderForm.status,
                }),
            });
```

### Observation B: Backend route definition in `web.php`
In `backend/routes/web.php` (line 51), the route is defined as follows:
```php
    Route::put('/live-events/{liveEvent}', [LiveSessionController::class, 'updateEvent'])->name('live-events.update');
```
This route is grouped under the standard web middleware group (`['auth', 'verified']`) and has no prefix.

### Observation C: Output of `php artisan route:list`
Running `php artisan route:list` confirms that the registered URI is exactly `live-events/{liveEvent}`:
```
  PUT       live-events/{liveEvent} ........................... live-events.update › LiveSessionController@updateEvent
```

### Observation D: Dynamic Bank Configuration
In `backend/app/Http/Controllers/SubscriptionController.php` (lines 68-74 & 143-178), bank details are dynamic and verify the configuration is complete:
```php
        $paymentConfig = PaymentConfig::where('is_active', true)->first();
        if (! $paymentConfig || empty($paymentConfig->account_no) || empty($paymentConfig->bank_name)) {
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => 'Cấu hình thanh toán chưa đầy đủ. Vui lòng liên hệ Admin.',
            ], 503);
        }
```
And in `backend/resources/js/Pages/Subscription/Index.tsx` (lines 765-772), the frontend renders a warning if bank credentials are empty:
```typescript
                                {(!checkoutData?.beneficiary_bank || !checkoutData?.beneficiary_account || !checkoutData?.beneficiary_name) ? (
                                    <div className="flex gap-2 rounded-lg border border-red-500/20 bg-red-500/5 p-3.5 text-sm text-red-600">
                                        <AlertCircleIcon className="mt-0.5 size-4 shrink-0 text-red-500" />
                                        <p>
                                            <span className="font-semibold">Lưu ý:</span> Không tìm thấy thông tin tài khoản ngân hàng thụ hưởng. Vui lòng liên hệ Admin để cấu hình thanh toán.
                                        </p>
                                    </div>
                                ) : (
```

### Observation E: Localized Feature Casting
In `backend/app/Models/SubscriptionPackage.php` (lines 39-91), package features are converted to dynamic Vietnamese descriptions via a computed attribute:
```php
    public function getFeaturesListAttribute(): array
    {
        ...
    }
```

---

## 2. Logic Chain

1. The frontend React page `Show.tsx` makes fetch requests to `/api/live-events/${id}` to persist changes to the database (Observation A).
2. The Laravel backend routes file `routes/web.php` maps the event update endpoint to `/live-events/{liveEvent}` (Observation B), which register in the system routing table as exactly `live-events/{liveEvent}` (Observation C).
3. Since `/api/live-events/{id}` is not defined or redirected anywhere in the Laravel routing structure, any HTTP request sent to it from the React client will trigger a `404 Not Found` response.
4. Consequently, comment pinning and order highlighting updates will be visually changed on the client side but will fail silently to persist on the backend.
5. In addition, when saving order metadata (qty, note, status), `res.ok` check evaluates to false, causing the UI to trigger a `"Có lỗi xảy ra khi lưu đơn hàng."` toast error and prevent the modal from closing.
6. Therefore, the implementation breaks correctness, completeness, and user interaction.

---

## 3. Caveats

- We assumed that there are no additional HTTP server rewrites (e.g. via Nginx or Apache) that map `/api/live-events/*` to `/live-events/*`. Based on standard Laravel project structures and the local routing list, no such rewrites exist.

---

## 4. Conclusion

- **Verdict**: **FAIL**
- The dynamic sync of comments and order details from the backend is broken due to a route mismatch between the frontend API client (`/api/live-events/${id}`) and the backend routing table (`/live-events/{liveEvent}`).
- The subscription configurations and Vietnamese localized package features casting work correctly.
- Hardcoded bank credentials have been completely removed and are fully dynamic.

---

## 5. Verification Method

To verify the route mismatch:
1. Try executing a mock PUT request to `/api/live-events/1` after authenticating:
   ```bash
   # Will output a 404 response
   curl -X PUT http://localhost:8000/api/live-events/1
   ```
2. Inspect the registered routes:
   ```bash
   php artisan route:list --name=live-events.update
   ```
   Output lists the path as `live-events/{liveEvent}` without any `/api` prefix.

---

## 6. Review matrices (Strict Audit Guidelines)

### Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | UI Sync Dynamic Integration |
| Stack/framework | Laravel, React, Inertia, TypeScript |
| Expected user behavior | Pin comment, highlight order, and save metadata updates real-time |
| Expected backend/data behavior | Save updates to `live_events` table and return JSON response |
| Source of truth | `routes/web.php`, `Show.tsx` |
| Exclusions | None |

### Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 2 | 2 | 0 | `Lives/Show.tsx`, `Subscription/Index.tsx` |
| User actions | 3 | 3 | 0 | Pin comment, highlight comment, save order details |
| API/actions | 4 | 4 | 0 | updateEvent, checkout, status, callback |
| Services/domain | 1 | 1 | 0 | TikTokService |
| DB/schema/config | 3 | 3 | 0 | LiveEvent model, SubscriptionPackage model, PaymentConfig model |
| Auth/permissions | 1 | 1 | 0 | Owner checks in updateEvent |
| State/cache | 1 | 1 | 0 | Cache gating in Lives controller |
| Tests | 2 | 2 | 0 | `LiveEventUpdateTest.php`, `SubscriptionPaymentTest.php` |

### Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| User cannot update event owned by another | `LiveSessionController.php` line 1056 | Update event in another user's session | Verified via Owner test | **PASS** (403 returned) |
| System returns 503 if payment configuration is missing | `SubscriptionController.php` line 69 | Set is_active config to false and check out paid plan | Verified via Subscription test | **PASS** (503 Service Unavailable) |
| Dynamic VietQR info rendering | `SubscriptionController.php` line 143 | QR templates containing wildcards | Verified replacements manually | **PASS** |

### Findings

#### [Critical] Path Mismatch on LiveEvent Update API
- **Type**: Confirmed Bug / Mismatch
- **Location**: `backend/resources/js/Pages/Lives/Show.tsx` (lines 578, 607, 1435) vs `backend/routes/web.php` (line 51)
- **Evidence**: Frontend uses `/api/live-events/${id}` while backend routes register `/live-events/{liveEvent}`.
- **Cross-check**: `php artisan route:list` shows `PUT live-events/{liveEvent}`.
- **Why wrong/risky**: All frontend PUT requests fail with `404 Not Found` in production/development, making live interactive features (pinning comments, highlighting orders, saving metadata) completely non-functional.
- **Minimal fix**: Update `Show.tsx` fetch endpoints from `/api/live-events/` to `/live-events/` (or use the Ziggy helper `route('live-events.update', id)`).
- **Validation**: Will show 200 OK after path is matched.
