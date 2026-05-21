# Handoff Report — Challenger 2 (Milestone 2 Verification)

## 1. Observation

When executing the project test command in `d:\Workspace\livestream\backend`, the test suite returned the following output:
```powershell
CommandLine: php artisan test
Cwd: d:\Workspace\livestream\backend
```
Resulting output snippet showing failures:
```
{#9051
  +"success": true
  +"message": "Subscription upgraded successfully"
} // vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:1850

   FAIL  Tests\Feature\SubscriptionPaymentChallengerTest
  ⨯ callback same price different package bug                                                                    0.04s  
  ⨯ callback duplicate requests cause double crediting                                                           0.02s  
  ⨯ free package checkout infinite abuse                                                                         0.02s  

...

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\SubscriptionPaymentChallengerTest > callback same price different package bug                  
  Expected subscription to be for Package B (Promo Package (60 Days)), but got Package ID: 1
Failed asserting that 1 matches expected 2.

  at tests\Feature\SubscriptionPaymentChallengerTest.php:76
...
  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\SubscriptionPaymentChallengerTest > callback duplicate requests cause double crediting         
  Expected subscription duration to remain 30 days (idempotent), but it was extended to 60 days.
Failed asserting that 60.0 matches expected 30.

  at tests\Feature\SubscriptionPaymentChallengerTest.php:139
...
  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\SubscriptionPaymentChallengerTest > free package checkout infinite abuse                       
  Expected response status code [400] but received 200.
Failed asserting that 200 is identical to 400.

  at tests\Feature\SubscriptionPaymentChallengerTest.php:192
```

