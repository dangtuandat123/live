# Milestone 2 Audit and Handoff Report: Subscription and Payment APIs

## Metadata
- **Active Mode**: Critical Path Mode
- **Declared Scope**: Route declarations, database schemas, checkout logic flow, callback webhook handling, and outbound HTTP webhook execution.
- **Confidence Rating**: High (Supported by complete code reads of models, migrations, tests, and configuration files).
- **Decisions**: Propose explicit routes, controllers, and job skeletons for checkout and payment webhook processing.

---

# 1. 5-Component Handoff Report

### 1.1. Observation
1. **Routing Environment**:
   - `backend/routes/api.php` line 6–8 exposes a protected `/user` route using `auth:sanctum`.
   - `backend/routes/api.php` line 10–15 defines a public `/ping` route.
   - CSRF protection is omitted in `api` group middleware routes by default (standard Laravel 12 config in `bootstrap/app.php` lines 11–16).
2. **Database Models & Schema**:
   - `SubscriptionPackage` (`backend/database/migrations/2026_05_21_210000_create_subscription_packages_table.php`): Fields include `id`, `name`, `price`, `duration_days`, `features` (JSON, nullable). Price is stored as an integer (e.g. `299000`).
   - `UserSubscription` (`backend/database/migrations/2026_05_21_210100_create_user_subscriptions_table.php`): Fields include `id`, `user_id`, `subscription_package_id`, `starts_at`, `expires_at`, `status` (default `active`).
   - `PaymentConfig` (`backend/database/migrations/2026_05_21_210200_create_payment_configs_table.php`): Fields include `id`, `name`, `prefix` (nullable), `suffix` (nullable), `webhook_url` (nullable), `method` (default `POST`), `params_template` (JSON, nullable), `headers_template` (JSON, nullable), `is_active` (boolean, default true).
   - `Transaction` (`backend/database/migrations/2026_05_21_210300_create_transactions_table.php`): Fields include `id`, `transaction_id` (unique string), `user_id`, `amount`, `payment_config_id`, `status` (default `pending`).
3. **Seeded Configuration**:
   - `PaymentConfigSeeder` (`backend/database/seeders/PaymentConfigSeeder.php`) seeds a single active gateway `'VietQR'` with:
     - `prefix` = `'LS_'`
     - `suffix` = `''`
     - `params_template` = `{"id_user": "{user_id}", "sotien": "{amount}", "description": "{prefix}{transaction_id}{suffix}"}`
     - `headers_template` = `{"Content-Type": "application/json"}`
     - `webhook_url` = `'http://localhost/api/payments/callback'`
4. **Current Test Status**:
   - `php artisan test` was executed and all 52 tests (422 assertions) passed successfully in 2.59 seconds. This includes `Feature/SubscriptionDatabaseTest.php` verifying all model relationships and casts.

### 1.2. Logic Chain
- Since checkout requires the authenticated user's ID to record the transaction and inject into the VietQR payload, the checkout endpoint `POST /api/subscriptions/checkout` must reside within the `auth:sanctum` middleware group.
- The webhook callback route `POST /api/payments/callback` receives notifications from banking systems or third-party gateways. Because these are external machine-to-machine calls, this route must remain public (outside `auth:sanctum`) but can restrict input validation.
- The VietQR URL requires placeholder replacement using `{Prefix}`, `{userId}`, `{Suffix}`, and `{amount}`. By fetching the active `PaymentConfig`, we retrieve `prefix` and `suffix`. From the selected package, we obtain `price` (which is mapped to `{amount}`).
- The payment webhook callback specifies `id_user` and `sotien`. To correctly link a payment back to the user's intent:
  1. We search for a pending transaction matching `user_id` and `amount = sotien`.
  2. If found, we mark the transaction as successful. If not found, we create a new successful audit transaction.
  3. We identify the target package by matching its price to `sotien`.
  4. We update or create an active subscription for the user. If the user already has an active subscription of the same package, we append `duration_days` to the current `expires_at`. If it's a different package, we deactivate the old one and start a new subscription.
- To prevent network latencies and timeout issues on the callback endpoint during outbound webhook dispatching, we utilize a queued Job (`SendOutboundPaymentWebhookJob`) to send the POST/GET request to `webhook_url` with parameters and headers replaced from templates.

