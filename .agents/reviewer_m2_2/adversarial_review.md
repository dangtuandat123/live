# Adversarial Review (Challenge Report)

## Challenge Summary

**Overall risk assessment**: CRITICAL

The current implementation relies on two flawed assumptions:
1. That package prices are globally unique identifiers for subscription products.
2. That webhook job execution is completely decoupled from the HTTP response flow and cannot impact the client-facing callback status.

Under adversarial or edge-case conditions, both assumptions fail, leading to data corruption (upgrading to the wrong subscription package) and API transaction mismatch (bank receiving a 500 error for a successfully upgraded subscription).

---

## Challenges

### [Critical] Challenge 1: Pricing Collision Vulnerability
- **Assumption challenged**: The assumption that matching `SubscriptionPackage::where('price', $amount)->first()` is safe because each price uniquely identifies a package.
- **Attack scenario**: A business administrator creates two subscription packages with identical prices (e.g. a standard monthly plan and a special promotional 2-month plan both priced at 150,000 VND). The user checks out the promotional plan. The bank sends a callback with `sotien = 150000`. The controller matches the first package (the standard monthly plan) and overrides the transaction and subscription data, resulting in the user receiving the standard plan instead of the promotional plan.
- **Blast radius**: High. Direct financial/feature discrepancy. Users pay for one product but receive a different one.
- **Mitigation**: Retrieve the package ID from the pending transaction associated with the user and the amount. Only fall back to price-based matching if no matching transaction is found.

### [High] Challenge 2: Sync Webhook Failure Propagation
- **Assumption challenged**: The assumption that queueing webhooks ensures they do not block or interfere with the API request lifecycle.
- **Attack scenario**: In a deployment where the queue driver is `sync` (which is standard for simple or synchronous task workers), or if the queue backend is down, any network failure/timeout or exception in `SendOutboundPaymentWebhookJob` bubbles up to the `PaymentCallbackController`. 
- **Blast radius**: High. Although the database commit succeeds, the controller returns a 500 response, signaling failure to the banking API. The bank will retry the callback, causing duplicate requests, while the database records show a successful upgrade.
- **Mitigation**: Wrap the `SendOutboundPaymentWebhookJob::dispatch(...)` statement in its own try-catch block to log any dispatch or inline execution exceptions, preventing them from propagating. Alternatively, use Laravel's `afterResponse()` job dispatching mechanism to defer execution until after the HTTP response has been sent to the bank.

---

## Stress Test Results

- **Multiple packages with same price** → Expect subscription matching the checked-out package → Got the first package matching the price (Package ID 1 instead of 2) → **FAIL**
- **Outbound Webhook server down / timeout under sync queue** → Expect callback to return 200 since payment succeeded → Got 500 response → **FAIL**

---

## Unchallenged Areas

- **VietQR image rendering** — Reason not challenged: VietQR image generation is delegated to an external static API (`https://api.vietqr.io/`), which is out of scope for direct local source code verification.
