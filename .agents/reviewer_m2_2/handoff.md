# Handoff Report

## 1. Observation
- Executed `php artisan test` in `d:\Workspace\livestream\backend` and observed failure in the challenger test suite:
  ```
  FAILED  Tests\Feature\SubscriptionPaymentChallengerTest > callback same price different package bug
  Expected subscription to be for Package B (Promo Package (60 Days)), but got Package ID: 1
  Failed asserting that 1 matches expected 2.
  at tests\Feature\SubscriptionPaymentChallengerTest.php:76
  ```
  and occasionally:
  ```
  Expected response status code [200] but received 500.
  Failed asserting that 500 is identical to 200.
  at tests\Feature\SubscriptionPaymentChallengerTest.php:63
  ```
- Checked `phpunit.xml` and noted the queue connection configuration at line 30:
  ```xml
  <env name="QUEUE_CONNECTION" value="sync"/>
  ```
- Inspected package lookup logic in `app/Http/Controllers/PaymentCallbackController.php` lines 33-39:
  ```php
  // Match package price
  $package = SubscriptionPackage::where('price', $amount)->first();
  if (! $package) {
      return response()->json([
          'error' => 'Unprocessable Content',
          'message' => "No subscription package found for price {$amount}.",
      ], 422);
  }
  ```
  And lines 53-63:
  ```php
  // Find pending transaction
  $transaction = Transaction::where('user_id', $userId)
      ->where('amount', $amount)
      ->where('status', 'pending')
      ->latest()
      ->first();

  if ($transaction) {
      $transaction->update([
          'status' => 'success',
          'subscription_package_id' => $package->id,
      ]);
  }
  ```
  And lines 107-111:
  ```php
  // Dispatch outbound webhook if URL exists
  if (! empty($activePaymentConfig->webhook_url)) {
      SendOutboundPaymentWebhookJob::dispatch($transaction->id);
  }
  ```

## 2. Logic Chain
- **Package Overwrite Bug**:
  1. The user checks out a specific package (e.g. Package B with ID 2). A pending transaction is created in the database containing `subscription_package_id => 2`.
  2. When the callback is received, the controller matches the package solely by the payment amount using `SubscriptionPackage::where('price', $amount)->first()`. If another package (Package A with ID 1) has the same price, the controller resolves `$package` to Package A.
  3. The controller then updates the transaction's package ID to `$package->id` (ID 1) and creates the active subscription for `$package->id` (ID 1).
  4. This ignores the checked-out package ID saved in the transaction and overwrites it, assigning the incorrect subscription type and duration to the user.
- **Webhook Exception Propagation**:
  1. Under testing/local environments with `QUEUE_CONNECTION` set to `sync`, jobs are executed inline and synchronously within the dispatch call.
  2. If the outbound webhook request fails (due to timeout or connection failure under restricted offline testing environments), `SendOutboundPaymentWebhookJob` throws an exception via `->throw()`.
  3. This exception is caught by the controller's catch block, which executes `DB::rollBack()` (even though `DB::commit()` has already committed the subscription upgrade) and returns a 500 error response.
  4. This causes the payment gateway to receive a failure response (500) while the user's subscription in the database remains successfully upgraded, creating a state desynchronization.

## 3. Caveats
- No caveats.

## 4. Conclusion
- The implementation has a critical correctness bug (pricing collisions resolving to the wrong package) and a high-severity reliability bug (synchronous webhook execution crashes payment callbacks). 
- Merging is **BLOCKED**; changes are requested.

## 5. Verification Method
- Run all tests in the backend folder:
  ```powershell
  cd d:\Workspace\livestream\backend
  php artisan test
  ```
- Confirm that `SubscriptionPaymentChallengerTest` passes when the issues are fixed.
- Verify that `PaymentCallbackController.php` resolves packages using the pending transaction's package ID when available, and handles job dispatch failures gracefully.
