## 2026-05-21T15:22:54Z
You are the implementation worker for Milestone 2.
Your working directory is: d:\Workspace\livestream\.agents\worker_m2_impl_fix
Your identity is: teamwork_preview_worker
Project root is: d:\Workspace\livestream
Original request is at: d:\Workspace\livestream\ORIGINAL_REQUEST.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Task:
1. Initialize your BRIEFING.md and progress.md in your working directory.
2. Edit d:\Workspace\livestream\backend\app\Http\Controllers\SubscriptionController.php to add a check in `checkout` for free packages (price === 0). If the user already has an entry in `user_subscriptions` table for this package (i.e. UserSubscription::where('user_id', $user->id)->where('subscription_package_id', $package->id)->exists()), return a JSON error response with status 400.
3. Edit d:\Workspace\livestream\backend\app\Http\Controllers\PaymentCallbackController.php:
   - In `handleCallback`, find the pending transaction first using:
     ```php
     $transaction = Transaction::where('user_id', $userId)
         ->where('amount', $amount)
         ->where('status', 'pending')
         ->latest()
         ->first();
     ```
     If found, resolve the package using the package ID associated with that pending transaction:
     ```php
     $package = null;
     if ($transaction) {
         $package = SubscriptionPackage::find($transaction->subscription_package_id);
     }
     if (!$package) {
         $package = SubscriptionPackage::where('price', $amount)->first();
     }
     ```
   - If `$transaction` is not found (meaning it's not pending anymore), check for a recently succeeded transaction for the same user, amount, and status='success' within the last 5 minutes to prevent duplicate callback double-crediting:
     ```php
     if (!$transaction) {
         $recentSuccess = Transaction::where('user_id', $userId)
             ->where('amount', $amount)
             ->where('status', 'success')
             ->where('updated_at', '>=', now()->subMinutes(5))
             ->latest()
             ->first();

         if ($recentSuccess) {
             return response()->json([
                 'success' => true,
                 'message' => 'Subscription upgraded successfully (duplicate callback ignored)',
             ]);
         }
     }
     ```
   - Wrap the webhook job dispatch in its own try-catch block so that any exception thrown during sync execution does not abort the callback or roll back the transaction:
     ```php
     if (! empty($activePaymentConfig->webhook_url)) {
         try {
             SendOutboundPaymentWebhookJob::dispatch($transaction->id);
         } catch (\Exception $webhookEx) {
             \Illuminate\Support\Facades\Log::error('Outbound webhook failed: ' . $webhookEx->getMessage());
         }
     }
     ```
4. Edit d:\Workspace\livestream\backend\routes\web.php:
   - In the DELETE `/packages/{package}` route, check if there are any associated subscriptions or transactions referencing the package before deleting:
     ```php
     $hasAssociations = \App\Models\UserSubscription::where('subscription_package_id', $package->id)->exists()
         || \App\Models\Transaction::where('subscription_package_id', $package->id)->exists();
     if ($hasAssociations) {
         return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đã có lịch sử đăng ký hoặc giao dịch.']);
     }
     ```
     And wrap the `$package->delete()` statement in a try-catch block for additional safety.
5. Verify the changes by running:
   cd d:\Workspace\livestream\backend
   php artisan test
6. Write a handoff.md report summarizing the changes made and the test results.
