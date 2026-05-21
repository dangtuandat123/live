# Handoff Report: Subscription, Payment, and Gating Investigation

## 1. Observation

Direct observations made in the codebase:

### Database Schema and Models
1. **Subscription Packages Table**: 
   - Path: `backend/database/migrations/2026_05_21_210000_create_subscription_packages_table.php`
   - Structure (lines 14-21):
     ```php
     Schema::create('subscription_packages', function (Blueprint $table) {
         $table->id();
         $table->string('name');
         $table->unsignedInteger('price');
         $table->unsignedInteger('duration_days');
         $table->json('features')->nullable();
         $table->timestamps();
     });
     ```
   - Observed that `features` exists as a nullable `json` field in the database.
2. **User Subscriptions Table**:
   - Path: `backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php`
   - Structure (lines 14-22):
     ```php
     Schema::create('user_subscriptions', function (Blueprint $table) {
         $table->id();
         $table->foreignId('user_id')->constrained()->cascadeOnDelete();
         $table->foreignId('subscription_package_id')->constrained('subscription_packages')->restrictOnDelete();
         $table->timestamp('starts_at')->nullable();
         $table->timestamp('expires_at')->nullable();
         $table->string('status')->default('active')->index();
         $table->timestamps();
     });
     ```
   - Confirmed that `user_subscriptions` does **NOT** contain `used_ai_credits` (neither the migration nor `UserSubscription.php` has references to this field).
3. **Transactions Table**:
   - Path: `backend/database/migrations/2026_05_21_210300_create_transactions_table.php`
   - Structure (lines 14-25):
     ```php
     Schema::create('transactions', function (Blueprint $table) {
         $table->id();
         $table->string('transaction_id')->unique();
         $table->foreignId('user_id')->constrained()->cascadeOnDelete();
         $table->unsignedInteger('amount');
         $table->foreignId('payment_config_id')->constrained('payment_configs')->restrictOnDelete();
         $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->restrictOnDelete();
         $table->string('vietqr_url', 500)->nullable();
         $table->string('status')->default('pending')->index();
         $table->timestamps();
     });
     ```
   - Observed `transaction_id` is unique.

### Gating and Limits
1. **Live Session Creation**:
   - Path: `backend/app/Http/Controllers/LiveSessionController.php` (lines 111-128)
     ```php
     public function store(Request $request)
     {
         $validated = $request->validate([
             'name' => ['required', 'string', 'max:255'],
             'tiktok_username' => ['required', 'string', 'max:100'],
             ...
         ]);

         // Tạo session DB
         $session = LiveSession::create([
             'user_id' => $request->user()->id,
             'name' => $validated['name'],
             'tiktok_username' => $validated['tiktok_username'],
             'status' => 'connecting',
         ]);
     ```
   - There is no check on `activeSubscription` or constraints checked against the package's `features` (e.g. AI analysis limits).
2. **System Settings configuration**:
   - Path: `backend/routes/web.php` (lines 174, 197)
     ```php
     'max_free_sessions' => 5,
     'max_free_sessions' => ['required', 'integer', 'min:1', 'max:999'],
     ```
   - The value is saved to `system_settings.json` via `/admin/settings` but is never referenced/enforced during the live session creation flow in `LiveSessionController@store`.

### Vulnerabilities
1. **Vulnerability 1: Package Price Resolution Bug**:
   - Path: `backend/app/Http/Controllers/PaymentCallbackController.php` (lines 43-56)
     ```php
     $transaction = Transaction::where('user_id', $userId)
         ->where('amount', $amount)
         ->where('status', 'pending')
         ->latest()
         ->lockForUpdate()
         ->first();

     $package = null;
     if ($transaction) {
         $package = SubscriptionPackage::find($transaction->subscription_package_id);
     }
     if (!$package) {
         $package = SubscriptionPackage::where('price', $amount)->first();
     }
     ```
   - Matching is done strictly via `user_id` and `amount`. If the user has multiple pending checkouts with the same price, the callback resolves to the `latest` pending transaction, which might not be the one they paid for.
   - If no transaction is found (e.g., deleted or already completed), it falls back to matching by price `SubscriptionPackage::where('price', $amount)->first();`, which resolves to the first package created with that price, ignoring the intended package.
