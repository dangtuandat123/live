# Audit Report — AI Auto-Discovery Keywords

## Summary
- **Scope**: Transition from manual keyword configuration to AI auto-discovered keywords across Setup.tsx, LiveSessionController.php, AnalyzeCommentsJob.php, Show.tsx, and the live_session_keywords table migration.
- **Mode**: static/code-path audit
- **Confidence**: High
- **Critical**: 0
- **High**: 0
- **Medium**: 3 (Manual configuration code blocking milestone, missing prompt extraction in AI job, lack of auto-discovery persistence)
- **Low**: 1 (Inefficient SQL LIKE performance for keywords)
- **Decision**: Fix before merge (Implementation plan must be followed to proceed with the milestone change safely)

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Keyword Discovery and Tracking Flow |
| Stack/framework | Laravel (PHP 8.x) + Inertia.js + React (TypeScript) + PostgreSQL/MySQL |
| Expected user behavior | Users set up a livestream session without manually entering keywords. During the live stream, comments are automatically scanned by AI to auto-discover keywords. The top keywords are displayed dynamically on the dashboard with their occurrence counts. |
| Expected backend/data behavior | `LiveSessionController::store` should no longer accept or validate manual keywords. `AnalyzeCommentsJob` updates the system prompt to return an array of up to 5 lowercase keywords under `extracted_keywords`. The job standardizes, dedupes, and persists them into the `live_session_keywords` table, keeping the total keyword count per session under 30. |
| Source of truth | Existing codebase files: `Setup.tsx`, `LiveSessionController.php`, `AnalyzeCommentsJob.php`, `Show.tsx`, and `2026_05_21_000005_create_live_session_keywords_table.php` |
| Exclusions | Python TikTok LIVE scraper service internals (handled as external API). |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 2 | 2 | 0 | `Setup.tsx` and `Show.tsx` read completely. |
| User actions | 2 | 2 | 0 | Creating a livestream session and viewing dashboard. |
| API/actions | 3 | 3 | 0 | Store session, fetch events, get top keywords. |
| Services/domain | 1 | 1 | 0 | `AnalyzeCommentsJob` and AI prompt building. |
| DB/schema/config | 3 | 3 | 0 | `live_session_keywords` migration, `LiveSessionKeyword` and `LiveSession` models. |
| Auth/permissions | 1 | 1 | 0 | Tenant ownership checks on session route access. |
| State/cache | 2 | 2 | 0 | Page state polling updates and `top_keywords` Cache keys. |
| Tests | 1 | 1 | 0 | `LiveSessionUIIntegrationTest.php` read to see test dependencies. |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Remove manual keywords | Milestone Spec / R1 | High | Manual card/inputs left in Setup view or validation errors when client does not send keywords. |
| AI Prompt returns `extracted_keywords` | R2 | High | AI ignores keywords, returns wrong types (e.g. nested objects), or doesn't return lowercase short keywords. |
| Enforce per-session limit of ~30 | R2 | High | Table grows boundlessly over continuous comments polling, leading to performance degradation. |
| Standardize keywords | R2 | High | Storing uppercase, empty, or duplicate keywords in the DB. |
| Calculate top keywords count | R3 | High | Top keywords not counted using SQL LIKE query, or counts mismatch what is rendered on dashboard. |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| Setup.tsx | "Từ khóa theo dõi" Card | `Setup.tsx:312-363` | No manual keyword section. | Manual keyword input, badges, add/remove buttons exist. | R1: Needs removal |
| Setup.tsx | Form initial state | `Setup.tsx:69` | `keywords` key removed or set empty. | `keywords: ['mua', 'chốt', 'ship', 'giá', 'size']` | R1: Needs removal |
| Show.tsx | "Từ khóa được nhắc nhiều" Card | `Show.tsx:3206-3238` | Displays auto-discovered keywords and counts. | Displays keywords from database with their comment occurrence counts. | R3: Works with DB data. |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Submit Setup | `handleSubmit` | Validate inputs (name, username) | `form.processing` | Redirects to show page | `lives.store` | Validation mismatch if `keywords` array is strictly validated as required in controller |
| Poll live updates | `useEffect` interval | None | None | Updates React state | `lives.fetch-events` | Intermittent network disconnects or session error status |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Từ khóa theo dõi" | `Setup.tsx:315` | Section to configure tracking keywords | Manual configuration input | Mismatch: Will be replaced by AI discovery |
| "Từ khóa được nhắc nhiều" | `Show.tsx:3210` | Section showing the top words people type | Shows count of comments matching each keyword | Match |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Setup Livestream | `Setup.tsx` useForm | `lives.store` | `name`, `tiktok_username`, `product_ids`, `keywords` | Auth user; validates names and keywords array | Inserts to `live_sessions` and `live_session_keywords` | Redirects to `lives.show` | `keywords` payload must be deprecated |
| Polling live stats | `Show.tsx` fetch | `lives.fetch-events` | None | Auth user; checks session owner | Reads `topKeywords` from cache or DB | JSON with `topKeywords` list | None |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `lives.store` | Blocked by auth | N/A | Excess payload properties | Safe | Validated parameters are used; extra fields ignored |
| `lives.fetch-events` | Blocked by auth | 403 Forbidden | N/A | Safe | Checks `liveSession->user_id === user->id` |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Keyword Limit per session | `AnalyzeCommentsJob.php` | Continual comment flows insert 100+ keywords | Checked table insertions | Risk of unbounded database growth |
| Keyword Case Normalization | `AnalyzeCommentsJob.php` | AI returns mixed-cased variations (e.g. "áo", "Áo", "ÁO") | No normalization code | Duplicate keywords stored under different casings |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| AI Credits | Authenticated Tenant | `lives.store` | `resolveActiveSubscription()` | Excessive AI requests via background job | Low (validated against `used_ai_credits`) |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `keywords` in store | `LiveSessionController.php` | Manual keywords validation and database inserts | Unused manual code after R1 frontend updates |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Manual keyword setup | `LiveSessionUIIntegrationTest.php` | Removing `keywords` payload validation should break tests setting manual keywords | Yes, testing needs refactoring | Missing unit test for `AnalyzeCommentsJob` keyword auto-discovery and persistence |

