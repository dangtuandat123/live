# Handoff Report: Checkout Controller Logic & VietQR Generation

## 1. Observation
I have performed a static codebase investigation on the workspace. The following files and configurations were inspected:

*   **Database Migrations**:
    *   `backend/database/migrations/2026_05_21_210000_create_subscription_packages_table.php` (Defines `id`, `name`, `price`, `duration_days`, `features`, `timestamps`).
    *   `backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php` (Defines `user_id`, `subscription_package_id`, `starts_at`, `expires_at`, `status`, `timestamps`).
    *   `backend/database/migrations/2026_05_21_210200_create_payment_configs_table.php` (Defines `name`, `prefix`, `suffix`, `webhook_url`, `method`, `params_template`, `headers_template`, `is_active`, `timestamps`).
    *   `backend/database/migrations/2026_05_21_210300_create_transactions_table.php` (Defines `transaction_id`, `user_id`, `amount`, `payment_config_id`, `status` defaults to `'pending'`).
*   **Eloquent Models**:
    *   `backend/app/Models/SubscriptionPackage.php`
    *   `backend/app/Models/UserSubscription.php` (Includes `isActive()` status check and `package()` relationship).
    *   `backend/app/Models/PaymentConfig.php`
    *   `backend/app/Models/Transaction.php` (Includes `user()` and `paymentConfig()` relationships).
    *   `backend/app/Models/User.php` (Includes `activeSubscription()` relationship with start/expiration checks).
*   **Database Seeders**:
    *   `backend/database/seeders/SubscriptionPackageSeeder.php` (Seeds "Free" [0 VND], "Pro" [299k VND], and "Enterprise" [999k VND] packages).
    *   `backend/database/seeders/PaymentConfigSeeder.php` (Seeds a "VietQR" active configuration with prefix `"LS_"` and empty suffix `""`).
*   **Routes**:
    *   `backend/routes/api.php` (Contains `/user` with `auth:sanctum` and a `/ping` route).
    *   `backend/routes/web.php` (Contains main user application and admin panel routes).

Verbatim excerpt from `backend/database/migrations/2026_05_21_210300_create_transactions_table.php` (lines 14-22):
```php
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->foreignId('payment_config_id')->constrained('payment_configs')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
```

Verbatim excerpt from `backend/app/Models/User.php` (lines 94-104):
```php
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }
```

---

## 2. Logic Chain

1.  **Requirement**: "Create a pending transaction record storing the amount, package, user, and VietQR URL."
    *   **Observation**: The existing `transactions` table (defined in `2026_05_21_210300_create_transactions_table.php`) has no `subscription_package_id` or `vietqr_url` column.
    *   **Inference**: To satisfy this requirement, the `transactions` table structure must be extended. I propose adding:
        *   `subscription_package_id` (foreign key pointing to `subscription_packages`) to track which package is requested.
        *   `vietqr_url` (text column) to persist the generated QR URL.
2.  **Requirement**: "Fetch the active payment configuration. If no configuration is active, how should we handle it?"
    *   **Observation**: The system seeds the `payment_configs` table with `is_active = true`.
    *   **Analysis of Alternatives**:
        *   *Alternative A (Fallback to hardcoded defaults)*: If database config is disabled, fallback to predefined values. **Risk**: If the fallback uses incorrect account/prefix settings, payments will be routed incorrectly or fails callback parsing, leading to manual verification headaches and revenue leakage.
        *   *Alternative B (Throw an exception / Abort)*: Immediately stop flow and return an HTTP error state (e.g. `503 Service Unavailable` or `422 Unprocessable Entity`) indicating no payment methods are active.
        *   *Decision*: **Alternative B** is selected. For financial and payment integrations, correctness and explicit setup are superior to implicit fallback behavior.
3.  **Requirement**: "Fetch the selected subscription package and ensure it is active."
    *   **Observation**: Currently `subscription_packages` migration has no `is_active` status flag.
    *   **Inference**: I propose adding `is_active` (boolean, default `true`, indexed) to `subscription_packages` table to allow admins to toggle packages on/off. If a requested package is not active, the system aborts with a `422 Unprocessable Entity` response.
4.  **Requirement**: "Generate a unique transaction ID (e.g. prefix + random string)."
    *   **Observation**: The `payment_configs` table has a `prefix` (e.g. `"LS_"`).
    *   **Inference**: To prevent collision, we should generate the ID as `prefix` combined with a randomized alphanumeric uppercase string (e.g. 8-10 characters), using a `do-while` loop to guarantee database uniqueness.
5.  **Requirement**: "Construct the VietQR URL by replacing the placeholders."
    *   **Observation**: The target format is:
        `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
    *   **Inference**: Replacing these placeholders exactly is done via `str_replace`. Since it is a query string, the replacements for `{Prefix}` and `{Suffix}` should be URL-encoded using `rawurlencode` to handle any special characters safely.
    *   **Edge Case Identification**: The "Free" package has `price = 0`. Generating a VietQR URL for 0 VND is invalid in banking systems. The checkout controller should handle `price = 0` by bypassing payment QR generation, instantly activating the Free subscription for the user, and returning a `completed` transaction status.

---

## 3. Caveats
*   This report assumes the exact base template `970416-11183041-rdXzPHV.jpg` and owner name `DANG TUAN DAT` are static configuration variables or constants. In a production environment, the bank account details and owner name should be configured dynamically inside the `payment_configs` table or `.env` rather than hardcoded in the codebase.
*   The callback and outbound webhook integration logic is not yet implemented (Milestone 2 next phase).

---

## 4. Conclusion & Action Plan

### A. Proposed Database Schema Changes
To implement this safely and maintain high auditability, the following changes are proposed:

#### 1. Modify `transactions` Migration & Model
Add `subscription_package_id` and `vietqr_url` to the migration and fillable array.

```php
// Proposed changes in database/migrations/2026_05_21_210300_create_transactions_table.php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->string('transaction_id')->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subscription_package_id')->constrained('subscription_packages')->restrictOnDelete();
    $table->unsignedInteger('amount');
    $table->foreignId('payment_config_id')->constrained('payment_configs')->restrictOnDelete();
    $table->string('status')->default('pending')->index();
    $table->text('vietqr_url')->nullable(); // Stores generated QR url
    $table->timestamps();
});
```

Update `app/Models/Transaction.php`:
```php
protected $fillable = [
    'transaction_id',
    'user_id',
    'amount',
    'payment_config_id',
    'subscription_package_id',
    'vietqr_url',
    'status',
];

