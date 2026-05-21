## 2026-05-21T15:22:03Z
**Context**: Subscription and Payment System (Milestones 2, 3, 4, 5).
**Task**: Fix backend bugs, implement missing React frontend views, and verify the test suite.
**Target Directory**: d:\Workspace\livestream
**Domain Skill Path**: d:\Workspace\livestream\.agents\skills\laravel-best-practices\SKILL.md

### Step 1: Implement Backend Fixes
1. Edit `backend/app/Http/Controllers/PaymentCallbackController.php`:
   - In `handleCallback(Request $request)`, find the pending transaction *before* resolving the package:
     ```php
     $transaction = Transaction::where('user_id', $userId)
         ->where('amount', $amount)
         ->where('status', 'pending')
         ->latest()
         ->first();
     ```
   - Resolve the package based on the pending transaction's package ID if it exists. If not, fall back to lookup by price:
     ```php
     $package = null;
     if ($transaction && $transaction->subscription_package_id) {
         $package = SubscriptionPackage::find($transaction->subscription_package_id);
     }
     if (! $package) {
         $package = SubscriptionPackage::where('price', $amount)->first();
     }
     ```
   - Add an idempotency check for duplicate callback requests: if `$transaction` (pending) is not found, check if a success transaction for the same user and amount was processed recently (e.g. within 5 minutes). If so, return a success response immediately without double-crediting:
     ```php
     if (! $transaction) {
         $recentSuccess = Transaction::where('user_id', $userId)
             ->where('amount', $amount)
             ->where('status', 'success')
             ->where('created_at', '>=', now()->subMinutes(5))
             ->first();

         if ($recentSuccess) {
             return response()->json([
                 'success' => true,
                 'message' => 'Subscription upgraded successfully',
             ]);
         }
     }
     ```
   - Wrap the outbound webhook job dispatch `SendOutboundPaymentWebhookJob::dispatch($transaction->id)` in a `try-catch (\Throwable $e)` block to prevent synchronous queue exception propagation from failing the payment callback response:
     ```php
     if (! empty($activePaymentConfig->webhook_url)) {
         try {
             SendOutboundPaymentWebhookJob::dispatch($transaction->id);
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::error("Failed to dispatch outbound payment webhook: " . $e->getMessage());
         }
     }
     ```

2. Edit `backend/app/Http/Controllers/SubscriptionController.php`:
   - In `checkout(Request $request)`, add a check for free packages (price = 0) to block repeated checkout if the user already has any active subscription. Return a 400 Bad Request if they do:
     ```php
     if ($package->price === 0) {
         if ($user->activeSubscription) {
             return response()->json([
                 'error' => 'Bad Request',
                 'message' => 'You already have an active subscription.'
             ], 400);
         }
         ...
     }
     ```

### Step 2: Create React/Inertia Frontend Views
1. Create `backend/resources/js/Pages/Subscription/Index.tsx`:
   - Must use `AuthenticatedLayout` wrapper from `@/Layouts/AuthenticatedLayout` and `Head` from `@/InertiaJS`.
   - Must show a premium packages listing (pricing) grid with Outfit/Inter typography, matching badge indicators for the active subscription, duration, and list of features.
   - Must open a checkout modal when "Đăng ký" is clicked.
   - For free packages (price = 0), show a confirmation message and a button to POST to `/api/subscription/checkout` which activates immediately, shows success toast, and reloads the page.
   - For paid packages (price > 0), load QR dynamically by POSTing to `/api/subscription/checkout`, show a loading spinner, and render the VietQR image using the returned `vietqr_url`.
   - Parse the exact transfer code from the `vietqr_url` search params (`addInfo` parameter) and show it with a Copy button next to it. Include transfer instructions: `Prefix {userId} Suffix`.
   - Show a "Đã chuyển khoản thành công" button which reloads the page to update the active subscription status.

2. Create `backend/resources/js/Pages/Admin/Packages/Index.tsx`:
   - Must use `AdminLayout` wrapper from `@/Layouts/AdminLayout`.
   - Implement Packages CRUD UI (list, create, update, delete).
   - Show a table listing name, price, duration, and features.
   - Add form handling for creating/editing packages (validating and converting inputs, saving features as arrays of strings like `['audio_analysis']`).
   - Secure package deletion: handle errors if deleting a package fails due to active subscriptions and display the error message to the admin.

### Step 3: Verify Implementation
1. Run `php artisan test` in `backend/` to run all subscription and payment tests (including database, callback, and challenger tests).
2. Ensure 100% of tests pass cleanly.
3. Write your handoff report summarizing the changes made and test execution results.
