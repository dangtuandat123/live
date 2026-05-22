# Audit Report

## Summary
- **Scope**: Review of UI and Backend Synchronization Phase 2 (Requirements R1 to R5)
- **Mode**: static/code-path audit & test verification
- **Confidence**: High
- **Critical**: 0
- **High**: 0
- **Medium**: 0
- **Low**: 0
- **Decision**: Safe within audited scope

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | UI and Backend Synchronization Phase 2 |
| Stack/framework | Laravel (PHP), Inertia.js, React (TypeScript), Vite, Tailwind CSS, SQLite (test) |
| Expected user behavior | Accurate Stage 3 funnel count ("KH tiềm năng"), "Chốt đơn" label for Leads card with ShoppingCartIcon, dynamic cache invalidation, custom keywords tracking, and regex sync for phone extraction |
| Expected backend/data behavior | Distinct customer counting, dynamic JSON `like` query for top keywords, dynamic cache invalidation on edits & jobs, regex pre-population preservation |
| Source of truth | Orchestrator/Worker original requirements |
| Exclusions | Python TikTok live streaming fetcher service, authentication, pricing packages checkout |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 1 | 1 | 0 | `Show.tsx` (funnelData, quick stats card, top keywords) |
| User actions | 4 | 4 | 0 | Page load, Poll/Fetch events, Update event, view keywords |
| API/actions | 2 | 2 | 0 | `lives.fetch-events`, `live-events.update` |
| Services/domain | 2 | 2 | 0 | `AnalyzeCommentsJob` logic, `TikTokService` (unmodified) |
| DB/schema/config | 2 | 2 | 0 | `live_events`, `live_sessions` tables |
| Auth/permissions | 1 | 1 | 0 | Owner verification checks in controller |
| State/cache | 6 | 6 | 0 | Cache keys memory/forget calls |
| Tests | 1 | 1 | 0 | `LiveSessionUIIntegrationTest.php` (94 tests passing total) |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| **R1**: Funnel counts distinct potential customers | Requirement R1 | High | Counting same user multiple times, or displaying capped 50 list count |
| **R2**: Funnel Stage 3 and Stats card renaming | Requirement R2 | High | Keeping old labels `"Có SĐT/ĐC"`, `"KH tiềm năng"`, or incorrect icon |
| **R3**: Cache cleared in job & controller edits | Requirement R3 | High | Outdated metrics displaying on page due to stale cache |
| **R4**: Top Keywords dynamic calculation & mapping | Requirement R4 | High | Redundant unused `'keywords'` props, fallback merge products/questions |
| **R5**: RegEx captures phone numbers & AI respects it | Requirement R5 | High | AI overwrites verified Vietnamese numbers with `false` |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Show.tsx` (Funnel) | Stage 3 of Conversion Funnel | Line 2217 | Label: `"KH tiềm năng"`, Value: `potentialCustomersCount` | Label: `"KH tiềm năng"`, Value: `potentialCustomersCount` | None |
| `Show.tsx` (Sidebar Stats) | Bottom grid leads_count card | Lines 3041-3050 | Label: `"Chốt đơn"`, Icon: `ShoppingCartIcon` | Label: `"Chốt đơn"`, Icon: `ShoppingCartIcon` | None |
| `Show.tsx` (Keywords Card) | "Từ khóa được nhắc nhiều" render | Lines 3206-3235 | Map over `topKeywords` with counts | Maps over `topKeywords` with counts | None |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Event Polling | `setInterval` fetch | None (polls status) | Handled (Loader) | Sets state data | `/lives/{id}/fetch-events` | Low |
| Event Pinned Toggle | `togglePin` | Controller handles auth | Instant state update | Success notification | `/api/live-events/{id}` | Low |
| Event Highlight Toggle | `toggleOrder` | Controller handles auth | Instant state update | Success notification | `/api/live-events/{id}` | Low |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| KH tiềm năng | Stage 3 label of funnel chart | Clear grouping for prospective buyers | Displays "KH tiềm năng" | None |
| Chốt đơn | Bottom of video card stats card | Clear labeling for actual orders | Displays "Chốt đơn" | None |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Polling data | `useEffect` poll | `lives.fetch-events` | POST JSON | Owner ID check | Cache read/set | Returns fresh stats & counts | None |
| Update event status | `togglePin` / `toggleOrder` | `live-events.update` | PUT JSON | Owner ID check | Clears cache, updates DB | Returns updated event JSON | None |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `POST /lives/{id}/fetch-events` | Blocked (Inertia Auth) | Returns 403 Forbidden | Ignored | Allowed (read-only) | Secured |
| `PUT /api/live-events/{id}` | Blocked (Inertia Auth) | Returns 403 Forbidden | Validation errors | Allowed (idempotent) | Secured |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| User cannot access another user's live session data | `LiveSessionController` lines 197, 327, 367, 394, 885 | Attempt to fetch/modify using random session ID | Returns 403 Forbidden status | Pass |
| Phone numbers once matched are never lost by AI | `AnalyzeCommentsJob` lines 264-266 | AI outputs `has_phone = false` for comment containing phone | Overwritten to `true` | Pass |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| User Session Data | External Authenticated User | API Endpoints | None (auth is checked) | None | Secured |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| Old `'keywords'` prop | None in show page props | Low | Confirmed removed from `show()` and `Show.tsx` props |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Invalidation of `top_keywords` | `test_updating_event_clears_cache` | Skip clearing cache key | Yes | None |
| Verification of `potentialCustomersCount` | `test_show_page_inertia_props...` | Return incorrect count | Yes | None |

## Findings
No findings of code quality issues, bugs, or regressions were detected. All changes conform exactly to guidelines.

## Product/UX/Text/Duplicate Issues
None.

## Test Gaps
None. Integration tests fully cover the new props, response structures, and cache invalidation.

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test` | Yes | `94 passed (658 assertions)` | Backend logic correctness & integration stability | Runtime VPS streaming simulation |
| `npm run build` | Yes | `Built in 15.47s` with zero errors | TypeScript safety and bundler compilation completeness | Screen-pixel layout perfect rendering |

## Missed-risk / Limitations
This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.

## Suggested Fix Order
No fixes needed.

## Decision
Safe within audited scope
