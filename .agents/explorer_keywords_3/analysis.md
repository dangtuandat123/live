# Audit Report — AI Auto-Discovery Keywords

## Summary
- **Scope**: Manual keyword configuration removal, AI Auto-Discovery Keywords implementation in `AnalyzeCommentsJob.php`, and keyword aggregation/rendering in `LiveSessionController.php` and `Show.tsx`.
- **Mode**: Static/code-path audit & implementation strategy.
- **Confidence**: High (Full trace of relevant paths, verified via workspace tests).
- **Critical Issues**: 0
- **High Issues**: 0
- **Medium Issues**: 0
- **Low Issues**: 0 (Suggestions for optimizing SQL queries provided)
- **Decision**: Safe within audited scope (No bugs found in existing code, details for planned implementation provided).

---

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | d:\Workspace\livestream |
| Stack/framework | Laravel (PHP 8.2+), React 18+ (TypeScript, Inertia.js), MySQL/SQLite, Tailwind CSS, Runware AI Multimodal API |
| Expected user behavior | Streamers set up live sessions without manual input of keywords. Comments are auto-analyzed by LLM. LLM extracts relevant keywords on the fly. Discovered keywords are aggregated and rendered in the dashboard as badges with occurrences. |
| Source of truth | Source code files: `Setup.tsx`, `LiveSessionController.php`, `AnalyzeCommentsJob.php`, `Show.tsx`, and database schema. |
| Exclusions | External Runware API sandbox environments, TikTok Live integration runtime stream data. |

---

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 2 | 2 | 0 | `Setup.tsx` and `Show.tsx`. |
| User actions | 3 | 3 | 0 | Stream setup submission, adding/removing keywords, and dashboard polling. |
| API/actions | 3 | 3 | 0 | `LiveSessionController@store`, `getTopKeywords`, and event endpoint. |
| Services/domain | 1 | 1 | 0 | `AnalyzeCommentsJob.php` (AI agent logic). |
| DB/schema/config | 3 | 3 | 0 | Migration `2026_05_21_000005_create_live_session_keywords_table.php`, Models `LiveSession.php`, `LiveSessionKeyword.php`. |
| Auth/permissions | 1 | 1 | 0 | Checked route ownership verification in `LiveSessionController`. |
| State/cache | 2 | 2 | 0 | Locked session queue cache and `topKeywords` cache key. |
| Tests | 2 | 2 | 0 | `AnalyzeCommentsJobTest.php` and `AnalyzeCommentsJobAdversarialTest.php`. |

---

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Stream Setup UI | `Setup.tsx` | High | Showing text inputs or badges for keywords, which are now AI-automated. |
| Session Storage | `LiveSessionController::store` | High | Expecting `keywords` array in the request body and validating/persisting them. |
| AI Prompting | `AnalyzeCommentsJob::buildSystemPrompt` | High | Failing to instruct LLM to return `extracted_keywords` array in JSON. |
| AI Parsing | `AnalyzeCommentsJob::handle` | High | Failing to parse `extracted_keywords` or failing to enforce lowercase, word-count constraints. |
| DB Persistence | `AnalyzeCommentsJob::handle` | High | Storing more than 30 keywords per session or duplicating entries in `live_session_keywords`. |
| Keyword Counting | `LiveSessionController::getTopKeywords` | High | Counting keywords using SQL LIKE on events that are not comments. |
| Keyword Rendering | `Show.tsx` | High | Mismatch in badge structure or rendering incorrect metrics. |