public function package(): BelongsTo
{
    return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
}
```

#### 2. Modify `subscription_packages` Migration & Model
Add `is_active` to control package availability.

```php
// Proposed addition in database/migrations/2026_05_21_210000_create_subscription_packages_table.php
$table->boolean('is_active')->default(true)->index();
```

---

### B. Proposed Checkout Routing
Register the checkout route in `backend/routes/api.php` under Sanctum middleware:
```php
use App\Http\Controllers\CheckoutController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/subscription/checkout', [CheckoutController::class, 'checkout'])->name('subscription.checkout');
});
```

---

### C. Proposed CheckoutController Implementation (Pseudocode)

```php
<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutController extends Controller
{
    /**
     * Initiate checkout session and generate VietQR URL.
     */
    public function checkout(Request $request): JsonResponse
    {
        // 1. Validation
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        // 2. Fetch package and verify availability/active status
        $package = SubscriptionPackage::findOrFail($validated['package_id']);
        if (!$package->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected subscription package is currently inactive.'
            ], 422);
        }

        $user = $request->user();

        // 3. Handle Zero-price package bypass logic (Free package)
        if ($package->price === 0) {
            $subscription = DB::transaction(function () use ($user, $package) {
                // Deactivate any existing active subscriptions if necessary
                $user->subscriptions()->where('status', 'active')->update(['status' => 'expired']);

                // Create a direct active subscription
                return UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_package_id' => $package->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($package->duration_days),
                    'status' => 'active',
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription activated successfully (Free package bypass).',
                'subscription' => $subscription
            ], 200);
        }

        // 4. Fetch the active payment configuration
        $paymentConfig = PaymentConfig::where('is_active', true)->first();
        if (!$paymentConfig) {
            // Throw exception or return 503 instead of falling back to unsafe hardcoded values
            return response()->json([
                'status' => 'error',
                'message' => 'Payment service is temporarily unavailable. No active configuration found.'
            ], 503);
        }

        // 5. Generate a unique transaction ID
        $prefix = $paymentConfig->prefix ?? '';
        $transactionId = $this->generateUniqueTransactionId($prefix);

        // 6. Construct the VietQR URL
        $vietqrUrl = $this->generateVietQrUrl($paymentConfig, $user->id, $package->price);

        // 7. Store pending transaction record
        $transaction = Transaction::create([
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'amount' => $package->price,
            'payment_config_id' => $paymentConfig->id,
            'subscription_package_id' => $package->id,
            'vietqr_url' => $vietqrUrl,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'transaction_id' => $transaction->transaction_id,
            'vietqr_url' => $transaction->vietqr_url,
        ], 201);
    }

    /**
     * Generate unique transaction ID.
     */
    protected function generateUniqueTransactionId(string $prefix): string
    {
        do {
            // Generates an uppercase 8-character random string prepended with prefix
            $id = $prefix . Str::upper(Str::random(8));
        } while (Transaction::where('transaction_id', $id)->exists());

        return $id;
    }

    /**
     * Generate dynamic VietQR URL based on payment config parameters.
     */
    protected function generateVietQrUrl(PaymentConfig $config, int $userId, int $amount): string
    {
        $prefix = $config->prefix ?? '';
        $suffix = $config->suffix ?? '';

        $templateUrl = 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';

        return str_replace(
            ['{Prefix}', '{userId}', '{Suffix}', '{amount}'],
            [rawurlencode($prefix), $userId, rawurlencode($suffix), $amount],
            $templateUrl
        );
    }
}
```

---

## 5. Verification Method

### A. Unit / Feature Tests Plan
To verify the correctness of the checkout process, the following feature test cases must be implemented in `backend/tests/Feature/CheckoutTest.php`:

1.  `test_guest_cannot_checkout()`: Ensure that unauthenticated requests to `POST /api/subscription/checkout` are rejected with `401 Unauthorized`.
2.  `test_checkout_validates_package_id()`: Ensure validating missing or invalid `package_id` returns `422 Unprocessable Entity`.
3.  `test_checkout_fails_when_package_inactive()`: Set `is_active` to `false` for a package and ensure checkout fails with `422`.
4.  `test_checkout_fails_when_no_active_payment_config()`: Disable all payment configs and verify the API returns `503 Service Unavailable`.
5.  `test_checkout_success_generates_correct_vietqr_url()`: Verify a successful checkout generates a unique transaction ID, inserts a pending transaction into the database with the correct relations, and returns the expected VietQR URL replacing placeholders correctly.
6.  `test_checkout_zero_price_bypasses_vietqr()`: Verify checkout for a Free (0 VND) package immediately activates a subscription without writing a pending VietQR payment transaction.

### B. Verification Command
To run the verification test suite, the worker should execute:
```bash
php artisan test --filter=CheckoutTest
```