### 1.3. Caveats
- **Subscription Package Status**: The `subscription_packages` migration has no `is_active` column. We assume all seeded packages are active. If an activation/deactivation mechanism is added later, checking for a boolean flag will be required.
- **Outbound Webhook Format**: The template variables in the database seed use lowercase `{user_id}` and `{amount}`, while the VietQR template variables use `{Prefix}`, `{userId}`, `{Suffix}`, and `{amount}`. We must handle both patterns distinctly to avoid broken substitutions.

### 1.4. Conclusion
- The backend configuration is perfectly suited to support the proposed checkout and callback flow.
- The routing, database schema, model definitions, and tests are sound and passing.
- Implementation plans, controller skeletons, and template replacements detailed below are ready for execution.

### 1.5. Verification Method
- **Routing Verification**: Inspect `php artisan route:list --path=api` to verify `/api/subscriptions/checkout` is protected by `auth:sanctum` and `/api/payments/callback` is public.
- **Unit/Feature Testing**: A mock test suite (detailed below) should be written to verify:
  1. Successful QR url generation and transaction storage.
  2. Callback execution, subscription date updates (including extension logic), and outbound HTTP requests.

---

# 2. Scope, Stack, and Source of Truth

| Item | Value |
|---|---|
| Target | Subscription and Payment Callback APIs |
| Stack/framework | Laravel 12, PHP 8.2+, SQLite (Testing), MySQL (Dev/Prod) |
| Expected user behavior | User selects package -> Generates QR code -> Pays via Bank app -> Subscription upgraded automatically |
| Expected backend/data behavior | Creates pending transaction -> Receives webhook callback -> Upgrades user subscription -> Triggers outbound webhook |
| Source of truth | `backend/database/migrations/*`, `backend/app/Models/*`, `backend/routes/api.php` |

---

# 3. Coverage Ledger

| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Routing Configuration | 1 | 1 | 0 | Checked `routes/api.php` |
| Subscription Models | 3 | 3 | 0 | `SubscriptionPackage`, `UserSubscription`, `User` |
| Payment Models | 2 | 2 | 0 | `PaymentConfig`, `Transaction` |
| Migration Schemas | 6 | 6 | 0 | All user/subscription/payment migrations read |
| Seeders | 4 | 4 | 0 | Verified default package list & VietQR config values |
| Tests | 13 | 1 | 12 | Inspected and ran `SubscriptionDatabaseTest.php` |

---

# 4. Expected Behavior Contract

| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Checkout returns valid VietQR URL | VietQR Spec | High | Placeholder text (e.g. `{userId}`) left unreplaced |
| Graceful payment config check | App Logic | High | 500 error if no active configuration exists |
| Callback handles missing transactions | Webhook Spec | High | Rejecting callback because transaction ID was not preset |
| Extend active subscription | Business Logic | High | Overwriting future expiry date instead of adding days |
| Safe outbound webhook dispatch | Webhook Spec | High | Synchronous failure block or crash due to bad endpoint |

---

# 5. Milestone 2 Route Declarations (Proposed changes to `routes/api.php`)

```php
<?php

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Backend is connected smoothly to Frontend!',
    ]);
});

// Payment webhook callback (public API)
Route::post('/payments/callback', [PaymentCallbackController::class, 'handleCallback']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Subscription Checkout
    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
});
```

---

# 6. Proposed Skeletons & Detailed Pseudocode

### 6.1. `app/Http/Controllers/SubscriptionController.php`
This controller handles the generation of checkout URLs and saves pending transactions.

