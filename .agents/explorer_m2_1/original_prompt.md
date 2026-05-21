## 2026-05-21T15:04:23Z
**Context**: We are at Milestone 2: Backend APIs & Callback.
**Task**: Explore checkout controller logic and VietQR URL generation.
Specifically:
1. Review the required VietQR format:
   `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
   where `{Prefix}` and `{Suffix}` are retrieved from the active `payment_configs` and `{userId}` is the logged-in user's ID.
2. Plan the checkout logic:
   - Fetch the selected subscription package and ensure it is active.
   - Fetch the active payment configuration. If no configuration is active, how should we handle it (e.g., fall back to defaults or throw an exception)?
   - Generate a unique transaction ID (e.g. prefix + random string).
   - Construct the VietQR URL by replacing the placeholders `{Prefix}`, `{userId}`, `{Suffix}`, and `{amount}` (price) with active config parameters and the logged-in user's ID.
   - Create a pending transaction record storing the amount, package, user, and VietQR URL.
3. Write your findings to your folder.
**Completion criteria**: Detailed handoff.md with checkout controller pseudocode and VietQR generation plan.

## 2026-05-21T15:04:23Z [Updated Task]
**Context**: We are at Milestone 2: Backend APIs & Callback.
**Task**: Explore callback webhook handling and outbound webhook dispatching.
Specifically:
1. Plan the POST `/api/payments/callback` endpoint:
   - Receives payload: `{"id_user": "{user_id}", "sotien": {amount}}`. Note: this endpoint is public.
   - Upgrades the user's subscription: calculates starts_at/expires_at based on duration_days of the matched package. If the user already has an active subscription, extend the expiration date; otherwise, starts_at is now and expires_at is now + duration_days. Set subscription status to active.
   - Updates/creates transaction record as success.
2. Plan the outbound HTTP webhook dispatch:
   - Fetch the active payment config.
   - Parse `params_template` and `headers_template` replacing placeholders `{user_id}`, `{amount}`, and `{transaction_id}` with their actual runtime values.
   - Send the HTTP request using the configured method (POST, GET, PUT) and headers to the `webhook_url`.
3. Check how external HTTP requests are made in this codebase (e.g., using Laravel's `Http` client) and suggest appropriate timeouts.
4. Write your findings to your folder.
**Completion criteria**: Detailed handoff.md with callback handler pseudocode and outbound webhook implementation plan.

## 2026-05-21T15:05:00Z
**Context**: We are at Milestone 2: Backend APIs & Callback.
**Task**: Explore routing and controller conventions in the project.
Specifically:
1. Locate and read `backend/routes/api.php` to understand route declaration patterns, middleware groupings, and authentication setups.
2. Locate existing controllers to see how dependency injection, requests, validation, and JSON responses are handled.
3. Plan the route definitions and controller skeletons for:
   - **GET `/api/subscription/packages`**: list active packages.
   - **GET `/api/subscription/status`**: check logged-in user's subscription status.
   - **POST `/api/subscription/checkout`**: initiate checkout (returns `vietqr_url` and `transaction_id`).
   - **POST `/api/payments/callback`**: public callback webhook upgrading subscriptions.
4. Write your findings to your folder.
**Completion criteria**: Detailed handoff.md with proposed route structures and controller file locations.
