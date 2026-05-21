## 2026-05-21T16:22:12Z

You are the Worker. Your working directory is d:\Workspace\livestream\.agents\worker_m2.
You must complete Milestone 2: Backend API & Gating Logic.

Please perform the following file modifications:
1. `backend/app/Models/User.php`:
   - Add `getSubscriptionFeatures(): array` method merging default Free limits with package features JSON. Default limits are:
     `'limit_streams' => 1, 'max_duration_hours' => 1, 'ai_credits' => 1000, 'audio_analysis' => false, 'export_leads' => false`
   - Add `resolveActiveSubscription(): ?UserSubscription` method to automatically subscribe the user to the Free package if they have no subscription records at all.
2. `backend/app/Http/Controllers/SubscriptionController.php`:
   - In the `checkout` method for free package (price === 0), wrap the check and creation inside a transaction and use `lockForUpdate()` on the user subscription / user to prevent concurrent checkout race conditions and Free package checkout abuse.
3. `backend/app/Http/Controllers/LiveSessionController.php`:
   - In `store`, call `resolveActiveSubscription()` to ensure the user has a subscription, then check the `limit_streams` limit. If the user's active connecting/live sessions count meets or exceeds the limit (and limit is not -1), throw `\Illuminate\Validation\ValidationException::withMessages(['tiktok_username' => ['Bạn đã đạt giới hạn số lượng livestream active tối đa của gói dịch vụ (' . $limitStreams . ').']])`.
   - In `show` and `fetchEvents`, check if the livestream duration (from `started_at` in hours) meets or exceeds the subscription's `max_duration_hours`. If it does, stop the livestream: call `$this->tiktokService->stopSession()`, update status to `ended`, `ended_at` to now, `duration_seconds` to elapsed seconds, and set `error_message` to `'Phiên livestream đã tự động kết thúc do vượt quá thời lượng tối đa cho phép của gói dịch vụ (' . $maxDurationHours . ' giờ).'`
4. `backend/app/Jobs/AnalyzeCommentsJob.php`:
   - In `handle()`, check if the user's active subscription's `used_ai_credits` has exceeded `ai_credits` (if not -1). If so, update the session status to `error`, set `error_message` to `'Đã hết tín dụng AI của gói dịch vụ.'`, and return/stop.
   - After successfully processing the batch, increment `used_ai_credits` in the user's active subscription (if it exists) by the number of comments in the batch.
   - In the audio capture logic, only call `$tiktokService->getSnapshot()` if `$features['audio_analysis']` is true. If false, bypass it (keep `$audioB64 = null`).
5. `backend/app/Http/Middleware/HandleInertiaRequests.php`:
   - In `share()`, share `auth.subscription` with Inertia:
     `'active' => (bool) $request->user()->activeSubscription?->isActive(),`
     `'package_id' => $request->user()->activeSubscription?->subscription_package_id,`
     `'package_name' => $request->user()->activeSubscription?->package?->name ?? 'Free',`
     `'expires_at' => $request->user()->activeSubscription?->expires_at?->toISOString(),`
     `'used_ai_credits' => $request->user()->activeSubscription?->used_ai_credits ?? 0,`
     `'features' => $request->user()->getSubscriptionFeatures(),`
     Ensure you call `resolveActiveSubscription()` beforehand to auto-subscribe them to Free if they have no subscription yet.
6. `backend/routes/web.php`:
   - In the `/subscription` route, resolve the user's active subscription using `resolveActiveSubscription()`.
   - Query and return the user's payment transactions history as `transactions` prop, mapping: `id`, `transaction_id`, `package_name`, `amount`, `status`, and formatted `created_at`.
   - Update `activeSubscription` prop to include `used_ai_credits` and `features`.

Once you have implemented these backend changes, run `php artisan test` in the `backend` folder to ensure all tests still pass successfully.
Create a handoff report in `d:\Workspace\livestream\.agents\worker_m2\handoff.md` detailing your changes and verification tests results.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.