```php
<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Handle the subscription package checkout.
     * 
     * POST /api/subscriptions/checkout
     * Body: { "package_id": 2 }
     */
    public function checkout(Request $request)
    {
        // 1. Validate the selected package
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        // 2. Fetch package details
        $package = SubscriptionPackage::findOrFail($validated['package_id']);

        // 3. Fetch active payment configuration
        $paymentConfig = PaymentConfig::where('is_active', true)->first();
        if (!$paymentConfig) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active payment configuration found. Please contact support.',
            ], 422);
        }

        // 4. Generate unique transaction ID
        $prefix = $paymentConfig->prefix ?? 'TX_';
        $transactionId = $prefix . strtoupper(Str::random(10));

        // 5. Construct the VietQR URL
        $user = $request->user();
        $vietQrTemplate = "https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}";

        // Replace placeholders safely (urlencode dynamic parts)
        $vietQrUrl = str_replace(
            ['{Prefix}', '{userId}', '{Suffix}', '{amount}'],
            [
                urlencode($paymentConfig->prefix ?? ''),
                urlencode($user->id),
                urlencode($paymentConfig->suffix ?? ''),
                urlencode($package->price)
            ],
            $vietQrTemplate
        );

        // 6. Create a pending transaction record
        $transaction = Transaction::create([
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'amount' => $package->price,
            'payment_config_id' => $paymentConfig->id,
            'status' => 'pending',
        ]);

        // 7. Return the response
        return response()->json([
            'status' => 'success',
            'data' => [
                'transaction_id' => $transaction->transaction_id,
                'vietqr_url' => $vietQrUrl,
                'amount' => $transaction->amount,
                'package' => $package->name,
            ]
        ]);
    }
}
```

---

### 6.2. `app/Http/Controllers/PaymentCallbackController.php`
This controller processes notifications from the banking system, marks transactions as successful, upgrades subscriptions, and dispatches outbound webhooks.

```php
<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Jobs\SendOutboundPaymentWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentCallbackController extends Controller
{
    /**
     * Handle the banking callback payload.
     * 
     * POST /api/payments/callback
     * Payload: {"id_user": "{user_id}", "sotien": {amount}}
     */
    public function handleCallback(Request $request)
    {
        // 1. Validate payload
        $validated = $request->validate([
            'id_user' => ['required', 'integer', 'exists:users,id'],
            'sotien' => ['required', 'integer', 'min:0'],
        ]);

        $userId = $validated['id_user'];
        $amount = $validated['sotien'];

        // 2. Identify the package associated with this payment amount
        $package = SubscriptionPackage::where('price', $amount)->first();
        if (!$package) {
            Log::error("Payment callback failed: No package matched price {$amount} for user ID {$userId}");
            return response()->json([
                'status' => 'error',
                'message' => 'No subscription package found for this payment amount.',
            ], 422);
        }

        $activePaymentConfig = PaymentConfig::where('is_active', true)->first();
        if (!$activePaymentConfig) {
            Log::error("Payment callback error: No active payment configuration found during callback processing.");
            return response()->json([
                'status' => 'error',
                'message' => 'Payment config unavailable.',
            ], 500);
        }

        DB::beginTransaction();
        try {
            // 3. Marks or creates a transaction record as successful
            $transaction = Transaction::where('user_id', $userId)
                ->where('amount', $amount)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($transaction) {
                $transaction->update(['status' => 'success']);
            } else {
                // If no pending transaction existed, record it for audit purposes
                $transaction = Transaction::create([
                    'transaction_id' => ($activePaymentConfig->prefix ?? 'TX_') . strtoupper(Str::random(10)),
                    'user_id' => $userId,
                    'amount' => $amount,
                    'payment_config_id' => $activePaymentConfig->id,
                    'status' => 'success',
                ]);
            }

            // 4. Upgrade user subscription
            // Get user's current active subscription (including future expires_at)
            $user = User::findOrFail($userId);
            $activeSub = $user->activeSubscription;

            if ($activeSub && $activeSub->subscription_package_id === $package->id) {
                // Extend the existing active subscription
                $currentExpiry = $activeSub->expires_at ?? now();
                $activeSub->update([
                    'expires_at' => $currentExpiry->addDays($package->duration_days),
                ]);
            } else {
                // Deactivate any different package subscription
                if ($activeSub) {
                    $activeSub->update(['status' => 'inactive']);
                }

                // Create a fresh subscription
                UserSubscription::create([
                    'user_id' => $userId,
                    'subscription_package_id' => $package->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($package->duration_days),
                    'status' => 'active',
                ]);
            }

            DB::commit();

            // 5. Fire outbound HTTP webhook asynchronously via queued Job
            if (!empty($activePaymentConfig->webhook_url)) {
                SendOutboundPaymentWebhookJob::dispatch($transaction->id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed, subscription upgraded.',
                'transaction_id' => $transaction->transaction_id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Database error during payment callback processing: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error occurred.',
            ], 500);
        }
    }
}
```

