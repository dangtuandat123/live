# Audit, Quality & Adversarial Review Report — Milestone 1: DB Schema & Models

**Review Date**: 2026-05-21  
**Auditor**: reviewer_m1_1 (reviewer, critic)  
**Status**: REQUEST_CHANGES (with major/medium findings to be resolved)

---

# PART 1: Strict Evidence Audit

## Summary
- **Scope**: DB Migrations, Eloquent Models, Relations, Factories, Seeders, and Tests added in Milestone 1.
- **Mode**: static/code-path audit & local test execution.
- **Confidence**: High (100% of target files read and test suite executed).
- **Findings**:
  - **Critical**: 0
  - **High**: 1 (Cascade deletes on financial/audit logs)
  - **Medium**: 2 (Starts_at future check bypass, missing default attribute mirroring)
  - **Low**: 1 (Redundant index on unique column)
- **Decision**: **Fix before merge** / **REQUEST_CHANGES**

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Subscription and Transaction DB schemas, Eloquent Models, Factories, Seeders, Tests |
| Stack/framework | Laravel 11.x, PHP 8.2+, PHPUnit |
| Expected user behavior | Users can subscribe to packages, perform transactions, and have active subscription status tracked. |
| Expected backend/data behavior | Data integrity is maintained for transactions and subscriptions; active status handles expiration and start dates correctly. |
| Source of truth | `backend/database/migrations/*`, `backend/app/Models/*`, `backend/tests/Feature/SubscriptionDatabaseTest.php` |
| Exclusions | None |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 0 | 0 | 0 | Backend-only milestone |
| User actions | 0 | 0 | 0 | N/A |
| API/actions | 0 | 0 | 0 | N/A |
| Services/domain | 0 | 0 | 0 | N/A |
| DB/schema/config | 4 | 4 | 0 | All 4 migrations fully read |
| Auth/permissions | 0 | 0 | 0 | N/A |
| State/cache | 0 | 0 | 0 | N/A |
| Tests | 1 | 1 | 0 | `SubscriptionDatabaseTest.php` fully read |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Retrieve active subscription | `User::activeSubscription` | High | Returns a subscription that has expired or has not yet started. |
| Cascade deletes on User | migrations | High | Deleting a user should cascade delete their subscriptions and transactions (standard but risky for financial records). |
| Cascade deletes on Configuration | migrations | High | Deleting a package or payment configuration should never delete user subscriptions/transactions; this destroys historical audit logs. |
| Cast JSON features/templates | Models | High | Accessing JSON columns returns raw strings instead of arrays/objects. |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Subscription status | `UserSubscription.php:55` | `status` is active but `starts_at` is in the future. | `isActive()` checks status and `expires_at` only. | **FAIL** (returns active for future subscription) |
| Transaction audit preservation | `2026_05_21_210300_create_transactions_table.php:19` | PaymentConfig is deleted. | `payment_config_id` foreign key has `cascadeOnDelete()`. | **FAIL** (deletes all transactions using that config) |
| Subscription history preservation | `2026_05_21_210100_create_user_subscriptions_table.php:17` | SubscriptionPackage is deleted. | `subscription_package_id` has `cascadeOnDelete()`. | **FAIL** (deletes all historical user subscriptions) |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| User subscription status | Admin/User | Mass-assignment | `$fillable` includes `status` | Direct update of status bypasses payment checks if request validation is weak. | Medium |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Future subscription start | None | Set `starts_at` to future date and assert `isActive()` is false. | No | Yes |
| Package deletion protection | None | Delete a package and check if historical subscriptions remain or deletion is restricted. | No | Yes |
| Payment config deletion protection | None | Delete a payment config and check if transactions remain or deletion is restricted. | No | Yes |

---

# PART 2: Quality Review

## Review Summary

**Verdict**: **REQUEST_CHANGES**

## Findings

### [High] Cascade Deletes on Configuration Deletion (Data Integrity Risk)
- **What**: Cascade deletes configured on foreign keys referencing configuration tables (`subscription_packages` and `payment_configs`).
- **Where**:
  - `backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php` (line 17):
    `$table->foreignId('subscription_package_id')->constrained('subscription_packages')->cascadeOnDelete();`
  - `backend/database/migrations/2026_05_21_210300_create_transactions_table.php` (line 19):
    `$table->foreignId('payment_config_id')->constrained('payment_configs')->cascadeOnDelete();`
- **Why**: User subscriptions and payment transactions represent historical contracts and financial audit trails. If an administrator deletes a package or disables/removes a payment gateway config, deleting related subscriptions or transactions will destroy critical financial records.
- **Suggestion**: Use `restrictOnDelete()` (or make the foreign key `nullable()` and use `nullOnDelete()`) to prevent deletion of configuration items that are referenced by existing user transactions or subscriptions.

### [Medium] Future Subscription Start Check Bypass
- **What**: Incomplete check for active subscriptions when the subscription's start date is in the future.
- **Where**:
  - `backend/app/Models/UserSubscription.php` (lines 55-58):
    ```php
    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture());
    }
    ```
  - `backend/app/Models/User.php` (lines 92-102):
    ```php
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }
    ```
