# Quality Review Report

## Review Summary

**Verdict**: REQUEST_CHANGES

This implementation contains a critical logic error that breaks package resolution when multiple packages share the same price, and a high-severity reliability bug where failing outbound webhooks crash the payment callback response. Due to these issues, the codebase cannot be approved in its current state.

---

## Findings

### [Critical] Finding 1: Incorrect package resolution when packages share the same price

- **What**: The payment callback matches subscription packages solely by price and overwrites the transaction's package ID with the first matching package found.
- **Where**: `app/Http/Controllers/PaymentCallbackController.php`, line 33 and line 62.
- **Why**: 
  1. At checkout, the user selects a specific package, generating a pending transaction with `subscription_package_id` pointing to that package.
  2. During callback handling, the code queries the database:
     ```php
     $package = SubscriptionPackage::where('price', $amount)->first();
     ```
     If multiple packages have the same price (e.g. a 30-day basic package and a 60-day promotional package both priced at 150k), this query always returns the first package matched in the database.
  3. The controller then updates the transaction's package ID with this globally-matched package:
     ```php
     $transaction->update([
         'status' => 'success',
         'subscription_package_id' => $package->id,
     ]);
     ```
     This overwrites the correct `subscription_package_id` recorded at checkout, resulting in the user receiving the wrong subscription package (wrong duration, wrong features).
- **Suggestion**:
  Instead of resolving the package globally by price before checking the transaction, the controller should first query the pending transaction using `user_id` and `amount`:
  ```php
  $transaction = Transaction::where('user_id', $userId)
      ->where('amount', $amount)
      ->where('status', 'pending')
      ->latest()
      ->first();
  ```
  - If a pending transaction is found: Use `$transaction->subscription_package_id` to resolve the package.
  - If no pending transaction is found (direct deposit case): Query the package by price as a fallback.

---

### [High] Finding 2: Outbound webhook failures crash the callback response under sync queue configuration

- **What**: Outbound webhook HTTP requests are dispatched synchronously within the main execution thread when using the `sync` queue driver. If the webhook destination fails, the exception bubbles up and crashes the callback, returning a 500 error.
- **Where**: `app/Http/Controllers/PaymentCallbackController.php`, lines 107-111 and lines 117-124.
- **Why**: 
  1. The callback handler dispatches `SendOutboundPaymentWebhookJob::dispatch($transaction->id)` inside the `try` block, after committing the DB transaction.
  2. If the queue connection is set to `sync` (common in local setups or direct execution), the job runs immediately.
  3. Inside `SendOutboundPaymentWebhookJob::handle()`, the HTTP request uses `->throw()`. If the request fails (due to timeout, connection failure, offline environment, or a 4xx/5xx response from the webhook receiver), an exception is thrown.
  4. This exception bubbles out of the job, is caught by the controller's catch block, and returns a `500 Internal Server Error` response to the payment gateway.
  5. Since the database transaction was already committed, the user's subscription IS upgraded, but the payment gateway receives a 500 error, leading to desynchronization of transaction states between our system and the bank.
- **Suggestion**:
  Wrap the job dispatch in its own try-catch block to suppress webhook failures from impacting the callback response, or dispatch using `dispatch($transaction->id)->afterResponse()` to ensure the callback response is sent to the gateway before the webhook job executes.

---

## Verified Claims

- Listing packages endpoint returns correct packages → verified via `SubscriptionPaymentTest::test_packages_listing_returns_seeded_packages` → PASS
- Checkout endpoint generates dynamic VietQR URL with correct params/prefix/suffix → verified via `SubscriptionPaymentTest::test_checkout_paid_package_generates_vietqr_url_and_creates_pending_transaction` → PASS
- Checkout free package activates subscription immediately → verified via `SubscriptionPaymentTest::test_checkout_free_package_activates_instantly_without_vietqr` → PASS
- Outbound Webhook job replaces placeholders correctly in headers/params → verified via `SubscriptionPaymentTest::test_outbound_webhook_job_sends_http_request_with_correct_replacements` → PASS

---

## Coverage Gaps

- **Direct payment callback robustness** — Risk level: Low — The system currently assumes a package exists for any arbitrary price callback, falling back to 422 only if no package matches. If a user pays an arbitrary amount not matching any package, it correctly returns 422, but does not record a failed transaction. Recommended: Accept this behavior as is, but log anomalous amounts.

---

## Unverified Items

- **Webhook SSL/TLS handling** — Reason not verified: Real SSL/TLS handshakes cannot be verified under the `CODE_ONLY` offline environment.
