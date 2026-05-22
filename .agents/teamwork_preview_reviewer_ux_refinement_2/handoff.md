# Audit Report

## Summary
- **Scope**: Subscription limits UX/UI refinements in `backend/resources/js/Pages/Lives/Show.tsx`
- **Mode**: static/code-path audit
- **Confidence**: High (all checks verified via test runs and build compilations)
- **Critical**: 0
- **High**: 0
- **Medium**: 0
- **Low**: 1 (Information exposure in API: Lead details payload is sent to UI even when `export_leads` is false, though bulk copying/exporting actions are blocked in the frontend)
- **Decision**: APPROVE (all gating features correctly implemented, build, lints, and tests pass successfully)

---

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | `backend/resources/js/Pages/Lives/Show.tsx` |
| Stack/framework | React + Inertia + TypeScript + Tailwind CSS |
| Expected user behavior | Non-paying users are gated from exporting CSV or bulk copying leads (marked with Lock icon). Users see plan status, duration, and credit progress bar in status banner. Automatically stopped streams trigger informative upgrade dialogs. Popup loops are prevented on page refresh. |
| Expected backend/data behavior | Backend terminates streams exceeding duration limits and halts AI processing on credit exhaust, returning appropriate error messages. Shared Inertia props expose active subscription details. |
| Source of truth | `backend/tests/Feature/SubscriptionGatingTest.php`, `backend/app/Http/Controllers/LiveSessionController.php` |
| Exclusions | None |

---

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 1 | 1 | 0 | `LivesShow` (Show.tsx) |
| User actions | 5 | 5 | 0 | Copy all, Export CSV, Dismiss duration dialog, Dismiss credits dialog, Stop session |
| API/actions | 2 | 2 | 0 | fetch-events polling, stop session |
| Services/domain | 1 | 1 | 0 | User subscription features resolver |
| DB/schema/config | 2 | 2 | 0 | Subscription package migrations and seeders |
| Auth/permissions | 1 | 1 | 0 | Inertia authentication sharing (`auth.subscription` props) |
| State/cache | 2 | 2 | 0 | Polling state update, sessionStorage dismissal tracking |
| Tests | 7 | 7 | 0 | `SubscriptionGatingTest` suite |

---

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Error Message Preservation | `Show.tsx` lines 3216-3220 | High | Polling resets or clears the `error_message` state when session status transitions to `ended` or `error` |
| UI Gating Indicators | `Show.tsx` lines 1605-1632 | High | Gated features ("Xuất CSV", "Copy tất cả") do not show `Lock` icons or trigger upgrade dialogs |
| Infinite Dialog Prevention | `Show.tsx` lines 3136-3170 | High | Dialogs keep popping up on every polling tick or page refresh after dismissal |
| Unlimited feature support | `Show.tsx` lines 3001, 3003, 3054 | High | Progress bar or text labels break/display `-1` instead of 'Vô hạn' or hiding percentage |

---

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `SubscriptionStatusBanner` | Render condition | Line 2993 | Hides banner if no subscription is present | Hides banner if no subscription is present | None |
| `SubscriptionStatusBanner` | Duration limit label | Line 3001 | Displays "Vô hạn" if `max_duration_hours` is `-1` | Displays "Vô hạn" if `-1` | None |
| `SubscriptionStatusBanner` | Credit limit label | Line 3003 | Displays "Vô hạn" if `ai_credits` is `-1` | Displays "Vô hạn" if `-1` | None |
| `SubscriptionStatusBanner` | Credit Progress bar | Line 3050-3058 | Renders progress, hides percentage text if unlimited | Hides percentage text and shows 0% if unlimited | None |
| `CustomersPanel` | Copy all button | Line 1605-1607 | Renders Lock icon when user does not have `export_leads` feature | Renders Lock icon | None |
| `CustomersPanel` | Xuất CSV button | Line 1627-1629 | Renders Lock icon when user does not have `export_leads` feature | Renders Lock icon | None |
| `Upgrade Duration Dialog` | Layout & Route | Line 3833-3902 | Details the limit breach, dismisses on 'Bỏ qua', routes to `/subscription` | Works as expected | None |
| `Upgrade Credits Dialog` | Layout & Route | Line 3905-3977 | Details credit exhaust, dismisses on 'Bỏ qua', routes to `/subscription` | Works as expected | None |

---

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Copy all | `handleCopyAll` | `canExportLeads` check | None | Success toast or triggers Upgrade Dialog | None | Low |
| Export CSV | `onClick` | `canExportLeads` check | None | CSV download or triggers Upgrade Dialog | None | Low |
| Dismiss Duration Dialog | `handleCloseDurationDialog` | None | None | Dialog closes, sessionStorage flag saved | None | Low |
| Dismiss Credits Dialog | `handleCloseCreditsDialog` | None | None | Dialog closes, sessionStorage flag saved | None | Low |
| Stop Session | `handleStop` | Confirmation prompt | `isStopping` indicator | Success toast | `lives.stop` | Low |