- **Why**: If a subscription is scheduled for the future (e.g. `starts_at` is set to tomorrow or next week), both `isActive()` and the `activeSubscription()` relationship will consider it active *today* because they do not check if `starts_at <= now()`.
- **Suggestion**: Add a check for `starts_at` to ensure it is in the past or null:
  - In `isActive()`: `($this->starts_at === null || $this->starts_at->isPast())`
  - In `activeSubscription()`: `->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))`

### [Medium] Missing Model Default Attribute Mirroring
- **What**: Database column defaults are not mirrored in the Eloquent model `$attributes` property.
- **Where**:
  - `UserSubscription.php` (missing default `status => 'active'`)
  - `PaymentConfig.php` (missing defaults `method => 'POST'` and `is_active => true`)
  - `Transaction.php` (missing default `status => 'pending'`)
- **Why**: Per the project's Laravel Best Practices (`rules/migrations.md` §16), column defaults defined in migrations must be mirrored in the model `$attributes` array so that new instances have the correct defaults before being persisted.
- **Suggestion**: Add `protected $attributes = [...]` to the respective models.

### [Minor] Redundant Index on Unique Column
- **What**: `transaction_id` column has both `unique()` and `index()` defined.
- **Where**: `backend/database/migrations/2026_05_21_210300_create_transactions_table.php` (line 16):
  `$table->string('transaction_id')->unique()->index();`
- **Why**: Database engines automatically create a unique index for any column defined with a unique constraint. Appending `->index()` creates a redundant second non-unique index on the same column.
- **Suggestion**: Change to `$table->string('transaction_id')->unique();`.

## Verified Claims
- **All tests pass successfully** → verified via `php artisan test` in `backend/` directory → **PASS** (48/48 tests passed).
- **Casts use Laravel 11 style** → verified via examining models (`SubscriptionPackage.php`, `UserSubscription.php`, `PaymentConfig.php`, `Transaction.php`) which all define a `casts()` method instead of a `$casts` property → **PASS**.
- **Seeders are idempotent** → verified via examining `SubscriptionPackageSeeder.php` and `PaymentConfigSeeder.php` which use `updateOrCreate` → **PASS**.

## Coverage Gaps
- **Missing validation of future start dates** — risk level: **Medium** — recommendation: Investigate and add verification test cases.
- **Missing protection tests for configuration deletions** — risk level: **High** — recommendation: Add test cases to assert that deleting a package or configuration fails or handles the reference gracefully.

## Unverified Items
- None.

---

# PART 3: Adversarial Review

## Challenge Summary

**Overall risk assessment**: **MEDIUM**

## Challenges

### [High] Cascade Delete Action on Financial Logs
- **Assumption challenged**: It is safe to automatically delete subscription history and transactions when packages or payment configurations are deleted.
- **Attack scenario**: An administrator accidentally deletes a legacy payment configuration or promotional package. The database cascade deletes all transactions and user subscriptions linked to it. The system loses all financial audit data for those subscriptions, leading to discrepancies during tax audits or user support.
- **Blast radius**: DB wide loss of payment logs and subscription history.
- **Mitigation**: Change to `restrictOnDelete()` or make foreign keys nullable and set to null on delete.

### [Medium] Future-dated Active Subscription Exploit
- **Assumption challenged**: An active subscription is only determined by status and expiration date.
- **Attack scenario**: A user pre-purchases a subscription package scheduled to start next month. Because `starts_at` is in the future but status is set to `'active'`, the system grants them premium benefits today.
- **Blast radius**: Business loss through unauthorized early access.
- **Mitigation**: Strictly validate that `starts_at <= now()` (or is null) before considering a subscription active.

## Stress Test Results
- **Starts_at in the future** → expected `isActive() == false` and not returned in `activeSubscription()` → Actual: `isActive() == true` and returned as active subscription → **FAIL**.
- **Unique transaction ID duplication** → expected DB throws unique exception to prevent duplicate transactions → Actual: unique constraint prevents duplicates → **PASS**.

## Unchallenged Areas
- **Webhook payload processing security** — reason not challenged: Webhook processing logic is not implemented in this milestone (data schema only).

---

# PART 4: Verification and Suggested Fixes

## Suggested Fix Order
1. **Migrations**:
   - Change `cascadeOnDelete()` to `restrictOnDelete()` on `subscription_package_id` in `user_subscriptions` table.
   - Change `cascadeOnDelete()` to `restrictOnDelete()` on `payment_config_id` in `transactions` table.
   - Remove redundant `->index()` on `transaction_id` in `transactions` table.
2. **Models**:
   - Add `$attributes` to `UserSubscription`, `PaymentConfig`, and `Transaction` models to mirror migration defaults.
   - Update `UserSubscription::isActive()` and `User::activeSubscription()` to check that `starts_at` is in the past or null.
3. **Tests**:
   - Add test case verifying that a subscription with a future `starts_at` date is not considered active.
   - Add test cases verifying cascade delete restrictions.

## Final Verdict
**REQUEST_CHANGES** (Fix before merge)
