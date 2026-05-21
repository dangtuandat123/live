## 2026-05-21T15:05:56Z
**Context**: We are at Milestone 2: Backend APIs & Callback.
**Task**: Perform a comprehensive read-only exploration and planning for the backend subscription and payment APIs.
Specifically:
1. Locate and read `backend/routes/api.php` to understand route declarations, middleware groups, and auth settings.
2. Locate existing controllers to see the project's coding conventions, responses, and dependency injection.
3. Review the required VietQR format:
   `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
   where `{Prefix}` and `{Suffix}` are retrieved from the active `payment_configs` and `{userId}` is the logged-in user's ID.
4. Plan the checkout logic:
   - Fetch the selected subscription package and ensure it is active.
   - Fetch the active payment configuration. If no configuration is active, handle it gracefully.
   - Generate a unique transaction ID.
   - Construct the VietQR URL by replacing the placeholders.
   - Create a pending transaction record.
5. Plan the payment callback webhook logic:
   - Accepts a JSON payload: `{"id_user": "{user_id}", "sotien": {amount}}`.
   - Upgrades user subscription (status, starts_at, expires_at based on duration_days).
   - Marks/creates a transaction as successful.
   - Fires outbound HTTP webhooks using configured method, headers, and params, replacing placeholders `{user_id}`, `{amount}`, and `{transaction_id}`.
6. Write your findings to d:\Workspace\livestream\.agents\explorer_m2_all\handoff.md.

**Completion criteria**: Detailed handoff.md containing controller skeletons, pseudocode, and specific plans for the routes, checkout, and callback handling.
