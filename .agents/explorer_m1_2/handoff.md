# Handoff Report

## 1. Observation
We conducted an investigation of the model and relationship design for the DB Schema & Models milestone (Milestone 1). Here are the direct observations from the codebase:

1. **User Model (`backend/app/Models/User.php`)**:
   - Extends `Illuminate\Foundation\Auth\User` (Authenticatable).
   - Currently defines `$fillable` fields: `name`, `email`, `password`, `role`, and `settings`.
   - Casts defined: `email_verified_at` => `datetime`, `password` => `hashed`, `settings` => `array`.
   - No relationships to subscriptions or transactions are defined.

2. **Existing Models in `backend/app/Models`**:
   - `LiveEvent.php`, `LiveSession.php`, `LiveSessionKeyword.php`, `LiveSessionStatsHistory.php`, `LiveStat.php`, `Product.php`.
   - All these models extend `Illuminate\Database\Eloquent\Model` directly (with the exception of `User` extending `Authenticatable`).
   - No custom base classes, common traits, or global scopes are defined or shared among these models.
   - Local scopes are used in `LiveSession.php` (`scopeForUser` and `scopeLive`).
   - `LiveEvent.php` defines a custom date format: `protected $dateFormat = 'Y-m-d H:i:s.u';` for microsecond precision.

3. **Missing Models**:
   - `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, and `Transaction` do not exist in the codebase. High-level searches for these symbols returned no results.

4. **Project Requirements (`PROJECT.md`)**:
   - Defines the architecture, API endpoints, webhook contracts, and page structure.
   - Mentions that `User` has many subscriptions and transactions.
   - Shows the interface contracts (e.g., packages fields: `id`, `name`, `price`, `duration_days`, `features`; status response: `active`, `package_id`, `expires_at`; callback endpoint: `id_user`, `sotien`).

---

## 2. Logic Chain
- Based on the requirement that `User` has many subscriptions and transactions, and that we must retrieve the user's active subscription status (consisting of `active`, `package_id`, and `expires_at`), the `User` model must define:
  1. `subscriptions()` relation (`HasMany` to `UserSubscription`).
  2. `transactions()` relation (`HasMany` to `Transaction`).
  3. `activeSubscription()` relation (`HasOne` to `UserSubscription` filtering by status and expiry date).
- Based on `PROJECT.md` contracts:
  - `SubscriptionPackage` needs to hold packages details (`name`, `price` as integer, `duration_days` as integer, and `features` cast to array).
  - `UserSubscription` needs to link a user to a package and track start/expiration dates and subscription status (`active`, `expired`, etc.).
  - `Transaction` needs to track checkout attempts (`transaction_id` as unique reference string, `user_id`, `package_id`, `amount`, `status`, `vietqr_url`, and `paid_at`).
  - `PaymentConfig` needs to store payment configurations (bank/account information, outbound webhook url, method, headers, and params templates).

---

## 3. Caveats
- Exact database tables and fields are yet to be created via migrations. The proposed model structures and types are based on the documented public API contracts in `PROJECT.md`.
- No actual DB queries were executed, as this is a read-only investigation.
- The VietQR generation format details (e.g., bank code formatting) might impose constraints on `PaymentConfig` field types, which should be validated during the API implementation phase.

---

## 4. Conclusion
We have formulated the exact Eloquent relationships to add to the `User` model, designed the four new models (`SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`), and planned their corresponding database schema. 

The proposed model implementations and the changes for the `User` model are fully detailed below.

---

## 5. Verification Method
1. **Inspecting File Existence**: Ensure files are created at:
   - `backend/app/Models/SubscriptionPackage.php`
   - `backend/app/Models/UserSubscription.php`
   - `backend/app/Models/PaymentConfig.php`
   - `backend/app/Models/Transaction.php`
2. **PHP Syntax & Relation Verification**: 
   - Run `php artisan tinker` and test model instantiations (once migrations are created and run).
   - Verify relationships:
     - `(new App\Models\User)->subscriptions()` should return `Illuminate\Database\Eloquent\Relations\HasMany`.
     - `(new App\Models\User)->transactions()` should return `Illuminate\Database\Eloquent\Relations\HasMany`.
     - `(new App\Models\User)->activeSubscription()` should return `Illuminate\Database\Eloquent\Relations\HasOne`.
     - `(new App\Models\UserSubscription)->package()` should return `Illuminate\Database\Eloquent\Relations\BelongsTo`.
     - `(new App\Models\Transaction)->user()` should return `Illuminate\Database\Eloquent\Relations\BelongsTo`.

---

## Project Coverage Report
*Understanding is coverage-based, not absolute.*
- **Active Mode**: Core Mode
- **Declared Scope**: Database Models and Relationships
- **Full Files Read**:
  - `backend/app/Models/User.php`
  - `backend/app/Models/LiveEvent.php`
  - `backend/app/Models/LiveSession.php`
  - `backend/app/Models/Product.php`
  - `backend/app/Models/LiveSessionKeyword.php`
  - `backend/app/Models/LiveSessionStatsHistory.php`
  - `backend/app/Models/LiveStat.php`
  - `PROJECT.md`
- **Directories Scanned**:
  - `backend/app/Models`
  - `backend/database/migrations`
  - `backend/tests`
- **Repo-wide Searches**: `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`
- **APIs/Contracts Checked**: `PROJECT.md`
- **DB Models Checked**: All models in `backend/app/Models`
- **Commands run**: `find_by_name`, `view_file`, `grep_search`
- **Unknowns**: None

---

## Evidence Ledger
| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|
| User Model | User model exists and does not define subscription/transaction relationships yet. | `view_file` on `backend/app/Models/User.php` | `backend/app/Models/User.php` | None | None | High | None |
| Models Design | SubscriptionPackage, UserSubscription, PaymentConfig, and Transaction models are not yet created. | `grep_search` and `find_by_name` in Models directory | None | `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction` | None | High | None |
| Base Classes/Traits | Existing models inherit from standard Eloquent `Model` (or `Authenticatable`) and use no custom base models, traits, or global scopes. | `view_file` on all model files in `backend/app/Models` | `LiveEvent.php`, `LiveSession.php`, `Product.php`, `LiveSessionKeyword.php`, `LiveSessionStatsHistory.php`, `LiveStat.php`, `User.php` | None | None | High | None |
| Project Specs | Project requirements, routes, pages, and API contracts are documented. | `view_file` on `PROJECT.md` | `PROJECT.md` | None | None | High | None |

---

## Proposed Model & Relations Code Definitions

### 1. Proposed Modifications to the `User` Model
Add these imports and relationship methods to `backend/app/Models/User.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Inside Class User:

    /**
     * Get all subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get all transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user's currently active subscription.
     */
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

