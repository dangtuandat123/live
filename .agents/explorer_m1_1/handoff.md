# Milestone 1: DB Schema & Models Investigation Report

## 1. Observation
We explored the existing Laravel database schema, migration configurations, and Eloquent models in the `backend` directory.

### Target Files Inspected
1. **Migrations**:
   - `backend/database/migrations/0001_01_01_000000_create_users_table.php`
   - `backend/database/migrations/2026_05_20_141717_add_role_to_users_table.php`
   - `backend/database/migrations/2026_05_20_163406_add_settings_to_users_table.php`
   - `backend/database/migrations/2026_05_21_000001_create_products_table.php`
   - `backend/database/migrations/2026_05_21_000002_create_live_sessions_table.php`
   - `backend/database/migrations/2026_05_21_000003_create_live_session_products_table.php`
   - `backend/database/migrations/2026_05_21_110822_create_live_session_stats_history_table.php`
2. **Models**:
   - `backend/app/Models/User.php`
   - `backend/app/Models/Product.php`
   - `backend/app/Models/LiveSession.php`
3. **Configurations**:
   - `backend/config/database.php`

### Observed Patterns
- **Database Driver**: Default connection configured to `sqlite` (with `mysql`, `mariadb`, `pgsql`, and `sqlsrv` drivers configured).
- **Primary Keys**: Defined using `$table->id()`, which uses `bigIncrements`.
- **Foreign Keys**: Defined using `$table->foreignId('column')->constrained()->cascadeOnDelete();` for related records (e.g. `user_id` on `products`, `live_sessions`).
- **Money/Prices**: Defined using `$table->unsignedInteger('price')->default(0);` (e.g., in `products` migration), casting to `integer` on the model. This avoids decimals and fits integer-based currencies (like VND or cents in USD).
- **JSON columns**: Used for configuration templates and features (e.g. `settings` JSON in `users` table).
- **Eloquent Casts**: Casts are declared using the PHP method syntax `protected function casts(): array` returned values (Laravel 11/12 convention).

### Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Models | 3 | 3 | 0 | Checked `User`, `Product`, `LiveSession` models fully. |
| Migrations | 19 | 8 | 11 | Focused on Core user/product/session tables. |
| Configs | 1 | 1 | 0 | Database configurations. |

### Evidence Ledger
| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|
| User Model | User has `settings` JSON field and `role` string field | `backend/app/Models/User.php` lines 28-34 | Yes | N/A | `php artisan test` | High | None |
| Price Datatype | Prices are unsigned integers in migrations | `2026_05_21_000001_create_products_table.php` line 16 | Yes | `grep` for "price" | N/A | High | None |
| Foreign Keys | Cascade delete is standard for user relationships | `2026_05_21_000002_create_live_sessions_table.php` line 13 | Yes | N/A | N/A | High | None |

---

## 2. Logic Chain

### Reasoning on Schema Choices
1. **Subscription Packages (`subscription_packages`)**:
   - Must hold product-like features and costs. We use `unsignedInteger` for `price` to match the project's standard price conventions.
   - Features will be stored in a `json` column, permitting flexible schema lists (e.g., limit values, tier names, support levels).
   - `duration_days` will be an `unsignedInteger` to denote the valid subscription span.

2. **User Subscriptions (`user_subscriptions`)**:
   - Represents a junction between a user and their package.
   - `user_id` must cascade delete since the subscription is owned by the user.
   - `starts_at` and `expires_at` track active dates. `expires_at` is nullable to support lifetime/unlimited subscription models.
   - `status` represents subscription state. We apply a database index on it for performant active status lookups. We also add a compound index on `[user_id, status]` to quickly retrieve active subscriptions for any user.

3. **Payment Configs (`payment_configs`)**:
   - Contains webhook receiver info and configurations.
   - Templates for parameters and headers are configured as `json` (or null if unused), giving full flexibility for webhook configuration.
   - An active flag `is_active` (boolean, indexed) is added to easily turn gateway configs on/off.

4. **Transactions (`transactions`)**:
   - Records payments. `user_id` uses cascade delete.
   - `payment_config_id` uses a nullable relation with **`nullOnDelete()`**. If an admin deletes a payment configuration/method, we must preserve the transaction log history for audits, accounting, and security reasons.
   - `status` utilizes index (expected values: `pending`, `success`, `failed`).
   - `transaction_id` (external gateway transaction ID) is indexed and nullable, as it may not be present when the user first redirects to pay, and is only updated via webhooks.
   - To link payments to subscriptions, we add a proposed field `subscription_package_id` to `transactions`.

### Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Delete payment config | Accounting rules | High | Cascade deleting the transaction records, losing historical payment data. |
| Retrieve active subscription | User dashboard | High | Full scan of `user_subscriptions` without compound index `[user_id, status]`. |
| Zero / negative payments | Data validation | High | Allowing negative values in `transactions.amount` or `subscription_packages.price`. |

### Invariant and State Matrix
| Invariant / Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Status active lifespan | `user_subscriptions` | Check subscription when current time exceeds `expires_at` | Database constraint/query scope | Correctly marked as expired. |
| Transaction status updates | `transactions` | Gateway sending fake or duplicate success webhooks | Transaction status check | Transition from success back to pending is blocked by logic layer. |
| Positive prices | `subscription_packages` | Negative package price injection | `unsignedInteger` type | Database blocks negative input. |

---

## 3. Caveats
- **Soft Deletes**: Standard Laravel soft delete traits (`SoftDeletes`) are not currently present in `User` or `Product` tables in the existing code. Therefore, we have omitted them from the proposed tables to maintain parity, but we highlight soft deletes as an optional upgrade.
- **Webhook Payload Template Size**: We propose the `json` type for webhook templates. While SQLite and MySQL support JSON natively, older systems might require `text`. Given Laravel 12 requirement standards, JSON is completely safe.

---

## 4. Conclusion & Proposed Designs

### Proposed Migrations

We propose the creation of four migration files located at `backend/database/migrations/`:

#### A. `2026_05_21_210000_create_subscription_packages_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('duration_days');
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
```

#### B. `2026_05_21_210100_create_user_subscriptions_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('expires_at');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
```

#### C. `2026_05_21_210200_create_payment_configs_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('prefix', 50)->nullable();
            $table->string('suffix', 50)->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('method', 20)->default('POST');
            $table->json('params_template')->nullable();
            $table->json('headers_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_configs');
    }
};
```

#### D. `2026_05_21_210300_create_transactions_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_config_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_package_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('transaction_id', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

---

### Proposed Models Design

#### A. `SubscriptionPackage` model (`backend/app/Models/SubscriptionPackage.php`)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_days' => 'integer',
            'features' => 'array',
        ];
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
```

#### B. `UserSubscription` model (`backend/app/Models/UserSubscription.php`)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'subscription_package_id' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    /**
     * Scope to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
    }
}
```

#### C. `PaymentConfig` model (`backend/app/Models/PaymentConfig.php`)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentConfig extends Model
{
    protected $fillable = [
        'name',
        'prefix',
        'suffix',
        'webhook_url',
        'method',
        'params_template',
        'headers_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'params_template' => 'array',
            'headers_template' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

#### D. `Transaction` model (`backend/app/Models/Transaction.php`)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'payment_config_id',
        'subscription_package_id',
        'amount',
        'status',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'payment_config_id' => 'integer',
            'subscription_package_id' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentConfig(): BelongsTo
    {
        return $this->belongsTo(PaymentConfig::class);
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }
}
```

---

### Suggested User Model Changes (`backend/app/Models/User.php`)

Add relationship methods to `User` class to link them to subscriptions and transactions:

```php
<<<<
    public function getSettingsWithDefaults(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }
}
====
    public function getSettingsWithDefaults(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get the user's active subscription (if any).
     */
    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', now());
            })
            ->latestOfMany();
    }

    /**
     * Get the user's transaction history.
     */
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
>>>>
```

---

## 5. Verification Method

Once the implementer writes these files, the database schema can be compiled and validated using standard commands.

### Specific Verification Commands
1. **Run Migrations**:
   Run the migration tool to verify that SQLite/MySQL correctly creates tables with the specified constraints without syntax errors:
   ```bash
   php artisan migrate
   ```
2. **Rollback Migrations**:
   Verify that rollback functions reverse modifications successfully and completely:
   ```bash
   php artisan migrate:rollback
   ```
3. **Execute Test Suite**:
   Verify that existing application logic is unaffected by the migrations:
   ```bash
   php artisan test
   ```

### Checkpoints for Verification
- Confirm that the `transactions` table contains `payment_config_id` set to `NULL` when the associated `payment_config` record is deleted.
- Verify that `is_active` in `payment_configs` has a valid index.
- Confirm compound index on `user_subscriptions` (`['user_id', 'status']`) is created.