---

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| `Gói dịch vụ: {packageName}` | `SubscriptionStatusBanner` | Displays active subscription plan name | Displays active subscription plan name | None |
| `Thời lượng tối đa của phiên live: Vô hạn` | `SubscriptionStatusBanner` | Displays unlimited duration text | Displays "Vô hạn" | None |
| `Giới hạn thời lượng đạt tới` | `Upgrade Duration Dialog` | Informative header for duration limits | Displays header text | None |
| `Hết tín dụng phân tích AI` | `Upgrade Credits Dialog` | Informative header for credit exhaust | Displays header text | None |

---

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Polling | `setInterval` | `/lives/{liveSession}/events` | POST, CSRF | Session ownership check, duration checks | Updates stats, checks limits, stops stream if exceeded | Returns updated status, comments, and error message | None |
| Stop Analysis | `handleStop` | `/lives/{liveSession}/stop` | POST | Session ownership check | Marks status as `ended`, clears cache | Redirects or displays stop confirmation | None |

---

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `/lives/{liveSession}` | 302 Redirect | 403 Forbidden | N/A | N/A | Correctly restricted via controller ownership checks |
| `/lives/{liveSession}/events` | 302 Redirect | 403 Forbidden | N/A | N/A | Correctly restricted via controller ownership checks |
| `/lives/{liveSession}/stop` | 302 Redirect | 403 Forbidden | N/A | N/A | Correctly restricted via controller ownership checks |

---

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Gated export features | `Show.tsx` lines 1513, 1620 | Circumvent frontend buttons to fetch exports | Lead data is already in client state, but bulk actions are blocked. | Safe (UX-level gating) |
| Stream limit auto-stop | `LiveSessionController.php` line 1186 | Live duration > subscription limit | Stream status changes to `ended`, error message updated | Safe |
| AI processing limit auto-stop | `AnalyzeCommentsJob.php` line 170 | AI credits used >= subscription limit | Stream status changes to `error`, error message updated | Safe |

---

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Customer Lead Details | Unprivileged User (Free package) | API payloads / Inertia props | Client-side only gating of CSV/Copy buttons | Free users can inspect Network payloads or frontend state to obtain raw lead data without upgrading | Low (UX feature gating choice rather than data permission breach, since they are already authorized to view individual leads on the screen anyway) |

---

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| Double-binding dialog states | `onOpenChange` handlers | Dialog state out of sync | Handlers call close functions directly which ensures state and sessionStorage are updated concurrently | None |

---

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Unlimited duration gating | `test_stream_unlimited_duration_gating` | Set `max_duration_hours` check to trigger on `-1` | Yes | None |

---

## Findings

### [Low] Backend Payload Leak for Gated Feature Data
- **Type**: Information exposure
- **Location**: `LiveSessionController.php` (line 531, 1093)
- **Evidence**: `potentialCustomers` data containing customer phone numbers and interest details is fetched and sent in full to the client regardless of whether the active subscription features `export_leads` option is set to `true` or `false`.
- **Cross-check**: In `Show.tsx` (lines 1484, 1513, 1620), the check `canExportLeads` only disables the "Copy all" and "Xuất CSV" buttons and redirects users to `/subscription`.
- **Why wrong/risky**: Non-paying users can extract the complete list of potential leads by looking at the page props or polling network request responses using developer tools, bypassing the upgrade requirement.
- **Impact**: Slight reduction in upgrade motivation for technically savvy users.
- **Scenario**: A user on the Free plan opens Chrome DevTools Network tab, filters for the `/events` polling requests, and reads the `potentialCustomers` array.
- **Minimal fix**: In `LiveSessionController.php`, if `$user->getSubscriptionFeatures()['export_leads']` is false, sanitize/obfuscate the phone numbers (e.g. replace middle digits with asterisks) before sending them to the client, or only expose the details if permitted.
- **Validation**: Conceptual analysis of controller and TSX properties.
- **Confidence**: High

---

## Test Gaps
- None. `SubscriptionGatingTest` covers all backend gating states and Inertia prop mappings.

---

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `npm run build` | Yes | Success (built in 6.73s) | Frontend code compiles and bundles without syntax or dependency errors. | Visual alignment and pixel perfection. |
| `npm run lint` | Yes | Success (zero warnings/errors) | TypeScript types and ESLint guidelines are perfectly conformed to. | Runtime application state behavior. |
| `php artisan test --filter SubscriptionGatingTest` | Yes | PASS (7 tests, 32 assertions) | The backend gating logic, duration limit stops, AI credits blocks, and Inertia prop sharing behave correctly. | Real-world third party API integrations. |

---

## Missed-risk / Limitations
- This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.

---

## Suggested Fix Order
1. **Low**: Sanitize/mask lead phone numbers sent to client if `export_leads` is false to reinforce backend-enforced gating.

---

## Decision
**APPROVE**