### 2. Proposed Model: `SubscriptionPackage`
Path: `backend/app/Models/SubscriptionPackage.php`

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
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'features' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_days' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the subscription history for this package.
     */
    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'package_id');
    }
}
```

### 3. Proposed Model: `UserSubscription`
Path: `backend/app/Models/UserSubscription.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'package_id' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    /**
     * Helper to verify if the subscription is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
```

### 4. Proposed Model: `PaymentConfig`
Path: `backend/app/Models/PaymentConfig.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentConfig extends Model
{
    protected $fillable = [
        'provider',
        'account_number',
        'account_name',
        'bank_id',
        'webhook_url',
        'webhook_method',
        'headers_template',
        'params_template',
        'is_active',
    ];

    protected $attributes = [
        'webhook_method' => 'POST',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'headers_template' => 'array',
            'params_template' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
```

### 5. Proposed Model: `Transaction`
Path: `backend/app/Models/Transaction.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_id',
        'user_id',
        'package_id',
        'amount',
        'status',
        'vietqr_url',
        'paid_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'package_id' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Get the user who initiated the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription package associated with the transaction.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }
}
```

---

## Proposed Migration Files Outline
For consistency and seamless implementation, these are the schema blueprints:

### 1. `create_subscription_packages_table.php`
```php
Schema::create('subscription_packages', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedInteger('price');
    $table->unsignedInteger('duration_days');
    $table->json('features')->nullable();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});
```

### 2. `create_user_subscriptions_table.php`
```php
Schema::create('user_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('package_id')->constrained('subscription_packages')->cascadeOnDelete();
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->string('status')->default('active')->index();
    $table->timestamps();
});
```

### 3. `create_payment_configs_table.php`
```php
Schema::create('payment_configs', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    $table->string('account_number')->nullable();
    $table->string('account_name')->nullable();
    $table->string('bank_id')->nullable();
    $table->string('webhook_url')->nullable();
    $table->string('webhook_method')->default('POST');
    $table->json('headers_template')->nullable();
    $table->json('params_template')->nullable();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});
```

### 4. `create_transactions_table.php`
```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->string('transaction_id')->unique()->index();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('package_id')->constrained('subscription_packages')->cascadeOnDelete();
    $table->unsignedInteger('amount');
    $table->string('status')->default('pending')->index();
    $table->text('vietqr_url')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```
