# Forensic & Deep Audit Report

## Forensic Audit Summary

**Work Product**: TikTok livestream comment analysis pipeline (Solution G)
**Profile**: General Project
**Verdict**: CLEAN

### Phase Results
- **Phase 1: Source Code Analysis**: PASS — Checked for hardcoded outputs, facades, and pre-populated logs/artifacts. The implementation is 100% genuine and correctly structured.
- **Phase 2: Behavioral Verification**: PASS — Build succeeds. 32 test cases passed successfully (82 assertions) using genuine mocks and assertions.
- **Phase 3: Dependency/Logic Check**: PASS — Standard dependencies. Core analysis logic is written from scratch, using `RunwareAiService` chatMultimodal and `TikTokService` snapshot features.

---

## Detailed Technical Audit

### Summary
- **Scope**: Solution G (AnalyzeCommentsJob.php, LiveSession.php, Migration, AnalyzeCommentsJobTest.php)
- **Mode**: static/code-path audit
- **Confidence**: 100% (backed by complete file reads and test suite run)
- **Critical**: 0
- **High**: 0
- **Medium**: 0
- **Low**: 1 (Minor database index suggestion)
- **Decision**: Safe within audited scope

### Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Solution G (Text + Audio + Memory Comment Analysis Pipeline) |
| Stack/framework | Laravel (PHP) + FastAPI (Python) for TikTokLIVE service |
| Expected user behavior | Livestreams connect. Comments analyze in real-time, matching products/sentiment/intent. |
| Expected backend/data behavior | `AnalyzeCommentsJob` fetches 50 unprocessed comments, calls Runware multimodal, saves AI results + updates memory/aggregates, loops itself. |
| Source of truth | Git repository and code execution |
| Exclusions | Node packages, Python FastAPI inner capture routines, frontend Blade UI rendering |

### Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 0 | 0 | 0 | Backend-only pipeline |
| User actions | 0 | 0 | 0 | Automated job execution |
| API/actions | 1 | 1 | 0 | `AnalyzeCommentsJob` class |
| Services/domain | 2 | 2 | 0 | `RunwareAiService`, `TikTokService` |
| DB/schema/config | 3 | 3 | 0 | `LiveSession` model, migrations |
| Auth/permissions | 0 | 0 | 0 | N/A (runs in queue worker context) |
| State/cache | 1 | 1 | 0 | Job lock clearing and unique lock logic |
| Tests | 1 | 1 | 0 | `AnalyzeCommentsJobTest` |

### Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Batch comment analysis | `AnalyzeCommentsJob::handle` | High | Fetching too many comments or processing already analyzed events. |
| Multimodal integration | `AnalyzeCommentsJob::handle` | High | Not including base64 audio snapshot. |
| Contextual memory (session note) | `AnalyzeCommentsJob::handle` | High | Not writing or loading the `ai_context_summary` for context retention. |
| Safe error recovery | `AnalyzeCommentsJob::handle` | High | Queue locking / Poison pill deadlock blocking the queue. |
| Schema data constraints | `AnalyzeCommentsJob::validateResult` | High | Saving unvalidated/malformed AI responses directly to DB. |

### Static UX Matrix
*None — this is a backend comment analysis pipeline audit.*

### Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Comment Analysis | `AnalyzeCommentsJob::handle` | Validates session status (must be live/connecting), validates AI tags in backend | Unique lock for 30s (`ShouldBeUnique`) to avoid parallel job collisions | Updates DB events, stats & schedules next. On error, retries or falls back to neutral tagging. | `RunwareAiService::chatMultimodal`, `TikTokService::getSnapshot` | AI API downtime/rate limits; mitigated by queue retry. |

### Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| Multimodal Prompt Instructions | `AnalyzeCommentsJob::buildSystemPrompt()` | AI gets strict specifications for "Chốt đơn", "Hỏi thông tin", "sentiment" and "session_note". | Includes clear rules, Vietnamese localized tags, exact product catalogs, and audio cues instructions. | None |

### Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| N/A | N/A | `AnalyzeCommentsJob` | Batch payload | Verifies session is in active state | Executes DB writes inside `DB::transaction`; clears lock | Updates database | None |

### Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `AnalyzeCommentsJob` execution | N/A (runs on worker) | Scoped to `$this->liveSessionId` | Input checked using `validateResult` whitelist | Prevented via unique key lock and checking `ai_processed` | Secure and idempotent |

### Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Unique Session Processing | `AnalyzeCommentsJob.php:18` | Simultaneous polling dispatch of job | `ShouldBeUnique` interface + `uniqueId()` | Job locked to 1 active run per session. |
| AI Response Sanitization | `AnalyzeCommentsJob.php:394` | AI hallucinates tags/intents | `validateResult` checks against whitelists | Hallucinated tags mapped to neutral/null. |
| Memory Size Cap | `AnalyzeCommentsJob.php:230` | AI outputs exceptionally long session notes | Truncated to 500 characters using `mb_substr` | Fit within database field text safely. |

### Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| TikTok user IDs & comment streams | Spammer | Comment Text | Standard LLM prompt susceptibility | Malicious injection to fabricate fake order tags ("Chốt đơn") | Low |

*Mitigation: Backend whitelisting of tags and exact product matching via `matchProductTag` protects DB records from raw injection.*

### Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `chatJson` | `RunwareAiService.php` | Unused method if all calls use multimodal | Kept as a clean helper method for text-only analysis fallbacks. |

### Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Self-dispatch lock clearing | Yes (multi-batch test) | Commencing `cache()->forget($lockKey)` to fail | Yes, subsequent immediate dispatches would block. | An integration test specifically checking that self-dispatch is blocked without clearing the lock. |

### Findings

#### [Low] Index on `event_at` in `live_events` table
- **Type**: Database performance
- **Location**: `backend/database/migrations/2026_05_21_000004_create_live_events_table.php`
- **Evidence**:
  ```php
  $unprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
      ->where('event_type', 'comment')
      ->where('ai_processed', false)
      ->orderBy('event_at')
      ->limit(50)
      ->get();
  ```
- **Cross-check**: The table currently only has composite indexes on `['live_session_id', 'event_type']`, `['live_session_id', 'created_at']`, and individual indexes on `sentiment`, `intent_tag`, `ai_processed`.
- **Why wrong/risky**: Ordering by `event_at` without a corresponding index on `event_at` or a composite index like `[live_session_id, event_type, ai_processed, event_at]` will result in a filesort execution path once the table contains a large volume of comments.
- **Impact**: Slight latency increases during the batch query execution on streams with millions of events.
- **Scenario**: A long livestream gets 50,000 comments, query execution plan will perform filesort for the ordering step.
- **Minimal fix**: Add a composite index or index `event_at` column.
- **Validation**: Manual execution plan check.
- **Confidence**: High.

---

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test` | Yes | 32 passed, 82 assertions | All test cases pass successfully. | Visual rendering correctness. |

## Missed-risk / Limitations
- This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.

## Decision
**Safe within audited scope** (Verdict: CLEAN)