We inspected the following target files:
- `backend/app/Http/Controllers/SubscriptionController.php`
- `backend/app/Http/Controllers/PaymentCallbackController.php`
- `backend/app/Http/Controllers/ProductController.php`
- `backend/app/Jobs/SendOutboundPaymentWebhookJob.php`
- `backend/app/Models/User.php`
- `backend/app/Models/UserSubscription.php`
- `backend/app/Models/SubscriptionPackage.php`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/database/migrations/2026_05_21_210000_create_subscription_packages_table.php`
- `backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php`
- `backend/database/migrations/2026_05_21_210300_create_transactions_table.php`

Direct observations on code sections:
1. In `PaymentCallbackController.php` (line 33):
   ```php
   $package = SubscriptionPackage::where('price', $amount)->first();
   ```
2. In `PaymentCallbackController.php` (line 53-76):
   ```php
   $transaction = Transaction::where('user_id', $userId)
       ->where('amount', $amount)
       ->where('status', 'pending')
       ->latest()
       ->first();

   if ($transaction) {
       $transaction->update([
           'status' => 'success',
           'subscription_package_id' => $package->id,
       ]);
   } else {
       // Audit success transaction
       $transactionId = ($activePaymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));
       $transaction = Transaction::create([
           'transaction_id' => $transactionId,
           'user_id' => $userId,
           'amount' => $amount,
           'payment_config_id' => $activePaymentConfig->id,
           'subscription_package_id' => $package->id,
           'vietqr_url' => null,
           'status' => 'success',
       ]);
   }
   ```
3. In `SubscriptionController.php` (line 74-88):
   ```php
   if ($package->price === 0) {
       // Free package: activate instantly
       DB::beginTransaction();
       try {
           $transactionId = ($paymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));

           $transaction = Transaction::create([
               'transaction_id' => $transactionId,
               'user_id' => $user->id,
               'amount' => 0,
               'payment_config_id' => $paymentConfig->id,
               'subscription_package_id' => $package->id,
               'vietqr_url' => null,
               'status' => 'success',
           ]);
   ```
4. In `routes/api.php` (line 21):
   ```php
   Route::post('/payments/callback', [PaymentCallbackController::class, 'handleCallback']);
   ```
5. In `routes/web.php` (line 238-246):
   ```php
   Route::delete('/packages/{package}', function (\App\Models\SubscriptionPackage $package) {
       $hasActiveSubs = \App\Models\UserSubscription::where('subscription_package_id', $package->id)
           ->where('status', 'active')
           ->exists();
       if ($hasActiveSubs) {
           return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đang có người dùng đăng ký hoạt động.']);
       }
       $package->delete();
       return back()->with('success', 'Đã xóa gói dịch vụ thành công.');
   })->name('admin.packages.destroy');
   ```

---

## 2. Logic Chain

1. **Same Price Package Mismatch Bug**:
   - Observation 1 shows that package matching is based purely on price match via query `SubscriptionPackage::where('price', $amount)->first()`.
   - If two packages (e.g. Package A and Package B) have the same price, this query always returns Package A (the first created).
   - Consequently, even if a user checked out Package B and has a pending transaction for Package B, the callback will activate a subscription for Package A. This explains the failure of `test_callback_same_price_different_package_bug`.

2. **Idempotency and Double Crediting**:
   - Observation 2 shows that when the callback is called a second time (or replayed), the pending transaction has already been updated to `success`.
   - The query for `pending` transaction returns `null`.
   - Instead of rejecting the duplicate callback, the `else` block executes, creating a brand new transaction and extending the active subscription. This explains the failure of `test_callback_duplicate_requests_cause_double_crediting`.

3. **Free Package Infinite Abuse**:
   - Observation 3 shows that when checking out a package with `price === 0`, it is instantly activated and committed without verifying if the user has already requested or activated the free package before.
   - A user can repeatedly hit `/api/subscription/checkout` with a free package ID and extend their active subscription duration indefinitely. This explains the failure of `test_free_package_checkout_infinite_abuse`.

4. **Public Callback Security Risk**:
   - Observation 4 shows `/api/payments/callback` is outside the `auth:sanctum` middleware and has no signature, token, or HMAC checks.
   - Any client can trigger this endpoint with arbitrary user IDs and amounts to credit subscriptions without making a payment.

5. **Package Deletion SQL Exception (500)**:
   - Observation 5 shows the admin controller deletes a package if it has no *active* subscriptions.
   - However, if the package has *inactive* (expired) subscriptions or historical transactions, the DB-level `restrictOnDelete()` constraint on foreign keys will trigger a `QueryException` and cause a 500 error page for the administrator.

---

## 3. Caveats

- Webhook processing concurrency: Under high concurrent traffic, race conditions are highly likely since no `lockForUpdate()` is applied on transactions during callback processing. This was verified logically but not via multi-process concurrent stress tests (as tests run sequentially in phpunit).
- Deactivated packages: There is no active/deactive flag on packages in the current database schema, only package deletion, which triggers the SQL constraint bug described above.

---

## 4. Conclusion

- **Decision**: **Block merge** (due to Critical security bypass and High financial/double-crediting bugs).
- The payment callback and checkout logic contain critical design flaws and bugs that allow:
  1. Users to receive the wrong subscription plan if multiple plans share the same price.
  2. Double-crediting and infinite extension on duplicate callback webhooks.
  3. Infinite free subscription duration through repeated free trial checkouts.
  4. Complete payment bypass by calling the unauthenticated public callback endpoint.
  5. Admin dashboard crash (500 page) on package deletion when historical data exists.

---

## 5. Verification Method

- To run the tests:
  ```powershell
  cd d:\Workspace\livestream\backend
  php artisan test
  ```
- Files to inspect:
  - `tests/Feature/SubscriptionPaymentChallengerTest.php` (contains the failing test cases).
  - `app/Http/Controllers/PaymentCallbackController.php` (contains callback logic).
  - `app/Http/Controllers/SubscriptionController.php` (contains checkout logic).

---

# Detailed Audit Report (strict-evidence-audit-v3-12k)

### Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Subscription checkout, payment callback, admin config APIs |
| Stack/framework | Laravel 11/12, PHP 8.3, SQLite/MySQL |
| Expected user behavior | Choose plan, get VietQR image, pay, get subscription activated correctly and exactly once |
| Expected backend/data behavior | Validate callbacks, check signatures, prevent double crediting, handle same price plans |
| Source of truth | `ORIGINAL_REQUEST.md`, `PROJECT.md` |
| Excludes | Frontend React files, python TikTokLIVE scripts |

### Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 2 | 2 | 0 | Pricing index page, Admin payment settings |
| User actions | 2 | 2 | 0 | Checkout package, Admin delete package |
| API/actions | 3 | 3 | 0 | List packages, status, checkout, callback |
| Services/domain | 1 | 1 | 0 | SendOutboundPaymentWebhookJob |
| DB/schema/config | 4 | 4 | 0 | packages, subscriptions, config, transactions tables |
| Auth/permissions | 2 | 2 | 0 | Sanctum user auth, admin role middleware |
| State/cache | 1 | 1 | 0 | activeSubscription relation cache bust |
| Tests | 3 | 3 | 0 | Payment, DB, and Challenger feature tests |

### Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Checkout free package | Specification | High | Double activation, infinite checkout loop |
| Callback resolves correct package | Specification | High | Matches package by price only, leading to plan mismatch |
| Callback prevents duplicate credit | Industry Standard | High | Allows replaying webhook to extend subscription |
| Callback is authenticated/signed | Security Standard | High | Publicly open, allowing arbitrary crediting |

### Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Only 1 active subscription at a time | `UserSubscription.php` | Multiple checkouts | Code deactivates old one but leaves state active | Pass (mostly, logic filters correctly) |
| Transaction ID uniqueness | `Transaction.php` | Multiple callback replays | Generates random TX ID in `else` block | Fail (inserts duplicate transactions for same payment) |
| Free package limitation | `SubscriptionController.php` | Double checkout | No check for existing trials | Fail (allows infinite trial extension) |

### Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| User Subscription status | Anyone | Callback POST API | Missing Signature / Auth | Set active subscription on any user | Critical |
| Packages & Revenues | Registered User | Checkout free package | Missing check for existing free subscription | Infinite free trial | High |

### Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Duplicate webhook request | None in basic tests | Transaction status check bypass | Yes (in Challenger test) | Concurrency race condition tests |
| Unauthenticated callback | None | Request signature verification | No | Signature test |

---

## Findings

### [Critical] Unauthenticated Webhook Callback Bypass
- **Type**: Security Bug
- **Location**: `routes/api.php` line 21, `PaymentCallbackController.php`
- **Evidence**: Route `/api/payments/callback` is public, has no middleware, and matches the user solely on input parameter `id_user`.
- **Why wrong**: Anyone can send a POST request to this endpoint with a user's ID and price, automatically upgrading their account without any payment.
- **Impact**: Loss of revenue, system compromise.
- **Minimal fix**: Implement HMAC signature verification or check basic auth header using a shared secret from `payment_configs`.

### [High] Same Price Package Mismatch Bug
- **Type**: Functional Bug
- **Location**: `PaymentCallbackController.php` line 33
- **Evidence**: `$package = SubscriptionPackage::where('price', $amount)->first();` matches first package instead of looking up checkout transaction package.
- **Why wrong**: Overwrites checkout request if prices are identical.
- **Impact**: Incorrect packages given to users.
- **Minimal fix**: Match package using the pending transaction's package ID first.

### [High] Duplicate Callback Double-Crediting
- **Type**: Idempotency Bug
- **Location**: `PaymentCallbackController.php` line 53-76
- **Evidence**: Missing pending transaction check triggers `else` block which creates a new transaction and extends the subscription again.
- **Why wrong**: Gateways retry webhooks; this leads to infinite crediting.
- **Impact**: Loss of revenue.
- **Minimal fix**: Reject callback if no pending transaction is found, or if a unique bank transaction ID has already been processed.

### [High] Free Package Checkout Infinite Abuse
- **Type**: Abuse/Logic Bug
- **Location**: `SubscriptionController.php` line 74-123
- **Evidence**: Checkout instantly activates free package and extends subscription length without checking if user already used it.
- **Why wrong**: Users can automate checkouts to get infinite free subscriptions.
- **Impact**: Loss of monetization.
- **Minimal fix**: Verify if the user already has a transaction/subscription for the free plan before activating.

### [Medium] Package Deletion SQL Constraint Crash (500)
- **Type**: Code Quality
- **Location**: `routes/web.php` line 238
- **Evidence**: Tries to delete a package if no active subscriptions exist, which throws database QueryException if inactive subscriptions or transactions exist.
- **Why wrong**: Crashes the admin page.
- **Minimal fix**: Catch the exception or check for any subscription/transaction existence.
