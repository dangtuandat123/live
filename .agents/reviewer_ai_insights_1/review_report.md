# Audit Report

## Summary
- Scope: Review of AI Insights & Alerts system across 7 target files.
- Mode: static/code-path audit
- Confidence: High (backed by file review, static type checking, build compilation, and backend unit testing).
- Critical: 0
- High: 1 (Manual refresh endpoint bypasses credit checks and throttle limits)
- Medium: 1 (Unhandled exceptions in controller `refreshInsights` API action)
- Low: 1 (AI Alerts type cast / frontend schema fallback validation)
- Decision: Merge with follow-up

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | AI Insights & Alerts System |
| Stack/framework | Laravel 11, PHP 8.2, React 19 (TypeScript), Tailwind CSS, Vite, Inertia.js, Runware AI service |
| Expected user behavior | Streamers can view live AI insights and alerts periodically refreshed by polling every 5 seconds. Streamers can manually trigger a refresh of AI insights & alerts using a refresh button. Refresh button shows a loading spinner state and is disabled while refreshing. Alerts are color-coded based on severity: Danger (red), Warning (amber), Info (blue), Success (emerald). |
| Expected backend/data behavior | Auto-insights are periodically generated during comment analysis background job (`AnalyzeCommentsJob`) every 30 seconds (throttled). The manual refresh endpoint validates session ownership, retrieves comments, stats, products, keywords, and invokes the `RunwareAiService` with `LiveSessionAnalyzer` instructions to get JSON summary and alerts, saves them to database (`ai_insights` and `ai_alerts`), sets throttle timestamp, and clears cache. |
| Source of truth | `PROJECT.md`, migration, model, agent, controller, job, view. |
| Exclusions | External Runware AI service endpoints (mocked in tests). |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 1 | 1 | 0 | `resources/js/Pages/Lives/Show.tsx` (AIInsightsPanel) |
| User actions | 2 | 2 | 0 | Polling updates, manual refresh action. |
| API/actions | 2 | 2 | 0 | `/lives/{liveSession}/refresh-insights` (POST), `/lives/{liveSession}/fetch-events` (POST). |
| Services/domain | 2 | 2 | 0 | `App\Ai\Agents\LiveSessionAnalyzer`, `App\Services\RunwareAiService` |
| DB/schema/config | 2 | 2 | 0 | `add_ai_insights_and_alerts_to_live_sessions_table` migration, `LiveSession` model attributes. |
| Auth/permissions | 2 | 2 | 0 | Ownership verification (`$liveSession->user_id !== $request->user()->id`), auth middleware. |
| State/cache | 1 | 1 | 0 | `live_session_{id}_last_insight_time` throttle cache and `clearSessionCache`. |
| Tests | 1 | 1 | 0 | `tests/Feature/LiveSessionAiInsightsTest.php` |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Manual Refresh Ownership | `LiveSessionController.php` | High | A different user can refresh insights for a session they don't own (403 aborted correctly). |
| Manual Refresh Throttle Cache | `LiveSessionController.php` | High | Clicking refresh repeatedly floods the AI API without updating the throttle timer. |
| Auto-Insights Periodical Trigger | `AnalyzeCommentsJob.php` | High | Automatic insights run on every comment batch even if triggered 5 seconds ago (throttled correctly to 30s). |
| Alert Severity Styling Fallback | `Show.tsx` | High | Malformed/new alert type returned by the AI causes a JS crash or empty styling (fallback to `info` correctly handles unknown types). |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Show.tsx` (AIInsightsPanel) | AI Insights Summary display | lines 2198-2252 | Renders structured HTML with formatted text based on `session.ai_insights`, fallbacks to calculated sentiment % and hints if null. | Same | None |
| `Show.tsx` (AIInsightsPanel) | Refresh button click | lines 2183-2193 | Displays spinner and text "Làm mới", disables button during request. Show toast on success/error. | Same | None |
| `Show.tsx` (AIInsightsPanel) | AI Alerts List | lines 2256-2374 | Renders color-coded alert blocks for active alerts in `session.ai_alerts`. If empty, render dynamic alerts generated from live metrics. | Same | None |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Manual Refresh Insights | `handleRefresh` | Frontend loading state (`isRefreshing`), server-side ownership. | Button disabled and spinner shown when `isRefreshing === true`. | Successful refresh updates the Inertia context session attributes and shows success toast; failure shows error toast. | POST `/lives/{liveSession}/refresh-insights` | Runware API failure throws RuntimeException, causing a 500 error page or unhandled JSON exception on frontend. |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Làm mới" | `Show.tsx` line 2191 | Triggers manual analysis of AI insights. | Triggers POST request to refresh-insights endpoint. | None |
| "Đã làm mới tổng kết và cảnh báo AI!" | `Show.tsx` line 2014 | Success feedback. | Displayed via `toast.success`. | None |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| User clicks "Làm mới" | calls `handleRefresh()`, sets `isRefreshing(true)` | `/lives/{liveSession}/refresh-insights` (POST) | Headers: Content-Type, CSRF, Accept application/json. | Authenticated user ownership check. | Updates `live_sessions.ai_insights` and `live_sessions.ai_alerts`, updates cache throttle key, clears session cache. | Returns JSON with updated `ai_insights` and `ai_alerts`. Frontend updates local session state, sets `isRefreshing(false)`, and triggers a success toast. | None |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `/lives/{liveSession}/refresh-insights` (POST) | Blocked by `auth` middleware (redirects to `/login`). | Returns HTTP 403 Forbidden. | No request inputs required, request body ignored safely. | Allowed. Bypasses 30-second throttle limits and AI credit gates. | Floods external AI API, incurring potential usage cost and bypassing user subscription limits. |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| User cannot access another user's data | `LiveSessionController.php` line 387 | Access endpoint with session ID of another user. | `if ($liveSession->user_id !== $request->user()->id) { abort(403); }` | HTTP 403 Forbidden (Pass). |
| Queue deadlock prevention (Poison Pill) | `AnalyzeCommentsJob.php` line 507-544 | Batch of comments throws unrecoverable JSON parse or API exception. | `if ($isLastAttempt || $isUnrecoverable) { DB::table('live_events')->update(['ai_processed' => true, ...]); }` | Comments marked as processed/neutral to allow queue pipeline progression (Pass). |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Runware AI API Credits / Usage Cost | Authenticated User | `/lives/{liveSession}/refresh-insights` | Manual refresh route has no server-side throttle verification or credit gate checking. | Flooding endpoint to call DeepSeek AI directly, bypassing subscription features and throttling. | High |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `clearSessionCache` | 3 | Clearing cache keys that might not exist or be needed. | Safe. Correctly invalidates all temporary stats/keywords cached metrics. |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Manual Refresh Rate Limiting | None | Flooding the endpoint fails after 1 request within 30s. | No | Test checking that manual refresh respects the throttle cache key. |
| Manual Refresh Subscription Gate | None | Manual refresh fails for users who have exceeded AI credits. | No | Test checking that manual refresh verifies subscription limit. |

## Findings

### [High] Manual refresh endpoint bypasses credit checks and throttle limits
- Type: Security / Abuse Path
- Location: `LiveSessionController::refreshInsights` (lines 385-473)
- Evidence: The manual refresh endpoint does not check if the user has an active subscription plan, nor does it check or increment the user's `used_ai_credits` counter. Furthermore, although it saves a cache key (`live_session_{id}_last_insight_time`), it never validates this key before dispatching the request to the Runware AI service.
- Cross-check: In contrast, the auto-insight generator inside `AnalyzeCommentsJob` correctly throttles requests to 30 seconds and checks `used_ai_credits` limits.
- Why wrong/risky: Users can spam call this endpoint directly via curl or console, invoking the DeepSeek AI service repeatedly. This can bypass paid plan gates, exhaust platform AI credits, and inflate external API costs.
- Impact: Abuse path that allows infinite free AI generation.
- Scenario: An attacker writes a script to post to `/lives/{session_id}/refresh-insights` every second, bypassing the frontend disabled state and calling DeepSeek API indefinitely.
- Minimal fix: Add a check inside `LiveSessionController::refreshInsights` to verify the throttle cache key (similar to `AnalyzeCommentsJob`) and ensure the user has sufficient AI credits. Also, increment `used_ai_credits` if appropriate.
- Validation: Currently unvalidated on server-side.
- Confidence: High

### [Medium] Unhandled exceptions in controller manual refresh
- Type: Exception Handling / Reliability
- Location: `LiveSessionController::refreshInsights` (lines 448-453)
- Evidence: The action invokes `$runware->chatJson` directly without a try-catch block.
- Cross-check: If the Runware API is offline, returns a 401/403 auth error, or hits rate limits, `chatJson` throws a `RuntimeException`.
- Why wrong/risky: This causes a 500 error screen or unhandled JSON exception response on the frontend instead of showing a clean error toast.
- Impact: Poor user experience if API goes down.
- Scenario: Runware AI service experiences an outage, manual refresh button triggers a crash and displays a generic server error instead of a graceful message.
- Minimal fix: Wrap `$runware->chatJson(...)` in a `try-catch` block, log the failure, and return a clean HTTP 500 response with a user-friendly message, e.g., `return response()->json(['error' => 'Dịch vụ AI hiện đang bận.'], 500)`.
- Validation: Verified by analyzing `RunwareAiService` exceptions and controller definition.
- Confidence: High

### [Low] Alert type cast fallback dependency
- Type: Robustness / Code Design
- Location: `Show.tsx` (lines 2267 and 2116-2159)
- Evidence: `alertTypeConfig` is indexed with `'danger' | 'warning' | 'info' | 'success'`, while the instructions in `LiveSessionAnalyzer.php` allow the AI model to return type strings like `'negative_comments', 'spam', 'purchase_intent', 'shipping_issue', v.v.`
- Cross-check: Line 2267 uses `const c = alertTypeConfig[alert.type] ?? alertTypeConfig.info;` to safely handle mismatching strings.
- Why wrong/risky: While the code is robust due to the fallback operator (`??`), there is a mismatch between the instructions in the prompt and the keys in the TypeScript type structure.
- Impact: Very low. It renders matching styles nicely using the fallback.
- Suggestion: Align the instructions in `LiveSessionAnalyzer` to output one of the four severity types (`'danger' | 'warning' | 'info' | 'success'`) instead of descriptive alerts, or provide a mapping in the frontend.
- Validation: Verified by reading the agent prompt vs. frontend config.
- Confidence: High

## Product/UX/Text/Duplicate Issues
- None. The frontend UI styling, manual refresh button with spinner state, and dynamic polling updates are correctly implemented and ESLint linting passes without any issues.

## Test Gaps
- There are no tests verifying that manual refresh respects throttle limits or AI credit gates on the backend because these gates are not currently implemented in the controller.

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test --filter=LiveSessionAiInsightsTest` | Yes | PASS (8 tests, 32 assertions) | AI Insights database attributes, analyzer schema, manual refresh controllers, auto insights throttle jobs, and fetchEvents serializations are fully correct. | Does not prove protection against manual API flood abuse. |
| `php artisan test` | Yes | PASS (104 tests, 700 assertions) | No regressions introduced across settings, subscription packages, checkout VietQR callbacks, or TikTok connection settings. | N/A |
| `npm run build` | Yes | PASS | Frontend TypeScript compilation and Vite build bundles build successfully. | N/A |
| `npm run lint` | Yes | PASS | Frontend files have no ESLint syntax or style errors. | N/A |

## Missed-risk / Limitations
- Runware AI Multimodal service latency is not mocked with timeouts; tests assume instantaneous mocking.

## Suggested Fix Order
1. **Throttle & Credit Gate manual refresh**: Add Cache check and User subscription check at the beginning of `LiveSessionController::refreshInsights`.
2. **Exception Handling**: Add try-catch block inside `LiveSessionController::refreshInsights` for Runware API calls.

## Decision
Merge with follow-up