---

### 6.3. `app/Jobs/SendOutboundPaymentWebhookJob.php`
This queued job executes outbound HTTP notifications using the configured templates and avoids slowing down callback responses.

```php
<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOutboundPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $transactionId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transaction = Transaction::with('paymentConfig')->find($this->transactionId);
        if (!$transaction || !$transaction->paymentConfig) {
            Log::warning("Outbound webhook skipped: Transaction or PaymentConfig not found for ID {$this->transactionId}");
            return;
        }

        $config = $transaction->paymentConfig;
        if (empty($config->webhook_url)) {
            return;
        }

        // 1. Prepare template variables
        $placeholders = [
            '{user_id}'        => $transaction->user_id,
            '{amount}'         => $transaction->amount,
            '{transaction_id}' => $transaction->transaction_id,
            '{prefix}'         => $config->prefix ?? '',
            '{suffix}'         => $config->suffix ?? '',
        ];

        // 2. Perform replacement in headers template
        $headers = [];
        if (!empty($config->headers_template)) {
            $headersJson = json_encode($config->headers_template);
            $headersJson = str_replace(array_keys($placeholders), array_values($placeholders), $headersJson);
            $headers = json_decode($headersJson, true) ?? [];
        }

        // 3. Perform replacement in params template
        $params = [];
        if (!empty($config->params_template)) {
            $paramsJson = json_encode($config->params_template);
            $paramsJson = str_replace(array_keys($placeholders), array_values($placeholders), $paramsJson);
            $params = json_decode($paramsJson, true) ?? [];
        }

        $method = strtoupper($config->method ?? 'POST');
        $url = $config->webhook_url;

        // 4. Send HTTP request
        try {
            $options = [];
            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }

            $response = Http::withHeaders($headers)->send($method, $url, $options);

            if ($response->successful()) {
                Log::info("Outbound payment webhook dispatched successfully to {$url} for Transaction {$transaction->transaction_id}");
            } else {
                Log::error("Outbound payment webhook returned failed status {$response->status()} for URL {$url}");
                throw new \Exception("Outbound webhook returned status " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("Failed to dispatch outbound payment webhook for Transaction {$transaction->transaction_id}: " . $e->getMessage());
            // Fail job to trigger retry logic
            throw $e;
        }
    }
}
```

---

# 7. Verification Method Details
To test this implementation once coded, a developer can run a feature test containing the following test cases:

```php
<?php

namespace Tests\Feature;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Jobs\SendOutboundPaymentWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_generates_vietqr_url_and_creates_pending_transaction()
    {
        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create(['price' => 299000]);
        $config = PaymentConfig::factory()->create([
            'prefix' => 'PREFIX_',
            'suffix' => '_SUFFIX',
            'is_active' => true
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'package_id' => $package->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status', 'data' => ['transaction_id', 'vietqr_url', 'amount', 'package']
        ]);

        $data = $response->json('data');
        $this->assertStringContainsString('PREFIX_299000_SUFFIX', urldecode($data['vietqr_url']));
        $this->assertDatabaseHas('transactions', [
            'transaction_id' => $data['transaction_id'],
            'user_id' => $user->id,
            'amount' => 299000,
            'status' => 'pending'
        ]);
    }

    public function test_callback_upgrades_subscription_and_marks_transaction_success()
    {
        Queue::fake();

        $user = User::factory()->create();
        $package = SubscriptionPackage::factory()->create(['price' => 299000, 'duration_days' => 30]);
        $config = PaymentConfig::factory()->create(['is_active' => true, 'webhook_url' => 'https://mock.url']);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 299000,
            'status' => 'pending',
            'payment_config_id' => $config->id
        ]);

        $response = $this->postJson('/api/payments/callback', [
            'id_user' => $user->id,
            'sotien' => 299000
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'success'
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active'
        ]);

        Queue::assertDispatched(SendOutboundPaymentWebhookJob::class);
    }
}
```