2. **Vulnerability 2: Lack of Callback Idempotency**:
   - Path: `backend/app/Http/Controllers/PaymentCallbackController.php` (lines 66-101)
     ```php
     if (!$transaction) {
         $recentSuccess = Transaction::where('user_id', $userId)
             ->where('amount', $amount)
             ->where('status', 'success')
             ->where('updated_at', '>=', now()->subMinutes(5))
             ->latest()
             ->lockForUpdate()
             ->first();

         if ($recentSuccess) {
             DB::rollBack();
             return response()->json([ ... ]);
         }
     }
     ```
   - If a duplicate callback is sent *after* 5 minutes, `$recentSuccess` is `null`. A new successful transaction is created, and the user subscription is extended again.
   - Additionally, if the callback generates a random transaction ID (`$transactionId = ($activePaymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));`), it bypasses the unique database constraint on `transaction_id`.
   - Also, if a user makes two *legitimate* separate payments of the same amount within 5 minutes, the second callback is incorrectly ignored because it is treated as a duplicate.
3. **Vulnerability 3: Free Package Checkout Abuse**:
   - Path: `backend/app/Http/Controllers/SubscriptionController.php` (lines 74-87)
     ```php
     if ($package->price === 0) {
         if (UserSubscription::where('user_id', $user->id)->where('subscription_package_id', $package->id)->exists()) {
             return response()->json([ ... ], 400);
         }

         if ($user->activeSubscription) {
             return response()->json([ ... ], 400);
         }
     ```
   - Checks are done outside the transaction boundary and do not lock the database. Thus, concurrent requests can easily bypass these checks, allowing multiple active subscriptions to be created/extended.
4. **Vulnerability 4: Unauthenticated Public Callback Bypass**:
   - Path: `backend/routes/api.php` (line 21)
     ```php
     Route::post('/payments/callback', [PaymentCallbackController::class, 'handleCallback']);
     ```
   - The route is entirely public and does not check for any authentication, token, or webhook signature. Anyone can call it with any `id_user` and `sotien` to upgrade their subscription for free.

---

## 2. Logic Chain

1. **Free Session and Feature Limits are unenforced**:
   - Since `LiveSessionController@store` lacks checks on the user's subscription status or the configured limit `max_free_sessions` from `system_settings.json`, and does not query the active package features, users on a Free package can launch an unlimited number of sessions and use premium features without restrictions.
2. **Duplicate/Ignored Payments**:
   - In `PaymentCallbackController.php`, using a sliding window of `now()->subMinutes(5)` to check for duplicates means any webhook retried 5+ minutes later will be processed again as a new transaction. Generating a random `transaction_id` on the fly ensures that the database-level uniqueness constraint on `transaction_id` never fails on duplicate calls.
3. **Incorrect Package Activation on Same Price**:
   - If two packages (e.g. Package A for 30 days and Package B for 60 days) cost the same, a callback for that price resolves to the latest pending checkout transaction. This activates the wrong package if the user checked out both but only paid for the older one.
4. **Concurrently Gating Free Packages**:
   - Because `SubscriptionController@checkout` performs subscription checks before starting the database transaction (without any row locking like `sharedLock` or `lockForUpdate`), a user submitting multiple simultaneous requests will bypass the uniqueness checks, leading to multiple free trials being credited.

---

## 3. Caveats

- We assumed the Python service configuration/parameters do not enforce limitations; they only collect live stream statistics.
- We did not audit the payment gateways themselves (e.g., VietQR API server) as they are external third-party services.
- The `vietqr_url` generated by `SubscriptionController@checkout` embeds the params in the query string which might expose user ID.

---

## 4. Conclusion

