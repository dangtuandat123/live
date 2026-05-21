## 2026-05-21T15:09:07Z
**Context**: We are at Milestone 2: Backend APIs & Callback.
**Task**: Implement the backend APIs, payment callback handler, queued outbound webhook job, and automated feature tests.
**Working Directory**: d:\Workspace\livestream\.agents\worker_m2_impl
**Domain Skill Path**: d:\Workspace\livestream\.agents\skills\laravel-best-practices\SKILL.md

**MANDATORY INTEGRITY WARNING**:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

**Steps to execute**:
1. Read the laravel-best-practices skill instructions at the path above (use `view_file` on `SKILL.md` first).
2. Read the project scope and findings in `PROJECT.md`, `ORIGINAL_REQUEST.md` and `d:\Workspace\livestream\.agents\explorer_m2_all\handoff.md`.
3. Modify the existing database migration `2026_05_21_210300_create_transactions_table.php` to add:
   - `subscription_package_id` as foreignId referencing `subscription_packages` table with `restrictOnDelete()` and nullable.
   - `vietqr_url` as string of length 500 and nullable.
4. Update `App\Models\Transaction.php` and `Database\Factories\TransactionFactory.php` to support these new fields (including `subscription_package_id` and `vietqr_url` in `$fillable`, defining the `subscriptionPackage` or `package` relation, and updating factory attributes).
5. Implement `App\Http\Controllers\SubscriptionController.php`:
   - `index`: lists all active/seeded packages (for endpoint `/api/subscription/packages`).
   - `status`: checks/returns the authenticated user's active subscription status (endpoint `/api/subscription/status`).
   - `checkout`: handles checkout initiation, creating a pending transaction and returning a dynamic VietQR URL (endpoint `/api/subscription/checkout`).
     - If the user selects a package with `price = 0` (Free package), bypass the VietQR generation and instantly activate the subscription.
     - If there is no active `PaymentConfig` in the database, return a `503 Service Unavailable` response instead of falling back to unsafe hardcoded defaults.
     - Otherwise, generate a unique transaction ID. Parse the active payment config prefix/suffix and construct the VietQR URL:
       `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
       Ensure URL encoding of dynamic placeholders. Create a pending Transaction and return the transaction ID and VietQR URL.
6. Implement `App\Http\Controllers\PaymentCallbackController.php`:
   - Accept POST request `/api/payments/callback` with JSON payload `{"id_user": "{user_id}", "sotien": {amount}}`.
   - Match the payment `sotien` to a `SubscriptionPackage` price. If no package matches, return `422 Unprocessable Content`.
   - Find the active `PaymentConfig`. If none exists, abort with `500 Internal Server Error`.
   - Look for a pending transaction for this user and amount. If found, mark it as successful. If not found, create a new successful audit transaction.
   - Upgrade user subscription:
     - Check if the user already has an active subscription of the *same* package. If yes, extend its `expires_at` date by adding `duration_days` from the current expiry date.
     - If the user has an active subscription of a *different* package (or no active subscription), deactivate the old subscription (set status to `'inactive'`) and create/activate a new subscription starting from now.
   - If the active `PaymentConfig` has a `webhook_url`, dispatch the outbound webhook asynchronously using a queued Laravel Job (`SendOutboundPaymentWebhookJob`).
7. Implement the queued Laravel Job `App\Jobs\SendOutboundPaymentWebhookJob.php`:
   - Fetch the transaction and its payment config.
   - Parse placeholders in `params_template` and `headers_template`: `{user_id}`, `{amount}`, `{transaction_id}`, `{prefix}`, and `{suffix}` (handling both lowercase and uppercase variations, e.g. `{userId}` or `{amount}`).
   - Send the outbound HTTP request (GET, POST, or PUT as configured) to `webhook_url` with custom headers and parameters. Use `Http::timeout(10)->connectTimeout(3)` for safety.
8. Register routes in `backend/routes/api.php`:
   - Public: `/api/subscription/packages` (get, listing) and `/api/payments/callback` (post, webhook callback).
   - Protected by `auth:sanctum`: `/api/subscription/status` (get, status check) and `/api/subscription/checkout` (post, initiate checkout).
9. Implement comprehensive E2E tests in `backend/tests/Feature/SubscriptionPaymentTest.php`:
   - Verify packages listing and status endpoints.
   - Verify that checkout with free package activates subscription immediately without VietQR.
   - Verify checkout with paid package generates the correct VietQR URL and creates a pending transaction.
   - Verify that callback processes payment, updates transaction status to success, and upgrades/extends the user subscription correctly.
   - Verify callback triggers outbound webhooks (mock HTTP requests using `Http::fake()` and verify they match templates/placeholders).
10. Run `php artisan migrate:fresh --seed` (working directory: `d:\Workspace\livestream\backend`) to ensure the DB schema is updated cleanly.
11. Run `php artisan test` (working directory: `d:\Workspace\livestream\backend`) and ensure both `SubscriptionDatabaseTest.php` and `SubscriptionPaymentTest.php` pass successfully.
12. Write a detailed handoff report in your folder `handoff.md`.
