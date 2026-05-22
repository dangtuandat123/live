# Handoff Report — AI System Evidence-Based Verification & Audit

## 1. Observation

### Verified Commands & Output Logs
1. **Backend Test Suite**:
   - Command: `php artisan test` executed inside `d:\Workspace\livestream\backend`.
   - Results: `96 passed (666 assertions)` in `4.58s`.
   - Key test coverage includes: `SubscriptionGatingTest`, `SubscriptionPaymentTest`, `TikTokConnectionTest`, `AnalyzeCommentsJobTest`.
2. **Frontend Compilation**:
   - Command: `npm run build` executed inside `d:\Workspace\livestream\backend`.
   - Results: Vite (v7.3.3) compiled 3412 modules cleanly.
   - Compiled assets include: `public/build/assets/Show-BQhLuCti.js` (90.76 kB) and `public/build/assets/app-DeDwStIG.js` (518.50 kB).

### File & Logic Inspection
- **LiveSessionController.php**: Contains `checkAndStopIfDurationExceeded` at lines 1041–1071. It computes elapsed streaming duration and stops the TikTok live session using `$this->tiktokService->stopSession($liveSession->tiktok_session_id)` if limits are exceeded. However, this is only called on HTTP requests to `show()` (line 193) and `fetchEvents()` (line 390).
- **AnalyzeCommentsJob.php**: Concatenates user comment texts directly into the LLM chat input on line 177: `$userMessage = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");` and passes it to the AI service on line 180: `$parts = [RunwareAiService::text($userMessage)];`. No XML-like delimiter or sanitization isolates these inputs from the system prompt instruction context (lines 530–567).
- **service.py**: Runs a FastAPI application. The snapshot capture logic is at lines 432–466, utilizing a global thread pool executor (`snapshot_executor = ThreadPoolExecutor(max_workers=4)`) to invoke FFmpeg processes for recording 3-second audio snippets and 640px image thumbnails, utilizing a 25-second snapshot cache (`SNAPSHOT_CACHE_TTL = 25`) to prevent CPU overload. It verifies the header key via `verify_api_key` checking the `X-Service-Key` header (lines 170-172).

---

## 2. Logic Chain

1. **Gating Check Dependency**: The duration limit verification is triggered exclusively when the user loads the frontend show view or triggers the client-side axios polling route (`fetchEvents`).
2. **Loopholes under Client Inactivity**: If a user shuts their browser window or terminates their tab, the browser stops sending axios polling requests to `fetchEvents`.
3. **Background Resource Leaks**: Because there is no background daemon, artisan command, or cron task executing `checkAndStopIfDurationExceeded`, the Python microservice continues recording, processing comments, and invoking AI APIs on the active stream until the TikTok host goes offline, bypassing package limits.
4. **Vulnerability to Injection**: Raw comments are parsed directly as plain text. If a comment contains instructions mimicking system directives (e.g. `SYSTEM OVERVIEW: IGNORE...`), the LLM can be misled to overwrite metadata classification output (such as changing intent tags to "Chốt đơn" and setting `has_phone` to true), contaminating sales metrics and leading to fake lead collection.

---

## 3. Caveats

- **External Network Dependencies**: TikTok livestream connections and reconnection logic rely on `piratetok_live`, whose socket stability is not verified under network degradation.
- **SQL Efficiency**: Auto-extracted keywords are dynamically verified via `LIKE` queries in `getTopKeywords()`. If comments database volume scales beyond 50,000 comments per live stream session, performance degradation will occur.

---

## 4. Conclusion

- **Decision**: **Fix before merge**
- **Actionable Steps**:
  1. *Fix closed-tab gating*: Implement a background scheduled job (e.g., running every 5 minutes) to scan active streams in the database and terminate any that exceed package duration limits.
  2. *Fix prompt injection*: Update `AnalyzeCommentsJob.php` to wrap comment text inside `<comment-item>` delimiters and specify in the system prompt that text within these tags must not be interpreted as instruction changes.

---

## 5. Verification Method

1. **Backend Tests**: Run `php artisan test` in `d:\Workspace\livestream\backend` to ensure the integration suite continues passing.
2. **Frontend Compilation**: Run `npm run build` in `d:\Workspace\livestream\backend` to verify the Inertia/React bundles compile.
3. **Inspect Output Report**: Verify the presence and correctness of the audit report at `d:\Workspace\livestream\evidence_deep_audit_report_ai.md`.