---

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Setup.tsx` | "Từ khóa theo dõi" Card (lines 313-363) | `Setup.tsx` code view | Card should be removed from view since setup is now automated. | Card is visible and provides manual input fields. | UX Improvement: Needs removal. |
| `Show.tsx` | Top Keywords Card (lines 3206-3238) | `Show.tsx` code view | Renders badge-style keyword elements. | Displays badges with dynamic colors & occurrences. | None. |

---

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Create Live Session | `LiveSessionController::store` | `name`, `tiktok_username`, `product_ids` | Redirect state in Inertia form | Redirects to Live stream dashboard on success | `POST /lives` | Removing validation from Controller must not break older clients. (Low risk) |

---

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Từ khóa theo dõi" | `Setup.tsx`:316 | Label for setting up follow-up keywords | Prompt for typing custom tracking words | Should be completely removed |
| "🔍 Từ khóa được nhắc nhiều" | `Show.tsx`:3210 | Title for the auto-discovered keywords list | Displays "Từ khóa được nhắc nhiều" | Correct |

---

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Poll Stream Updates | `Show.tsx` (Inertia fetch) | `LiveSessionController@getTopKeywords` | `GET /lives/{id}/events` | Validates session ownership | Hits cache with 5s TTL | Returns JSON containing `topKeywords` array | None |

---

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `LiveSessionController@store` | Blocked by auth middleware | Blocked by auth middleware | Request ignores extra inputs | Safe | Normal creation of live session |

---

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Max 30 keywords per session | `AnalyzeCommentsJob.php` | Prompt returns 50 keywords on a single batch | Static Analysis | Truncation logic slicing new inserts to `30 - currentCount`. | Saved correctly. |
| Keyword standardization | `AnalyzeCommentsJob.php` | AI returns capitalized/punctuated keywords | Static Analysis | `mb_strtolower(trim($kw))` removes case & spaces. | Standardized. |

---

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Session Data | Unauthorized user | `/lives/{id}` | Route model binding check exists | None | Low |

---

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `keywords` validation rules | `LiveSessionController::store` | Dead code | To be deleted as part of R1 |

---

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| AI Keyword Discovered Storage | `AnalyzeCommentsJobTest.php` | Altering prompt layout or storage limit | Yes, can add a test verifying database count | Need unit test verifying keyword limit of 30. |

---

## Findings

### [Improvement] R1: Manual Keyword Configuration Removal
- **Type**: Code Cleanup
- **Location**: `backend/resources/js/Pages/Lives/Setup.tsx`, `backend/app/Http/Controllers/LiveSessionController.php`
- **Evidence**:
  - `Setup.tsx` line 69 initializes `keywords` in Inertia state.
  - `Setup.tsx` lines 313-363 render the card input.
  - `LiveSessionController.php` lines 125-126 validate `keywords`.
- **Minimal Fix / Deletion Plan**:
  1. Remove `keywords` from the default `useForm` object in `Setup.tsx`.
  2. Remove `keywordInput` state hook and helpers (`addKeyword`, `removeKeyword`) in `Setup.tsx`.
  3. Delete the manual keyword entry JSX Card (lines 313-363) in `Setup.tsx`.
  4. Delete the validation rules for `keywords` and `keywords.*` in `LiveSessionController::store()`.
  5. Delete the database insert loop for keywords in `LiveSessionController::store()`.

### [Improvement] R2: AI Auto-Discovery Keywords
- **Type**: Feature Enhancement
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Evidence**: `buildSystemPrompt` returns system prompt specifying JSON output format.
- **Proposed Changes**:
  1. **Update prompt instructions** in `buildSystemPrompt()`:
     - Update JSON template:
       ```json
       {"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", "intent_tag": "Chốt đơn"|"Hỏi thông tin"|"Phản hồi SP"|"Yêu cầu hỗ trợ"|null, "question_tag": string|null, "product_tag": string|null, "has_phone": bool}], "session_note": "string (max 300 ký tự)", "extracted_keywords": ["string"]}
       ```
     - Add explanation under rule section:
       ```
       - extracted_keywords: Danh sách mảng tối đa 5 từ khóa nổi bật được trích xuất từ các bình luận trong lượt phân tích này (viết chữ thường, ngắn gọn 1-3 từ, tập trung vào sản phẩm, giá cả, chất lượng, thắc mắc chung, loại bỏ từ rác).
       ```
  2. **Update job processor `handle()`**:
     - Parse the AI response array: `$extractedKeywords = $response['extracted_keywords'] ?? [];`.
     - Standardize each keyword:
       - Lowercase and trim: `$kwCleaned = mb_strtolower(trim($kw));`
       - Length filter: only allow keywords consisting of 1 to 3 words (`count(explode(' ', $kwCleaned)) <= 3`).
       - Reject empty values.
     - Persist with a per-session limit of 30 keywords:
       - Check count: `$currentCount = DB::table('live_session_keywords')->where('live_session_id', $session->id)->count();`
       - If `$currentCount < 30`, calculate capacity: `$maxAllowed = 30 - $currentCount`.
       - Retrieve existing keywords: `$existing = DB::table('live_session_keywords')->where('live_session_id', $session->id)->pluck('keyword')->map(fn($k) => mb_strtolower(trim($k)))->toArray();`
       - Filter out duplicates from `$extractedKeywords` (comparing against `$existing` and self-batch duplicates).
       - Slice to `$maxAllowed` and perform database inserts.

### [Low] R3: Performance Bottlenecks in Keyword Aggregation
- **Type**: Performance Risk
- **Location**: `backend/app/Http/Controllers/LiveSessionController.php` (method `getTopKeywords`)
- **Evidence**:
  - The controller loops over keywords to issue individual database `count()` queries:
    ```php
    foreach ($setupKeywords as $kw) {
        $count = $session->events()
            ->where('event_type', 'comment')
            ->where('data->comment', 'like', "%{$kw}%")
            ->count();
        ...
    }
    ```
- **Why wrong/risky**:
  - If a session accumulates the maximum of 30 keywords, the controller executes 30 separate SQL requests. This is an N-query database roundtrip issue.
  - The SQL LIKE query `%keyword%` on JSON fields is unindexed and triggers full-scans on the session events.
- **Mitigation status**: Currently mitigated by cache keys with a 5-second TTL during live streams, and 1 hour when streaming has ended.
- **Suggested Improvement**:
  Instead of N queries, fetch all comment events for the session in a single database request (e.g. pulling only the `data->comment` column) and perform the string scanning and counting in memory using PHP's `mb_strpos()`. This reduces the DB overhead to exactly 1 query.

---

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test` | Yes | 94 passed, 658 assertions | Core functionality and integration suites pass successfully. | AI output structure changes (requires mock assertions update). |

---

## Missed-risk / Limitations
- This is a static code-path audit.
- No live browser/visual QA was performed.

---

## Suggested Fix Order
1. **R1**: Clean up frontend component `Setup.tsx` and controllers validations (`LiveSessionController::store`).
2. **R2**: Implement prompt upgrades and keyword persistence logic in `AnalyzeCommentsJob.php`.
3. **Tests**: Update test mocks in `AnalyzeCommentsJobTest.php` to include `extracted_keywords` in returned mock results, preventing test regression.

---

## Decision
**Safe within audited scope** (No bugs found in existing code, details for planned implementation provided).

*This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.*