The subscription and gating features contain multiple security flaws:
1. **Limit Gating Bypass**: Premium gating and session limits are not enforced.
2. **Unsecured Webhook**: The payment callback route is completely public and lacks signature/security verification.
3. **Weak Idempotency**: Duplicate payment callbacks sent after 5 minutes lead to double-crediting.
4. **Fragile Price Matching**: If multiple packages share a price, the system can upgrade the user to the incorrect package.
5. **Race Condition in Checkout**: Concurrent requests bypass the free-tier restriction.

---

## 5. Verification Method

To verify these findings, run:
```powershell
php artisan test
```
The test suite in `SubscriptionPaymentChallengerTest.php` contains test cases mapping directly to these conditions:
- `test_callback_same_price_different_package_bug`: Verifies package mismatch on same price.
- `test_callback_duplicate_requests_cause_double_crediting`: Verifies double-crediting behavior.
- `test_free_package_checkout_infinite_abuse`: Verifies block logic for free package abuse.

---

# Strict Audit matrices

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Subscription and Limit Gating Audit |
| Stack/framework | Laravel (PHP 8.2+), Inertia React, MySQL |
| Expected user behavior | Users checkout subscription packages, pay via VietQR, and are gated on Live Session features. |
| Expected backend/data behavior | Payments callback updates transactions and upgrades active subscription with strict idempotency and pricing. |
| Source of truth | `SubscriptionController`, `PaymentCallbackController`, `LiveSessionController`, Migrations |
| Exclusions | Python TikTok Service codebase |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 3 | 3 | 0 | Checked all three specified React views |
| User actions | 4 | 4 | 0 | List packages, Checkout, Pay callback, Admin configuration |
| API/actions | 4 | 4 | 0 | Package Index, Status check, Checkout, Webhook Callback |
| Services/domain | 1 | 1 | 0 | TikTok Service stats integration |
| DB/schema/config | 4 | 4 | 0 | Migration schemas for packages, subs, transactions, configs |
| Auth/permissions | 2 | 2 | 0 | Sanctum auth middleware, Admin middleware |
| State/cache | 1 | 1 | 0 | Live stats cache keys in controller |
| Tests | 3 | 3 | 0 | Audited Database, Challenger, and Payment tests |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Enforce free session limit | `system_settings.json` | High | Free users can create unlimited sessions |
| Match callback to checkout | `PaymentCallbackController` | High | Matching only on price triggers wrong package activations |
| Webhook Security | `routes/api.php` | High | Anyone can upgrade their account for free via public callback |
| Callback Idempotency | `PaymentCallbackController` | High | Retries after 5 mins cause double crediting |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Subscription/Index.tsx` | "Tôi đã chuyển tiền" button | Lines 400-415 | Polls status & confirms purchase | Polls `/api/subscription/status` | Safe |
| `Admin/Packages/Index.tsx` | Delete package action | Lines 191-206 | Block if package has history | Blocks correctly in controller | Safe |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Checkout package | `SubscriptionController@checkout` | `package_id` exists | `loadingCheckout` on UI | Json response with transaction | `/api/subscription/checkout` | Race condition on free packages |
| Payment Callback | `PaymentCallbackController@handleCallback` | `id_user`, `sotien` | None (Backend) | Json success response | `/api/payments/callback` | Unsecured public API |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "You have already subscribed..." | `SubscriptionController.php:78` | Explains user cannot buy multiple free trials | Correctly returned | None |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Click "Đăng ký ngay" | `Index.tsx` | `/api/subscription/checkout` | `{package_id}` | Sanctum authenticated | Creates transaction | Show QR modal | None |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `/api/payments/callback` | Enabled | Allowed | Allowed | Accepted | Anyone can upgrade any account |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| User cannot get unlimited free trials | `SubscriptionController.php:75` | Concurrent checkouts | Lack of DB locks | Multiple trials created |

## Suggested Fix Order
1. **Secure Webhook**: Introduce secret token / signature verification for `/api/payments/callback` to prevent public access.
2. **Improve Callback Resolution**: Save a unique transaction identifier or signature in checkout and match it during callback.
3. **Fix Idempotency**: Save the unique bank reference code in the transaction table and apply a DB unique constraint.
4. **Enforce Gating**: Add active subscription checks in `LiveSessionController@store`.
