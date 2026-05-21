# Forensic Audit Report & Strict Evidence Audit

**Work Product**: Subscription, Payment, and Subscription Package System (Backend & Frontend)
**Profile**: General Project
**Integrity Mode**: Development
**Verdict**: INTEGRITY VIOLATION (Due to missing DB row locks / concurrent request locking in payment callbacks)

---

## 1. Executive Summary

- **Scope**: Comprehensive integrity audit of payment callbacks, subscription checkouts, admin configs, free package abuse checks, status polling UI, and build/test verification.
- **Mode**: Static / code-path audit & behavioral test validation.
- **Confidence**: High
- **Critical Issues**: 1 (Missing DB row locks / concurrency locks in callback idempotency handling)
- **High Issues**: 0
- **Medium Issues**: 0
- **Low/Info Issues**: 0
- **Decision**: **Block merge / Reject work product** due to failing Check 3 (missing DB row locks/concurrency control).

---

## 2. Scope, Stack, and Source of Truth

| Item | Value |
|---|---|
| Target | Subscription, Payment, and Subscription Package features |
| Stack/framework | Laravel (PHP 8.2+), React (TypeScript), Inertia.js, TailwindCSS |
| Expected user behavior | User can view subscription packages, checkout via dynamic VietQR, and have their account upgraded automatically upon payment callback. |
| Expected backend/data behavior | The callback handles banking webhooks idempotently, upgrades user subscriptions, logs transactions, and triggers outbound webhooks. Free packages are restricted to once per user. |
| Source of truth | `ORIGINAL_REQUEST.md`, project specification, and automated test cases. |
| Exclusions | None |

---

## 3. Coverage Ledger

| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 1 | 1 | 0 | `Subscription/Index.tsx` |
| User actions | 3 | 3 | 0 | Checkout package, Copy transfer info, Manual check payment |
| API/actions | 3 | 3 | 0 | Packages listing, Checkout creation, Payment callback |
| Services/domain | 1 | 1 | 0 | `SendOutboundPaymentWebhookJob` |
| DB/schema/config | 4 | 4 | 0 | `subscription_packages`, `user_subscriptions`, `payment_configs`, `transactions` |
| Auth/permissions | 2 | 2 | 0 | Admin middleware verification, Sanctum auth |
| State/cache | 1 | 1 | 0 | Subscription status polling and manual checking |
| Tests | 3 | 3 | 0 | `SubscriptionPaymentTest.php`, `SubscriptionPaymentChallengerTest.php`, `SubscriptionDatabaseTest.php` |

---

## 4. Expected Behavior Contract

| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Package price resolution uses transactions | Audit check 2 | High | Callback upgrades based on hardcoded pricing/mock checks instead of checking checked-out transaction ID. |
| Callback is idempotent with DB locks | Audit check 3 | High | Bypassing pending checks, lacking row locks, allowing concurrent requests to double-credit. |
| Free package abuse is blocked | Audit check 4 | High | Allowing a user to register a free package multiple times or bypass backend validation. |
| UI polling and handlers are genuine | Audit check 5 | High | Dummy button handlers or fake/mocked API responses in `Subscription/Index.tsx`. |

---

## 5. Invariant and State Matrix

| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| User cannot subscribe to a free package twice | `SubscriptionController.php:75-80` | Post checkout request for free package twice | Database query checking existing subscription: `UserSubscription::where(...)->exists()` | PASS (blocked with 400 Bad Request) |
| Webhook callback resolves correct package | `PaymentCallbackController.php:48-53` | Same-price package checkout | Verifies `$transaction->subscription_package_id` first | PASS (resolves correct package) |
| Webhook callback is idempotent | `PaymentCallbackController.php:63-76` | Sequential duplicate webhooks | Checks `Transaction::where('status', 'success')->where('updated_at', '>=', now()->subMinutes(5))->first()` | PASS for sequential duplicates |
| Concurrency safety (DB row locks) | `PaymentCallbackController.php` | Concurrent duplicate webhooks | Lacks `lockForUpdate()` or similar locks on transaction rows | **FAIL** (potential race condition / double upgrade) |

---

## 6. Phase Results (Forensic Verification Procedure)

### Phase 1: Source Code Analysis
- **Hardcoded output detection**: PASS. Checked `PaymentCallbackController.php` and `SubscriptionController.php` for hardcoded response shortcuts. All inputs are resolved from the database models (`SubscriptionPackage`, `Transaction`, `UserSubscription`).
- **Facade detection**: PASS. Interfaces and classes contain genuine implementation logic including database transactions, relationships, Carbon datetime manipulation, and external HTTP calls via Laravel HTTP client.
- **Pre-populated artifact detection**: PASS. No fake log files, result files, or pre-populated verification artifacts exist.

### Phase 2: Behavioral Verification
- **Build and run**: PASS. Checked via `php artisan test` and `npm run build`.
- **Output verification**: PASS. The system generates correct VietQR URLs, performs dynamic replacement in outbound webhooks, and correctly upgrades subscriptions.
- **Dependency audit**: PASS. No prohibited core logic delegation.

---

## 7. Audit Findings

### [Critical] Missing DB Row Locks in Payment Callback Idempotency
- **Type**: Concurrency / Race Condition Vulnerability
- **Location**: `backend/app/Http/Controllers/PaymentCallbackController.php` (Lines 41-45, 78-97)
- **Evidence**:
  The transaction is fetched before `DB::beginTransaction()` is called, and does not use any lock (`lockForUpdate` or `sharedLock`):
  ```php
  $transaction = Transaction::where('user_id', $userId)
      ->where('amount', $amount)
      ->where('status', 'pending')
      ->latest()
      ->first();
  ```
  And then updated inside the transaction:
  ```php
  DB::beginTransaction();
  try {
      if ($transaction) {
          $transaction->update([
              'status' => 'success',
              'subscription_package_id' => $package->id,
          ]);
  ```
- **Why wrong/risky**:
  Under concurrent callback execution, multiple threads can read the same pending transaction, bypass the check, enter the update block simultaneously, and double-credit the subscription extension duration.
- **Impact**: Financial and system state integrity risk under high webhook concurrency.
- **Scenario**: A banking webhook system sends two duplicate callback requests at the exact same millisecond. Both threads read the transaction as `pending` before either has completed the transaction write.
- **Minimal fix**:
  Query the transaction inside the database transaction block using `lockForUpdate()`:
  ```php
  DB::beginTransaction();
  try {
      $transaction = Transaction::where('user_id', $userId)
          ->where('amount', $amount)
          ->where('status', 'pending')
          ->lockForUpdate()
          ->latest()
          ->first();
      // ...
  ```
- **Validation**: Failing check 3 of the integrity audit requirements.

---

## 8. Validation Commands Execution Log

### Automated Tests Execution
Executed command: `php artisan test`
Result:
```
Tests:    67 passed (490 assertions)
Duration: 2.87s
```

### Frontend Build Execution
Executed command: `npm run build`
Result:
```
vite v7.3.3 building client environment for production...
✓ 3410 modules transformed.
✓ built in 6.34s
```

---

## 9. Final Audit Decision

### **Verdict**: INTEGRITY VIOLATION (REJECT)
**Reason**: Check 3 explicitly requires that the callback idempotency logic is genuinely using status-checking and DB row locks (`lockForUpdate`) or similar to prevent concurrent webhook upgrades. The codebase contains no database row locks (`lockForUpdate`) or locking mechanisms, representing a concurrency vulnerability.
