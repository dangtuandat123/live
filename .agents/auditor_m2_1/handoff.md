# Handoff Report

## 1. Observation
I performed a thorough static and code-path analysis of the Milestone 2 backend subscription and payment system, and executed the project's automated test suite.

### Tool Commands & Results
- Command run: `php artisan test` inside `d:\Workspace\livestream\backend`
- Output summary:
  - 63 tests passed.
  - 3 tests failed inside `Tests\Feature\SubscriptionPaymentChallengerTest`.
  
Verbatim failures:
1. `Tests\Feature\SubscriptionPaymentChallengerTest > callback same price different package bug`
   ```
   Failed asserting that 1 matches expected 2.
   at tests\Feature\SubscriptionPaymentChallengerTest.php:76
   ```
2. `Tests\Feature\SubscriptionPaymentChallengerTest > callback duplicate requests cause double crediting`
   ```
   Failed asserting that 60.0 matches expected 30.
   at tests\Feature\SubscriptionPaymentChallengerTest.php:139
   ```
3. `Tests\Feature\SubscriptionPaymentChallengerTest > free package checkout infinite abuse`
   ```
   Expected response status code [400] but received 200.
   Failed asserting that 200 is identical to 400.
   at tests\Feature\SubscriptionPaymentChallengerTest.php:192
   ```

### Code Audit
- **No Cheating / Hardcoding**: I inspected `SubscriptionController.php`, `PaymentCallbackController.php`, and `SendOutboundPaymentWebhookJob.php` line by line. The controllers dynamically query the `subscription_packages`, `payment_configs`, `transactions`, and `user_subscriptions` database tables. There are no hardcoded responses or bypasses to satisfy test expectations.
- **Dynamic VietQR URL Generation**: Verified dynamic template placeholder replacement using `str_replace()` on prefix, suffix, user_id, and amount values.
- **Outbound Webhooks**: Dispatching `SendOutboundPaymentWebhookJob` to convert params and headers from config JSON template to replaced arrays, and utilizing Laravel's `Http` client dynamically.

---

# Audit Report

## Summary
- Scope: Subscription and Payment Backend System (Milestone 2)
- Mode: static/code-path audit
- Confidence: High
- Critical: 0
- High: 0
- Medium: 3 (Package Resolution, Double Crediting, Free Package Abuse)
- Low: 0
- Decision: **Fix before merge**

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | SubscriptionController.php, PaymentCallbackController.php, SendOutboundPaymentWebhookJob.php, Models, Migrations, Factories |
| Stack/framework | Laravel 11/12, PHP 8.2+, Eloquent ORM |
| Expected user behavior | Query subscription packages, initiate checkout to obtain VietQR code, receive subscription upgrade upon payment callback, allow Admin configuration of payment providers. |
| Expected backend/data behavior | Validate package presence, save transactions (pending/success), upgrade subscription dates, execute outbound webhook job with template replacements. |
| Source of truth | `ORIGINAL_REQUEST.md` |
| Exclusions | React Frontend Views (`Admin/Payments/Index`, `Admin/Packages/Index`, `Subscription/Index`) are excluded from backend code-path verification. |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 3 | 0 | 3 | React frontend views are excluded from this backend audit |
| User actions | 6 | 6 | 0 | List packages, status check, checkout, update payment config, package CRUD, callback processing |
| API/actions | 4 | 4 | 0 | GET package list, GET status, POST checkout, POST callback |
| Services/domain | 1 | 1 | 0 | Webhook notification dispatch job |
| DB/schema/config | 4 | 4 | 0 | Migrations for packages, subscriptions, payment configs, transactions |
| Auth/permissions | 2 | 2 | 0 | `auth:sanctum` for APIs, `EnsureUserIsAdmin` middleware for admin panel |
| State/cache | 1 | 1 | 0 | `activeSubscription` relation on User model |
| Tests | 3 | 3 | 0 | SubscriptionDatabaseTest, SubscriptionPaymentTest, SubscriptionPaymentChallengerTest |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Package list | SubscriptionController | High | Empty or hardcoded static list |
| User Status | SubscriptionController | High | Hardcoded active/inactive status |
| Checkout | SubscriptionController | High | Free package does not activate instantly; Paid package fails to generate VietQR url |
| Payment Callback | PaymentCallbackController | High | Fails to match price, fails to extend subscription, or fails to trigger webhooks |
| Webhook Outbound | SendOutboundPaymentWebhookJob | High | Bypasses template parsing or makes raw/static HTTP calls |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| User Pricing | View Package list | routes/web.php | Display packages from DB | Matches DB list | None |
| Admin Payments | View Config | routes/web.php | Retrieve active config | Matches active record | None |
| Admin Packages | Package CRUD | routes/web.php | Create/Update/Delete | DB transactions | None |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Package list | SubscriptionController@index | None | N/A | 200 OK / 500 | GET /api/subscription/packages | Low |
| Check status | SubscriptionController@status | None | N/A | 200 OK / 401 | GET /api/subscription/status | Low |
| Checkout package | SubscriptionController@checkout | package_id exists | N/A | 200 OK / 503 / 500 | POST /api/subscription/checkout | Medium |
| Trigger callback | PaymentCallbackController@handleCallback | id_user exists, sotien min:0 | N/A | 200 OK / 422 / 500 | POST /api/payments/callback | Medium |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| `Prefix {userId} Suffix` | SubscriptionController.php | Correct code syntax dynamically matching payment config values | Matches template prefix/suffix placeholders | None |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Checkout click | React View | POST /api/subscription/checkout | package_id | `auth:sanctum`, exists | Creates transaction record | 200 OK with VietQR | None |
| Payment callback | Bank Webhook | POST /api/payments/callback | id_user, sotien | public route, user exists | Creates success transaction | 200 OK success message | None |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| POST /api/subscription/checkout | Blocked (401) | N/A | Blocked by exists rule | N/A | Safe |
| POST /api/payments/callback | Allowed (Public) | N/A | Blocked by validation | Allows repeat calls | Vulnerable to replay (double-crediting) |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Active subscription extends on renew | PaymentCallbackController | Renew same package | `addDays($package->duration_days)` | Correct |
| New package deactivates old package | PaymentCallbackController | Buy different package | `update(['status' => 'inactive'])` | Correct |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Subscription System | Spoofed Callback | POST /api/payments/callback | Public endpoint lacks signature verification | Free subscription upgrades | Medium |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `CheckoutController` | 0 matches | None | Merged into SubscriptionController as requested |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Same price different package resolution | SubscriptionPaymentChallengerTest | Matching price directly to package query | Yes | None |
| Idempotency checking | SubscriptionPaymentChallengerTest | Missing transaction identifier check | Yes | None |
| Free checkout limit | SubscriptionPaymentChallengerTest | Allow unlimited checkouts | Yes | None |

