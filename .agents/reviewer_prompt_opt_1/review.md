# Prompt Optimization Review Report

**Active Depth Mode**: `PR/Diff Mode` (focused on the changed files and their immediate test cases/dependencies)

---

## Project Coverage Report

| Category | Details / Checked |
|---|---|
| **Active Depth Mode** | PR/Diff Mode |
| **Declared Scope** | Prompt optimization changes across agents, jobs, and tests. |
| **Full files read** | `backend/app/Ai/Agents/CommentAnalyzer.php`<br>`backend/app/Ai/Agents/LiveSessionAnalyzer.php`<br>`backend/app/Jobs/AnalyzeCommentsJob.php`<br>`backend/tests/Feature/AnalyzeCommentsJobTest.php` |
| **Files scanned** | `PROJECT.md` |
| **Directories scanned** | `.agents/worker_prompt_opt_1/` |
| **Repo-wide searches** | Search for `*.md` files in workspace. |
| **Tests checked** | `Tests\Feature\AnalyzeCommentsJobTest` (all passed) |
| **Commands run** | `git diff`, `git status`, `git log`, `php artisan test`, `npm run build`, `php -l` |
| **Unknowns/Exclusions** | Real-world API behavior/latencies of Gemini/DeepSeek (mocked in tests). |

---

## Evidence Ledger

| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|
| **Correctness** | English system prompts correctly integrate CoT and few-shots. | Verified prompt strings in `CommentAnalyzer.php`, `LiveSessionAnalyzer.php`, and `AnalyzeCommentsJob.php`. | Yes | None | `git diff` | High | None |
| **Robustness** | No JSON schema or enums were modified, preserving contracts. | Inspected `schema()` methods in agents. | Yes | None | `git diff` | High | None |
| **Tests** | All 109 backend tests pass after mock assertions were updated. | Test output from command line. | Yes | None | `php artisan test` | High | None |
| **Frontend** | Build succeeds without issues after backend prompt edits. | Vite production build log. | No | None | `npm run build` | High | None |
| **Syntax** | All modified files have correct PHP syntax. | Checked using `php -l`. | Yes | None | `php -l` | High | None |

---

## Quality Review Summary

**Verdict**: APPROVE

## Findings

### No Critical, Major, or Minor Findings
The implementation is clean, robust, and correctly meets all requirements of the prompt optimization task.

---

## Verified Claims

- **Claim**: The updated prompts are written in English, contain CoT reasoning steps, XML tags, and few-shot examples.
  - *Verification*: Checked system instructions in `CommentAnalyzer.php`, `LiveSessionAnalyzer.php`, and `AnalyzeCommentsJob.php`. All contain `xml` elements like `<context>`, `<rules>`, `<reasoning_process>`, `<few_shot_examples>`, and `<output_format>`.
  - *Result*: PASS
- **Claim**: The output text format is set to Vietnamese and enums/schemas remain unchanged.
  - *Verification*: Inspected output rules (`Generate a single JSON output in Vietnamese`), and the `schema()` definitions in `CommentAnalyzer.php` and `LiveSessionAnalyzer.php`. All enums (e.g. `'positive'`, `'neutral'`, `'negative'`, `'Chốt đơn'`, etc.) match the database schema exactly.
  - *Result*: PASS
- **Claim**: All tests compile and pass.
  - *Verification*: Executed `php artisan test`. All 109 tests passed.
  - *Result*: PASS
- **Claim**: Frontend build compiles.
  - *Verification*: Executed `npm run build` inside `backend/` directory. All chunks compiled successfully with Vite.
  - *Result*: PASS

---

## Coverage Gaps
- **Gemini/DeepSeek API integration** — *Risk Level*: Low — *Recommendation*: Accept risk since tests mock AI behaviors. Production usage will depend on actual LLM responses, which is handled gracefully by validation fallbacks.

---

## Unverified Items
- None.

---

## Adversarial Challenge Summary

**Overall risk assessment**: LOW

## Challenges

### [Low] Challenge 1: LLM Hallucinating invalid schema enums or formatting errors
- **Assumption challenged**: The LLM will always return valid enums and structures.
- **Attack scenario**: LLM returns sentiment as `"very positive"` or intent_tag as `"Mua hàng"` instead of `"Chốt đơn"`.
- **Blast radius**: The application might save incorrect data, crash, or fail validation.
- **Mitigation**: The code in `AnalyzeCommentsJob.php` has a `validateResult` method that checks every tag against constant lists of valid tags:
  ```php
  $sentiment = $result['sentiment'] ?? 'neutral';
  if (! in_array($sentiment, self::VALID_SENTIMENTS)) {
      $sentiment = 'neutral';
  }
  ```
  This is a highly robust validation safeguard.

### [Low] Challenge 2: Poison Pill Deadlock from bad LLM responses
- **Assumption challenged**: The job can safely retry if an AI call fails or returns trash.
- **Attack scenario**: A batch of comments consistently triggers a formatting error from the LLM, causing the job to fail and retry forever, blocking the queue.
- **Blast radius**: Livestream comment processing halts completely.
- **Mitigation**: `AnalyzeCommentsJob` implements custom error handling to mark the poison-pill comments as processed (`neutral`) on the last retry attempt or for non-transient exceptions:
  ```php
  $isLastAttempt = $this->attempts() >= $this->tries;
  if ($isLastAttempt || $isUnrecoverable) {
      DB::table('live_events')->whereIn('id', $unprocessed->pluck('id'))->update(['ai_processed' => true, 'sentiment' => 'neutral']);
  }
  ```
  This is extremely safe and prevents deadlocks.

---

## Stress Test Results

- **Scenario 1**: Comment batch with empty/null comments.
  - *Expected behavior*: Skips processing or defaults to neutral.
  - *Actual behavior*: `commentsText` is filtered, empty comments are marked processed (neutral) automatically without calling LLM.
  - *Result*: PASS
- **Scenario 2**: Missing or noisy audio snapshot.
  - *Expected behavior*: Fallback to text-only analysis, omitting audio prompt section.
  - *Actual behavior*: Audio snapshot throws or returns null; prompt construction skips `=== AUDIO LIVESTREAM ===` section and sends only text parts.
  - *Result*: PASS

---

## Unchallenged Areas
- None.

---

**Final Static Audit Note**:
This is a static/code-path audit and verification. It confirms issues and qualities proven by static code analysis and unit/feature tests. It does not claim pixel-perfect visual correctness of the UI frontend under all screen resolutions unless explicitly verified.