---

## Findings

### [Medium] Finding 1: Manual Keyword Configuration in Setup UI
- **Type**: Clean up / Feature Deprecation
- **Location**: `backend/resources/js/Pages/Lives/Setup.tsx`
- **Evidence**:
  - Line 69: `keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[],`
  - Line 72: `const [keywordInput, setKeywordInput] = React.useState('');`
  - Lines 84–97: `addKeyword()` and `removeKeyword()` helper functions.
  - Lines 312–363: The `<Card>` component titled "Từ khóa theo dõi".
- **Why wrong/risky**: Blocks the milestone implementation. Manual input interface is confusing to users when keyword discovery is automated.
- **Impact**: UX confusion and redundant form payload.
- **Minimal fix**:
  - Remove `keywords` key from the `useForm` initial state or set it to `[]`.
  - Remove `keywordInput` React state.
  - Delete `addKeyword` and `removeKeyword` helper functions.
  - Delete lines 312–363 from `Setup.tsx`.
- **Validation**: Ensure Setup page renders without Javascript errors and forms submit without the keyword payload.

### [Medium] Finding 2: Manual Keyword Validation and Database Insertion in Controller
- **Type**: Clean up / Schema Sync
- **Location**: `backend/app/Http/Controllers/LiveSessionController.php`
- **Evidence**:
  - Lines 125–126:
    ```php
    'keywords' => ['nullable', 'array'],
    'keywords.*' => ['string', 'max:100'],
    ```
  - Lines 163–168:
    ```php
    // Save keywords
    if (! empty($validated['keywords'])) {
        foreach ($validated['keywords'] as $keyword) {
            $session->keywords()->create(['keyword' => $keyword]);
        }
    }
    ```
- **Why wrong/risky**: Redundant validation rules and manual inserts that are no longer supported by the UI.
- **Impact**: Exposes dead code paths in endpoint handlers.
- **Minimal fix**: Remove validation rules from `validate` array and remove the keyword-saving block in `store`.
- **Validation**: Run `php artisan test --filter=LiveSessionUIIntegrationTest` and adapt testing setup.

### [Medium] Finding 3: Missing Keyword Auto-Discovery Prompt in AI Comments Analysis
- **Type**: Feature Gap
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Evidence**:
  - Line 493:
    ```php
    Trả về JSON duy nhất: {"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", "intent_tag": "Chốt đơn"|"Hỏi thông tin"|"Phản hồi SP"|"Yêu cầu hỗ trợ"|null, "question_tag": string|null, "product_tag": string|null, "has_phone": bool}], "session_note": "string (max 300 ký tự)"}
    ```