## Findings

### [Medium] Finding 1: Package Price Resolution Bug
- **Type**: Logical Bug
- **Location**: `PaymentCallbackController.php:33`
- **Evidence**:
  ```php
  $package = SubscriptionPackage::where('price', $amount)->first();
  ```
- **Why wrong/risky**: If multiple packages share the same price, the system query grabs the first package matched, ignoring the package the user actually purchased in their checkout transaction.
- **Impact**: User gets upgraded to the wrong plan.
- **Scenario**: User checks out Package B (Promo, 60 days) for 150K. Callback is received for 150K. System resolves it to Package A (Basic, 30 days) and upgrades them for only 30 days.
- **Minimal fix**:
  Locate pending transaction for this user and amount first, and retrieve the package ID from that transaction:
  ```php
  $transaction = Transaction::where('user_id', $userId)
      ->where('amount', $amount)
      ->where('status', 'pending')
      ->latest()
      ->first();
  $packageId = $transaction ? $transaction->subscription_package_id : null;
  $package = $packageId ? SubscriptionPackage::find($packageId) : SubscriptionPackage::where('price', $amount)->first();
  ```
- **Validation**: Re-run Challenger test.
- **Confidence**: High

### [Medium] Finding 2: Lack of Callback Idempotency (Double Crediting Vulnerability)
- **Type**: Security Vulnerability / Logical Bug
- **Location**: `PaymentCallbackController.php:53`
- **Evidence**:
  ```php
  $transaction = Transaction::where('user_id', $userId)
      ->where('amount', $amount)
      ->where('status', 'pending')
      ->latest()
      ->first();
  ```
- **Why wrong/risky**: When the callback is called a second time, there is no pending transaction left, so the `else` block triggers, creating a new successful transaction and extending the subscription again.
- **Impact**: Duplicate webhooks result in double crediting users.
- **Scenario**: A banking callback fires twice due to network delay. The user gets extended by double the package duration.
- **Minimal fix**: Check if a webhook transaction with the same bank transaction reference has already been executed, or prevent creation of manual transactions without a pending check.
- **Validation**: Re-run Challenger test.
- **Confidence**: High

### [Medium] Finding 3: Free Package Checkout Infinite Abuse
- **Type**: Abuse Vulnerability
- **Location**: `SubscriptionController.php:74`
- **Evidence**:
  ```php
  if ($package->price === 0) { ... }
  ```
- **Why wrong/risky**: Users can check out free trial packages multiple times without any limitation.
- **Impact**: Loss of revenue as users repeatedly extend free trials indefinitely.
- **Scenario**: User requests checkout for a 0 VND package, obtains 7-day trial, then immediately requests checkout again, extending it to 14 days, and so on.
- **Minimal fix**: Implement a check to prevent users from checking out free trial packages if they already have an active subscription or have previously requested a free package.
  ```php
  $alreadyClaimed = UserSubscription::where('user_id', $user->id)
      ->where('subscription_package_id', $package->id)
      ->exists();
  if ($alreadyClaimed) {
      return response()->json(['error' => 'Vulnerability Blocked', 'message' => 'Free trial already claimed.'], 400);
  }
  ```
- **Validation**: Re-run Challenger test.
- **Confidence**: High

---

## 2. Logic Chain
1. I analyzed the source code files and verified that the implementation is 100% genuine and does not use hardcoded test conditions or cheat facades. All logic relies on querying DB models dynamically.
2. I executed `php artisan test` and observed that 63 main tests pass cleanly, but 3 challenger tests fail due to actual logical bugs/abuse vulnerabilities in the controller code.
3. Therefore, the implementation integrity itself is **CLEAN** (no cheating/fraud), but contains 3 confirmed logical bugs/vulnerabilities.
4. The recommended decision is **Fix before merge**.

## 3. Caveats
- This is a static and code-path analysis. No E2E browser tests were executed.
- Outward webhooks in the queue job are verified via unit tests mocking the HTTP calls, preventing real network execution in the restricted code-only environment.

## 5. Verification Method
To verify this audit independently, run:
```powershell
cd d:\Workspace\livestream\backend
php artisan test
```
The test suite executes both the main behavior test file `SubscriptionPaymentTest.php` and the challenger test file `SubscriptionPaymentChallengerTest.php`. Currently, the challenger tests fail, proving the existence of the documented bugs.
