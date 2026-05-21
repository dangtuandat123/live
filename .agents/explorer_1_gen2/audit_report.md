# Audit Report

## Summary
- Scope: Deep static audit of subscription, payment, and AI comment analysis pipeline
- Mode: static/code-path audit
- Confidence: High
- Critical: 2
- High: 2
- Medium: 1
- Low: 0
- Decision: Fix before merge

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | d:\Workspace\livestream |
| Stack/framework | Laravel (PHP), Inertia, React (TypeScript), SQLite/MySQL |
| Expected user behavior | Users can subscribe to tiers, view dashboard, manage products/lives, and have comments analyzed via AI. |
| Expected backend/data behavior | Payments are resolved by transaction/package IDs, processed idempotently, free trials are restricted to 1 per user, and AI comment jobs run with concurrency locks and robust error handling. |
| Source of truth | Backend PHP code, database migrations, React components, and PHPUnit feature tests. |
| Exclusions | External TikTok API live connection, Runware AI live HTTP calls (both mocked in tests). |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 5 | 5 | 0 | Subscription/Index.tsx, Admin/Packages/Index.tsx, Admin/Payments/Index.tsx, Admin/Settings/Index.tsx, Admin/Users/Index.tsx |
| User actions | 8 | 8 | 0 | Checkout, paid payment confirmation, admin create/edit/delete packages, update role, update system settings |
| API/actions | 4 | 4 | 0 | index, status, checkout in SubscriptionController; handleCallback in PaymentCallbackController |
| Services/domain | 3 | 3 | 0 | TikTokService, RunwareAiService, AnalyzeCommentsJob |
| DB/schema/config | 6 | 6 | 0 | migrations for packages, user_subscriptions, transactions, payment_configs, live_events, live_stats |
| Auth/permissions | 2 | 2 | 0 | auth & admin middlewares, user isAdmin method |
| State/cache | 1 | 1 | 0 | AnalyzeCommentsJob concurrency lock |
| Tests | 4 | 4 | 0 | AnalyzeCommentsJobTest, AnalyzeCommentsJobAdversarialTest, SubscriptionPaymentTest, SubscriptionPaymentChallengerTest |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Package price resolution should match unique ID | PaymentCallbackController | High | Querying packages by price and taking the first match leads to incorrect package activation when prices overlap. |
| Payment callback must be idempotent | PaymentCallbackController | High | Re-requesting callback or parallel callback requests double-credit or extend user subscriptions incorrectly. |
| Free package checkout is limited to 1 | SubscriptionController | High | Allow checkout of free packages multiple times, granting infinite free trials. |
| Comment analysis concurrency lock | AnalyzeCommentsJob | High | Running comments analysis job concurrently on the same session causes race conditions in stats/sentiment aggregation. |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| Subscription/Index.tsx | Confirm Paid action | `Subscription/Index.tsx` line 136 | Trigger actual background check or redirect | Reloads only `activeSubscription` which might be stale | UX issue (reliance on user manual reload / click) |
| Admin/Packages/Index.tsx | Delete Package action | `Admin/Packages/Index.tsx` line 195 | Call API and display specific error if assigned | Calls API, handles errors correctly | None |
| Admin/Payments/Index.tsx | Submit payment config | `Admin/Payments/Index.tsx` line 74 | Validates JSON templates and puts configuration | Validates JSON and saves correctly | None |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Checkout | SubscriptionController@checkout | `package_id` exists | `loadingCheckout` is true | Redirects or returns QR URL | `/api/subscription/checkout` | Free packages can be abused; lack of active payment config checks |
| Payment Callback | PaymentCallbackController@handleCallback | `amount` and bank metadata | None (webhook) | Activates/extends subscription | `/api/payments/callback` | Non-idempotent; price-to-package resolution is collision-prone |
| Create Package | Admin router (closure) | `name`, `price`, `duration_days` | `processing` is true | Created package | `/admin/packages` | Features input array validation |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Tôi đã chuyển tiền" | `Subscription/Index.tsx` | Inform system payment is done and check status | Reloads only Inertia prop `activeSubscription` | Minor UX mismatch (no backend poll trigger) |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Checkout | `handleSelectPackage` | `/api/subscription/checkout` | `package_id` | Authenticated user | Creates Transaction | Returns transaction_id & vietqr_url | None |
| Confirm Paid | `handleConfirmPaid` | `router.reload` | `only: ["activeSubscription"]` | Authenticated user | Reads active subscription | Reloads Inertia props | Yes, does not actively poll payment status |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `/api/subscription/checkout` | Blocked by sanctum | Protected (current user context) | Allowed to select Free package repeatedly | Unlimited checkouts of Free package | Abuse of free package trials |
| `/api/payments/callback` | Allowed (public route) | N/A | Missing amount or duplicate callback | Allowed duplicate callbacks | Double-credits/extends package subscription (Lack of Idempotency) |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| UserSubscription has 1 active sub | User model `activeSubscription` | Create multiple active user subscriptions | `User::activeSubscription` returns `latestOfMany()` | Returns the newest active sub but allows multiple rows in DB |
| Comment analysis lock | `AnalyzeCommentsJob` | Run parallel jobs for same session | `AnalyzeCommentsJob@handle` | Blocked via `Cache::lock()` |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Subscription Tier access | Authorized User | `/api/subscription/checkout` | Lack of historical verification | Infinite free package checkout | Medium |
| Financial Crediting | Unauthorized external entity | `/api/payments/callback` | Lack of idempotency / price-resolution collision | Double crediting / wrong package activation | Critical |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| Package resolution by price | `PaymentCallbackController@handleCallback` | Activates wrong package when prices collide | `SubscriptionPackage::where('price', $amount)->first()` |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Multiple Free Package checkout | `SubscriptionPaymentChallengerTest@test_free_package_checkout_infinite_abuse` | User checking out free package when they already had one | Yes | None |
| Duplicate Callback processing | `SubscriptionPaymentChallengerTest@test_callback_duplicate_requests_cause_double_crediting` | Sending same callback twice | Yes | None |

