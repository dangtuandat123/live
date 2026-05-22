## 2026-05-22T10:13:18Z

You are a worker agent. Your task is to fix the findings identified in the Project Quality Review report.

## Target Files to Modify:
1. Controller: `d:\Workspace\livestream\backend\app\Http\Controllers\LiveSessionController.php`
2. Agent: `d:\Workspace\livestream\backend\app\Ai\Agents\LiveSessionAnalyzer.php`
3. View: `d:\Workspace\livestream\backend\resources\js\Pages\Lives\Show.tsx`
4. Test: `d:\Workspace\livestream\backend\tests\Feature\LiveSessionAiInsightsTest.php`

## Detailed Implementation Requirements:

1. **Backend Throttling & Credit Gating in LiveSessionController::refreshInsights**:
   - **Cache Throttle Check**: Before calling the AI service, check if the cache key `live_session_{$liveSession->id}_last_insight_time` is set. If the elapsed time is less than 30 seconds, calculate the remaining seconds `$secondsRemaining` and return:
     `response()->json(['error' => "Vui lòng đợi {$secondsRemaining} giây trước khi yêu cầu làm mới tiếp theo."], 429)`
   - **Credit Limit Check**: Retrieve `$user = $request->user();`, resolve active subscription `$activeSub = $user->resolveActiveSubscription();`, get subscription features `$features = $user->getSubscriptionFeatures();`, and retrieve the AI credits limit `$aiCreditsLimit = $features['ai_credits'] ?? 1000;`. If `$aiCreditsLimit !== -1` and `$activeSub` is not null, verify if `$activeSub->used_ai_credits >= $aiCreditsLimit`. If true, return:
     `response()->json(['error' => 'Đã hết tín dụng AI của gói dịch vụ.'], 402)`
   - **Exception Handling**: Wrap `$runware->chatJson(...)` in a `try-catch (\Throwable $e)` block. Log the error using `Log::error(...)` and return:
     `response()->json(['error' => 'Dịch vụ AI hiện đang bận. Vui lòng thử lại sau.'], 500)`
   - **Increment Credit Usage**: Upon a successful Runware AI response, if `$activeSub` is not null, increment `$activeSub->used_ai_credits` by `count($comments)` (the number of comments analyzed) and save.

2. **Agent Prompt Instruction Update in LiveSessionAnalyzer**:
   - In `LiveSessionAnalyzer::instructions()`, modify the system prompt description of the alert `type` attribute so it strictly tells the AI to return one of the four types defined in TS: `'danger'`, `'warning'`, `'info'`, or `'success'`.
   - Update: `type: loại cảnh báo (chỉ chọn một trong các giá trị: 'danger', 'warning', 'info', 'success')`.

3. **Frontend Error Handling in Show.tsx**:
   - In the `handleRefresh` function of the `AIInsightsPanel` component, update the error handling in the `else` block when the response is not OK:
     ```typescript
     const errData = await res.json().catch(() => ({}));
     toast.error(errData.error || 'Không thể làm mới dữ liệu AI.');
     ```

4. **Test Suite updates in tests/Feature/LiveSessionAiInsightsTest.php**:
   - Add feature tests to cover:
     - Throttling: manual refresh returns HTTP 429 if called within 30 seconds.
     - Credit Gate: manual refresh returns HTTP 402 if `used_ai_credits >= ai_credits`.
     - Exception Handling: manual refresh returns HTTP 500 if `chatJson` throws a RuntimeException.
     - Credit Increment: manual refresh increments the user's active subscription `used_ai_credits` by the analyzed comments count.

5. **Verification**:
   - Run `php artisan test` in `d:\Workspace\livestream\backend` to ensure all tests pass.
   - Run `npm run build` in `d:\Workspace\livestream\backend` to ensure Vite successfully bundles frontend code.

## Workspace & Output:
- Working directory: `d:\Workspace\livestream\.agents\worker_fix_findings_1`.
- Write your status updates, command outputs, and handoff report in `d:\Workspace\livestream\.agents\worker_fix_findings_1\handoff.md`.
