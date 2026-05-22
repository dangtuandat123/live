# Audit Report

## Summary
- Scope: Analyze files in the livestream workspace (Setup.tsx, LiveSessionController.php, AnalyzeCommentsJob.php, Show.tsx, live_session_keywords table migration) to support the transition from manual keyword configuration to AI auto-discovery keywords.
- Mode: static/code-path audit
- Confidence: High
- Critical: 0
- High: 2 (AI prompt integration architecture, SQL LIKE performance risk)
- Medium: 1 (Manual keyword UI/backend cleanup)
- Low: 0
- Decision: Safe within audited scope

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Livestream Keyword Tracking Feature (Manual vs AI-Discovery) |
| Stack/framework | Laravel (PHP 8.2+), React (TypeScript), Inertia.js, Tailwind CSS, PostgreSQL/MySQL (JSON columns) |
| Expected user behavior | Start a session without manually specifying keywords. The AI analyzes comments, discovers relevant keywords dynamically, and the dashboard displays them grouped and counted. |
| Expected backend/data behavior | The AI analysis prompt requests `extracted_keywords`. The job normalizes them (lowercase, 1-3 words) and stores them in the `live_session_keywords` table (capped at ~30 per session). The controller queries and returns counts of these keywords. |
| Source of truth | Source code files: `Setup.tsx`, `LiveSessionController.php`, `AnalyzeCommentsJob.php`, `Show.tsx`, and model `LiveSessionKeyword.php`. |
| Exclusions | Runtime execution of the changes (this is a read-only investigation). |

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 2 | 2 | 0 | `Setup.tsx` and `Show.tsx` |
| User actions | 2 | 2 | 0 | Adding/removing keywords, viewing top keywords card |
| API/actions | 3 | 3 | 0 | `lives.store`, `lives.show`, `lives.fetch-events` |
| Services/domain | 2 | 2 | 0 | `AnalyzeCommentsJob` AI pipeline and `RunwareAiService` |
| DB/schema/config | 2 | 2 | 0 | `live_session_keywords` migration and `LiveSessionKeyword` model |
| Auth/permissions | 1 | 1 | 0 | Controller-level auth gating |
| State/cache | 1 | 1 | 0 | Keyword query caching (5s TTL) |
| Tests | 2 | 2 | 0 | `LiveSessionUIIntegrationTest`, `AnalyzeCommentsJobTest` |

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Manual keyword setup removal | Product Spec / R1 | High | Leftover input boxes, badges, validation rules, or DB store logic |
| AI Prompt keyword extraction | AI Spec / R2 | High | Prompt doesn't specify lowercase, word count, limit, or JSON schema |
| AI Keyword standardization/limit | Logic Spec / R2 | High | Duplicates stored, uppercase strings, empty strings, or session count > 30 |
| SQL LIKE query logic | Code Spec / R3 | High | getTopKeywords queries matching data->comment count for session keywords |
| Dashboard keywords rendering | Frontend Spec / R3 | High | Rendering crashes on empty keywords list or wrong data shape |

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Setup.tsx` | "Từ khóa theo dõi" Card & Input | Setup.tsx lines 312-363 | No manual input UI since it's auto-discovered | Input field, button, and badge list for manual setup | Redundant UI that blocks auto-discovery transition. |
| `Show.tsx` | "Từ khóa được nhắc nhiều" Card | Show.tsx lines 3206-3237 | Render dynamic keyword badges with count | Correctly maps `topKeywords` array to badges | None (UI is ready for dynamic keywords). |

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Create Session | `handleSubmit` (Setup.tsx) | `name`, `tiktok_username` | Disabled on form submission | Redirect to Show page / errors shown | POST `lives.store` | Submitting `keywords` array is obsolete. |
| Poll Live Data | `fetchEvents` interval (Show.tsx) | Session ID | Handled in background | Updates `topKeywords` state | POST `lives.fetch-events` | Loop queries database every 5 seconds. |

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Từ khóa bán hàng mà AI sẽ ưu tiên phát hiện trong bình luận" | Setup.tsx line 315 | Describe the feature accurately | Explains manual configuration | Misleading once auto-discovery is enabled. |
| "Từ khóa được nhắc nhiều" | Show.tsx line 3210 | Title for discovered keywords | Title matches | None. |

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Create Session | Setup.tsx | `lives.store` | `{name, tiktok_username, keywords}` | Check user auth and validate inputs | Saves to `live_sessions` and `live_session_keywords` | Redirect | Frontend manual keywords need removal; backend validation/save needs removal. |
| Live Dashboard | Show.tsx | `lives.fetch-events` | `POST /lives/{id}/fetch-events` | Validate session status and user auth | Hits cache or getTopKeywords | JSON containing `topKeywords` | None. |

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `POST /lives` | 401 Unauthorized | N/A | N/A | N/A | Correctly gated |
| `POST /lives/{id}/fetch-events` | 401 Unauthorized | 403 Forbidden (policy checked) | N/A | N/A | Correctly gated |
| `lives.store` (malicious request) | 401 Unauthorized | N/A | Extra `keywords` array submitted | N/A | Saves invalid keywords if not stripped |

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Max 30 keywords per session | `AnalyzeCommentsJob` | Job tries to insert duplicate/unbounded keywords | No current checks in code | Potential database bloat |
| Lowercase standard | `AnalyzeCommentsJob` | LLM returns mixed/uppercase keywords | No current normalization | Keywords display with inconsistent casing |

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Database Performance | Normal user | Repeated polling/high keyword count | LOOP SQL LIKE queries without robust indexing | DB CPU exhaustion / deadlock | High |

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| Manual keyword storage | `LiveSessionController::store` | Redundant save logic | In `store()` (lines 163-168), it creates manual keywords in `live_session_keywords` table. |

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Top Keywords Query | `LiveSessionUIIntegrationTest` | Delete/mock empty keywords list | Yes | Needs explicit unit test asserting AI-discovered keyword limits and lowercase normalization |

## Findings

### [Medium] Manual Keyword UI and Backend Processing
- **Type**: Code Cleanup / UI Redundancy
- **Location**:
  - `backend/resources/js/Pages/Lives/Setup.tsx` (lines 69, 72, 84-97, 312-363)
  - `backend/app/Http/Controllers/LiveSessionController.php` (lines 125-126, 163-168)
- **Evidence**:
  - `Setup.tsx` form state contains: `keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[]`
  - Setup form has manual keywords input card mapping to `form.data.keywords`.
  - `LiveSessionController::store` validates `'keywords' => ['nullable', 'array']` and `'keywords.*' => ['string', 'max:100']`.
  - Storing logic:
    ```php
    if (! empty($validated['keywords'])) {
        foreach ($validated['keywords'] as $keyword) {
            $session->keywords()->create(['keyword' => $keyword]);
        }
    }
    ```
- **Cross-check**: Confirmed relationship in `LiveSession::keywords` model definition.
- **Why wrong/risky**: Users should not manually configure keywords because the system is migrating to AI-driven keyword auto-discovery. Keeping these code blocks increases technical debt, confuses the user, and wastes request payload bytes.
- **Impact**: UI clutter and redundant DB insertions.
- **Minimal fix**:
  - Delete `keywords` configuration card, functions, and state in `Setup.tsx`.
  - Remove validation rules and saving block in `LiveSessionController::store`.

### [High] LLM Prompt Format & Keyword Persisting Logic
- **Type**: Architectural Gap / Data Integrity
- **Location**: `backend/app/Jobs/AnalyzeCommentsJob.php`
- **Evidence**:
  - System prompt specifies return format (lines 485-487):
    `{"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", ...}], "session_note": "string"}`
  - No handling of `extracted_keywords` in AI response processing block.
- **Cross-check**: LLM integration is handled using `RunwareAiService::chatMultimodal`.
- **Why wrong/risky**: Currently, the AI does not return keywords, and the job does not persist any discovered keywords. To support auto-discovery, the system prompt must explicitly request a list of keywords and specify normalization rules (lowercase, 1-3 words, max 5 per batch). The job must collect, filter, lowercase, and limit them to 30 per session to avoid DB size bloat and slow queries.
- **Impact**: Feature will not work; keywords will not be discovered or stored in `live_session_keywords` table.
- **Minimal fix**:
  1. Update system prompt returned JSON schema:
     `{"results": [...], "session_note": "...", "extracted_keywords": ["kw1", "kw2", ...]}`
  2. Add instructions to prompt:
     - Return maximum 5 keywords extracted from the current batch of comments.
     - Keywords must be in lowercase.
     - Keywords must be short (1-3 words), focusing on products, price, quality, or popular queries.
  3. In `AnalyzeCommentsJob::handle()`, extract `extracted_keywords` and persist them using standard Eloquent checks:
     ```php
     $extracted = $response['extracted_keywords'] ?? [];
     $this->persistExtractedKeywords($session, $extracted);
     ```
  4. Implement `persistExtractedKeywords`:
     ```php
     private function persistExtractedKeywords(LiveSession $session, array $extractedKeywords): void
     {
         $maxLimit = 30;
         $standardized = collect($extractedKeywords)
             ->map(fn($kw) => mb_strtolower(trim($kw)))
             ->filter(fn($kw) => !empty($kw) && mb_strlen($kw) <= 100)
             ->unique();

         if ($standardized->isEmpty()) {
             return;
         }

         $existingKeywords = $session->keywords()->pluck('keyword')->map(fn($k) => mb_strtolower($k))->toArray();
         $currentCount = count($existingKeywords);

         foreach ($standardized as $keyword) {
             if ($currentCount >= $maxLimit) {
                 break;
             }
             if (!in_array($keyword, $existingKeywords)) {
                 $session->keywords()->create(['keyword' => $keyword]);
                 $existingKeywords[] = $keyword;
                 $currentCount++;
             }
         }
     }
     ```

### [High] SQL LIKE Query Loop Performance Risk
- **Type**: Performance Risk
- **Location**: `LiveSessionController::getTopKeywords` (lines 1157-1185)
- **Evidence**:
  ```php
  foreach ($setupKeywords as $kw) {
      $kw = trim($kw);
      if ($kw === '') {
          continue;
      }
      $count = $session->events()
          ->where('event_type', 'comment')
          ->where('data->comment', 'like', "%{$kw}%")
          ->count();
      if ($count > 0) {
          $keywordCounts[] = [
              'keyword' => $kw,
              'count' => $count,
          ];
      }
  }
  ```
- **Cross-check**: Called inside dashboard data payload fetch loop (every 5 seconds).
- **Why wrong/risky**: `LIKE "%{$kw}%"` queries over JSON paths are extremely slow as they cannot use standard indexes, requiring full table scans. Doing this in a loop for 30 keywords every 5 seconds for each active session will quickly saturate DB CPU under concurrent streams.
- **Impact**: High DB load, API latency spikes, potential stream disconnects.
- **Minimal fix**:
  - Keep counts aggregated or cached with a longer TTL for active streams.
  - Or, maintain an incremental counter or trigger index optimization.

## Product/UX/Text/Duplicate Issues
- The text description under keywords card: *"Các từ khóa bán hàng mà AI sẽ ưu tiên phát hiện trong bình luận"* in `Setup.tsx` line 315 will be obsolete and must be removed along with the card.

## Test Gaps
- The `LiveSessionUIIntegrationTest` mocks/seeds manual keywords. Once the UI is deleted, these tests still work since they call relations directly on the model:
  `$session->keywords()->create(['keyword' => 'kem']);`
  However, we should add unit tests for `AnalyzeCommentsJob` asserting:
  1. AI-extracted keywords are correctly normalized to lowercase.
  2. Duplicate keywords are ignored.
  3. Total session keywords never exceed the 30-item limit.

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test` | No (Read-only) | N/A | Tests pass on unchanged codebase | Success of proposed code changes |

## Missed-risk / Limitations
- LLM output parsing failure (e.g., JSON syntax issues when LLM returns invalid `extracted_keywords` arrays or wrong types) is managed by the fallback `ai_processed = true` poison pill logic in the job, but could lead to transient lost keywords.

## Suggested Fix Order
1. **Backend Prompt & Processing (`AnalyzeCommentsJob.php`)**: Update prompt instructions, parse return array, and implement standardizing/persisting method with the 30-item cap.
2. **Frontend UI Cleanup (`Setup.tsx`)**: Remove manual keywords input block and form state.
3. **Backend API Validation Cleanup (`LiveSessionController.php`)**: Strip manual keyword storage from the `store` API handler.
4. **Performance Check (`LiveSessionController.php`)**: Optimize or verify caching profile of `getTopKeywords()`.

## Decision
Safe within audited scope