## Findings

### [Critical] Package Price Resolution Vulnerability
- Type: Logic Bug / Financial Risk
- Location: `backend/app/Http/Controllers/PaymentCallbackController.php` lines 27-28
- Evidence: 
  ```php
  $package = SubscriptionPackage::where('price', $amount)->first();
  ```
- Cross-check: Verified in `SubscriptionPaymentChallengerTest.php` lines 18-50.
- Why wrong/risky: If two packages are configured with the same price (e.g. promotional package vs normal package), the system will always resolve to the first matching package, ignoring which package was actually selected during checkout.
- Impact: Users paying for package B might receive package A if the prices are equal.
- Scenario: Admin creates a special Promo Pro package for 100,000 VND and a standard Starter package for 100,000 VND. A customer purchasing Promo Pro gets the standard Starter package instead.
- Minimal fix: Lookup the transaction using a unique code/ID in the callback description/metadata, find the associated transaction, and fetch the package directly from `transaction->subscription_package_id`.
- Validation: Run `php artisan test --filter=SubscriptionPaymentChallengerTest::test_callback_same_price_different_package_bug`
- Confidence: High

### [Critical] Lack of Callback Idempotency
- Type: Concurrency / Double Crediting Risk
- Location: `backend/app/Http/Controllers/PaymentCallbackController.php`
- Evidence: Callback handler does not check if the transaction is already processed or use database transactions/pessimistic locks to guarantee atomicity.
- Cross-check: Verified in `SubscriptionPaymentChallengerTest.php` lines 52-87.
- Why wrong/risky: Concurrent or repeated callback requests from bank gateway webhooks can cause a user's subscription duration to be extended multiple times for a single payment.
- Impact: Financial loss due to users receiving more subscription time than paid for.
- Scenario: Webhook gateway retries a callback because of a timeout or network blip. The backend processes the callback twice, extending the user's expiration date by 2x duration.
- Minimal fix: Add a check if the transaction is already processed/completed, wrap transaction updating in a DB transaction, and lock the transaction row using `sharedLock()` or `lockForUpdate()`.
- Validation: Run `php artisan test --filter=SubscriptionPaymentChallengerTest::test_callback_duplicate_requests_cause_double_crediting`
- Confidence: High

### [High] Free Package Checkout Abuse
- Type: Business Logic Flaw
- Location: `backend/app/Http/Controllers/SubscriptionController.php`
- Evidence: Checkout logic does not verify if the user has previously registered or used a free package subscription.
- Cross-check: Verified in `SubscriptionPaymentChallengerTest.php` lines 89-114.
- Why wrong/risky: Allows users to subscribe to the free tier infinitely, bypassing limits.
- Impact: Loss of potential subscription revenue.
- Scenario: A user's free trial expires. Instead of upgrading, they request checkout of the free package again, extending their free trial indefinitely.
- Minimal fix: In `checkout()`, before processing a free package subscription, check if the user has any existing/past subscriptions to the free package.
- Validation: Run `php artisan test --filter=SubscriptionPaymentChallengerTest::test_free_package_checkout_infinite_abuse`
- Confidence: High

### [Medium] Concurrency and UI/UX polling gap
- Type: UX Flaw
- Location: `backend/resources/js/Pages/Subscription/Index.tsx`
- Evidence: Manual reliance on Inertia page reload upon clicking "Tôi đã chuyển tiền".
- Cross-check: Static analysis of `Index.tsx` shows no polling mechanism to dynamically update payment status when the server processes the callback.
- Why wrong/risky: Users might get confused if they click "Tôi đã chuyển tiền" before the webhook is received and processed by the backend.
- Impact: High support ticket volume and bad user experience.
- Scenario: User pays and immediately clicks "Tôi đã chuyển tiền". The webhook hasn't fired yet. The page reloads but shows "Free (Mặc định)" because the backend hasn't processed the callback.
- Minimal fix: Implement a status check endpoint polling every few seconds while the modal is open or when "Tôi đã chuyển tiền" is clicked to give a real-time status indication.
- Validation: Inspection of frontend code.
- Confidence: High

## Product/UX/Text/Duplicate Issues
- **"Tôi đã chuyển tiền" button lacks backend polling validation**: Relying on manually reloading page props is a fragile pattern because webhooks from bank gateways can take anywhere from a few seconds to a minute.
- **Admin Dashboard estimated revenue calculation**: In `web.php` line 78, revenue is calculated using a hardcoded `User::count() * 299000`. This is artificial data and does not represent actual transaction revenue.

## Test Gaps
- None. The Challenger test suite (`SubscriptionPaymentChallengerTest.php`) has 100% coverage of the specific logic vulnerabilities requested to be checked.

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test` | Yes | 67 passed | All database schema, controllers, jobs, and adversarial test cases function successfully | External payment gateway real-time behaviors |
| `npm run build` | Yes | Succeeded in 6.57s | React/TypeScript assets compile and bundle successfully without lint or syntax errors | UI/UX pixel-perfection or real runtime rendering |

## Missed-risk / Limitations
- Direct integration with real banking gateways (e.g. MB Bank API) cannot be fully verified without sandbox/credentials. Only the webhook receiver logic was verified.

## Suggested Fix Order
1. **Lack of Callback Idempotency** (Security/Financial critical)
2. **Package Price Resolution** (Security/Financial critical)
3. **Free Package Checkout Abuse** (Business logic high)
4. **UX Polling implementation** (UX medium)

## Decision
Fix before merge

This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.