- **Why wrong/risky**: Runware/Gemini AI will not auto-discover/extract keywords as they are not requested in the system prompt.
- **Impact**: Auto-discovery does not function.
- **Minimal fix**: Update the system prompt to return `extracted_keywords` array.
  - JSON format modification:
    ```php
    Trả về JSON duy nhất: {"results": [...], "session_note": "string (max 300 ký tự)", "extracted_keywords": string[]}
    ```
  - Instruction modification (Add to Prompt guidelines):
    ```markdown
    - "extracted_keywords": Mảng chứa tối đa 5 từ khóa được phát hiện từ danh sách bình luận này (mỗi từ khóa viết thường, ngắn 1-3 từ, tập trung vào sản phẩm, giá cả, chất lượng hoặc các thắc mắc chung).
    ```

### [Medium] Finding 4: Missing Persistence of AI Extracted Keywords in Job
- **Type**: Feature Gap
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Evidence**: The class `AnalyzeCommentsJob` parses results only for `results` and `session_note`, neglecting any keyword storage.
- **Why wrong/risky**: Discovered keywords are not saved to the `live_session_keywords` table.
- **Impact**: Dashboard top keywords show "Chưa có dữ liệu" (No data) since `live_session_keywords` table remains empty.
- **Minimal fix**:
  Add processing logic inside `handle()` method (around line 348) after receiving AI response:
  ```php
  $extractedKeywords = $response['extracted_keywords'] ?? [];
  if (is_array($extractedKeywords) && ! empty($extractedKeywords)) {
      $normalizedKws = collect($extractedKeywords)
          ->map(fn($kw) => mb_strtolower(trim($kw)))
          ->filter(fn($kw) => ! empty($kw) && mb_strlen($kw) <= 100)
          ->unique();

      foreach ($normalizedKws as $kw) {
          $currentCount = $session->keywords()->count();
          if ($currentCount >= 30) {
              break;
          }

          $exists = $session->keywords()->where('keyword', $kw)->exists();
          if (! $exists) {
              $session->keywords()->create(['keyword' => $kw]);
          }
      }
  }
  ```
- **Validation**: Verify that the database schema is updated correctly and the record counts are capped at 30 per session.

### [Low] Finding 5: Inefficient SQL LIKE keyword counting in Controller
- **Type**: Performance Risk
- **Location**: `backend/app/Http/Controllers/LiveSessionController.php` (lines 1157–1185)
- **Evidence**:
  ```php
  $count = $session->events()
      ->where('event_type', 'comment')
      ->where('data->comment', 'like', "%{$kw}%")
      ->count();
  ```
- **Why wrong/risky**: Executing `LIKE "%keyword%"` query for up to 30 keywords on the `live_events` table during polling can become slow for sessions with high comment volumes.
- **Impact**: Increased query latency on dashboard polling.
- **Minimal fix**: This is currently mitigated by caching (`Cache::remember` with a 5-second TTL during live, and 1-hour TTL when finished). The auto-discovery cap of 30 keywords also guarantees a maximum of 30 iterations. However, database indexes on `live_events(live_session_id, event_type)` must be verified.

---

## Product/UX/Text/Duplicate Issues
- None. The transition to automated keywords represents a positive simplification of Setup UI.

## Test Gaps
- Existing feature tests (`LiveSessionUIIntegrationTest.php`) create manual keywords via `$session->keywords()->create(...)` to test counts. These test fixtures should be updated to match the auto-discovery flow, or mock the `AnalyzeCommentsJob` behavior.

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test --filter=LiveSessionUIIntegrationTest` | No | N/A (Read-only mode) | Verifies current integrations pass | Verifies the new auto-discovery logic |

## Missed-risk / Limitations
- Runware AI response structure: The AI response schema needs to be robustly handled. If the AI returns strings in different formats or fails to output `extracted_keywords` array, a default empty array must be used to prevent PHP warnings.

## Suggested Fix Order
1. **AnalyzeCommentsJob**: Update AI prompt and write the persistence/deduping logic for discovered keywords with a limit of 30.
2. **LiveSessionController (API store)**: Remove keyword inputs from validation rules and creation routines.
3. **Setup.tsx (UI setup)**: Delete the manual keyword card and useForm keywords data state.
4. **Tests**: Update test cases in `LiveSessionUIIntegrationTest.php` to align with the changes.

## Decision
Fix before merge
