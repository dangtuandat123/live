# Handoff Report — Milestone 1: DB Schema & Models Review

## 1. Observation
- In `backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php`, the foreign key `subscription_package_id` is defined as:
  ```php
  $table->foreignId('subscription_package_id')->constrained('subscription_packages')->cascadeOnDelete();
  ```
- In `backend/database/migrations/2026_05_21_210300_create_transactions_table.php`, the foreign keys `user_id` and `payment_config_id` are defined as:
  ```php
  $table->foreignId('user_id')->constrained()->cascadeOnDelete();
  $table->foreignId('payment_config_id')->constrained('payment_configs')->cascadeOnDelete();
  ```
  And `transaction_id` is defined as:
  ```php
  $table->string('transaction_id')->unique()->index();
  ```
- In `backend/app/Models/UserSubscription.php`, the `isActive` function is defined as:
  ```php
  public function isActive(): bool
  {
      return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture());
  }
  ```
- In `backend/app/Models/User.php`, the `activeSubscription` relation is defined as:
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
- Running `php artisan test` in `d:\Workspace\livestream\backend` succeeded with 48 passed tests (414 assertions), including 4 database tests.

## 2. Logic Chain
1. **Cascade Delete Risks**: By using `cascadeOnDelete()` on `subscription_package_id` in `user_subscriptions` and `payment_config_id` in `transactions`, deleting a SubscriptionPackage or PaymentConfig configuration record will delete all associated historical customer subscription records and payment records from the database. Financial and audit logs should never be automatically deleted on configuration changes. Therefore, this poses a major data integrity risk.
2. **Future Active Subscription Bypass**: By only checking if `status === 'active'` and `expires_at` is null or in the future inside `UserSubscription::isActive()` and `User::activeSubscription()`, any subscription scheduled for a future start date (where `starts_at` is set to a future timestamp) will be considered active immediately. This is a logical bypass.
3. **Missing Defaults in Model Attributes**: Migration files specify defaults (e.g. `$table->string('status')->default('active')` or `$table->string('status')->default('pending')`), but these defaults are not mirrored in the `$attributes` property of their respective models. According to the project style guide (`rules/migrations.md` §16), defaults should be mirrored so that unpersisted Eloquent models display correct default values.
4. **Redundant Indexes**: Defining `$table->string('transaction_id')->unique()->index()` creates both a unique index and a standard non-unique index on `transaction_id`. Unique constraints already create a database-level unique index, so the extra `->index()` call is redundant.

## 3. Caveats
- Since this is Milestone 1, there are no endpoints, controllers, or forms using these models yet. We assume that future controllers will perform request validation and authorization to restrict mass-assigning attributes like `status` in `UserSubscription`.
- No actual production database exists yet, so changes to migrations are safe to perform since the project is in early development.

## 4. Conclusion
The implementation of the database schemas, models, relations, factories, seeders, and tests is complete and correct for basic usage, but has critical design flaws concerning data integrity (cascade deletes on transaction audit logs), logical gaps (future-dated subscriptions bypassing the active check), style guide violations (missing model default attributes), and redundant indexing.
The verdict is **REQUEST_CHANGES** (Fix before merge).

## 5. Verification Method
- Run `php artisan test` in `d:\Workspace\livestream\backend` to verify existing tests pass.
- Inspect the file `d:\Workspace\livestream\.agents\reviewer_m1_1\review_report.md` for the full detailed audit, quality, and adversarial review.
- Inspect the database tables structure or migration files to verify:
  - If `cascadeOnDelete()` has been replaced with `restrictOnDelete()` or similar for configuration tables.
  - If `starts_at` is checked in active subscription logic.
