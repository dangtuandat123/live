# Plan - E2E Testing for Subscription, Payment, and Admin Config

This plan details E2E test suite design and implementation.

## Phase 1: Test Plan & Feature Inventory
- Identify features and assign N = 7.
- Design 4-tier test case hierarchy:
  - **Tier 1: Feature Coverage (5 * 7 = 35 tests)**:
    - 5 tests for Package Listing API (active, inactive, fields check, formats)
    - 5 tests for Status API (active subscription, inactive, expired, formatting)
    - 5 tests for Checkout API (valid package, custom amount, VietQR URL validation, transaction status)
    - 5 tests for Payment Callback API (happy path, double-submit, amount matching, status updating)
    - 5 tests for Outbound Webhook Forwarding (valid payload, parsing variables, POST, GET, PUT methods)
    - 5 tests for Admin Payment Config CRUD (create, read, update, delete config, authorization checks)
    - 5 tests for Admin Package CRUD (create, read, update, delete package, authorization checks)
  - **Tier 2: Boundary & Corner Cases (5 * 7 = 35 tests)**:
    - Edge cases for pricing, negative prices, giant prices, invalid features JSON structure
    - Expiration calculations, leap years, timezone handling, zero/negative durations
    - Mismatched callback amounts, invalid user IDs, webhook timeouts, bad configs
    - Webhook payload template injection, header validation, empty values, special chars in templates
    - SQL injection inputs, unauthenticated admin access, privilege escalation attempts
  - **Tier 3: Cross-Feature Combinations (7 tests)**:
    - Upgrade from Package A to Package B (calculating price difference, dates overlap, remaining duration)
    - Downgrade from Package B to Package A (rules on expiration, refund policy or queueing)
    - Subscription renewal (extending expires_at of same package)
    - Consecutive payments of same or different packages
    - Multiple configurations active, checking prefix/suffix precedence
    - User with active subscription checking checkout and status
    - Webhook callback causing subscription update followed by status checking and admin CRUD sync checks
  - **Tier 4: Real-World Workloads (5 tests)**:
    - Workload 1: End-to-end user journey: package list -> checkout -> callback payment -> verify subscription status.
    - Workload 2: Admin configures custom payment config -> user checkouts with custom package -> callback processed with prefix/suffix verification -> outbound webhook triggered successfully.
    - Workload 3: Subscription expiration and renewal: active subscription expires -> client status shows inactive -> client checkouts and pays again -> status shows active with correct new dates.
    - Workload 4: Subscription upgrade/downgrade flow: user has active Basic package -> user checkouts Pro package -> payment callback processed -> subscription upgraded to Pro immediately with correct new expiry.
    - Workload 5: System robustness under webhook/payment failure: checkout created -> callback fails (wrong amount/user) -> transaction marked failed -> user subscription status unaffected -> admin modifies config -> retry callback with correct values -> user upgraded -> webhook triggered.

## Phase 2: Setup Test File & Infrastructure
- Create `backend/tests/Feature/SubscriptionPaymentTest.php`.
- Define mock handlers for Outbound Webhooks using Laravel's `Http::fake()`.
- Define helper methods for clean setup (createUser, createAdmin, createPackage, createPaymentConfig).

## Phase 3: Implement & Validate tests
- Write the E2E tests following the PHPUnit structure in Laravel.
- Run tests (they will fail first, which is correct and expected since implementation does not exist yet).
- Publish `TEST_READY.md` and `TEST_INFRA.md`.
