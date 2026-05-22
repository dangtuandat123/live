# Handoff Report

## 1. Observation
We observed the existing prompt structures, schemas, and test expectations:
- **CommentAnalyzer Prompt & Schema**:
  - File: `d:\Workspace\livestream\backend\app\Ai\Agents\CommentAnalyzer.php` (lines 42–88) contains a Vietnamese system prompt.
  - Schema (lines 90–112) specifies output structure returning an array of objects matching keys: `id`, `sentiment`, `intent_tag`, `question_tag`, `product_tag`, `has_phone`.
- **AnalyzeCommentsJob Prompt & Test Requirements**:
  - File: `d:\Workspace\livestream\backend\app\Jobs\AnalyzeCommentsJob.php` (lines 617–653) contains the background job's system prompt builder, outputting additional keys: `session_note` and `extracted_keywords` and taking context blocks `=== BỘ NHỚ PHIÊN LIVE ===` and `=== AUDIO LIVESTREAM ===`.
  - File: `d:\Workspace\livestream\backend\tests\Feature\AnalyzeCommentsJobTest.php` (lines 113–114):
    ```php
    $this->assertStringContainsString('Chốt đơn', $instructions);
    $this->assertStringContainsString('cú pháp đặt hàng', $instructions);
    ```
    asserts that the system prompt must explicitly contain `'Chốt đơn'` and `'cú pháp đặt hàng'`.
- **LiveSessionAnalyzer Prompt & Schema**:
  - File: `d:\Workspace\livestream\backend\app\Ai\Agents\LiveSessionAnalyzer.php` (lines 67–91) contains a Vietnamese prompt asking for `summary` and `alerts`.
  - Schema (lines 94–108) defines returning `summary` and `alerts` (having keys `type`, `title`, `desc`, `action`).
- **Tests Execution**:
  - Ran `php artisan test tests/Feature/AnalyzeCommentsJobTest.php tests/Feature/LiveSessionAiInsightsTest.php` using the terminal and verified all 25 tests pass.

---

## 2. Logic Chain
- **Step 1**: To satisfy the prompt optimization requirements, we must translate the system prompts of both analyzers (`CommentAnalyzer` and `LiveSessionAnalyzer`) to English, structure them with XML tags, and implement Chain-of-Thought (CoT) and few-shot examples (supporting Vietnamese livestream nuances like customer consultation being neutral, not negative).
- **Step 2**: Based on the assertion constraints in `AnalyzeCommentsJobTest.php` (Observation 1), the optimized `CommentAnalyzer` English prompt must retain the literal Vietnamese strings `'Chốt đơn'` and `'cú pháp đặt hàng'` inside its instructions text (e.g. as references or example explanations).
- **Step 3**: To avoid breaking database updates or UI displays, the output JSON schema and the PHP class attributes must remain exactly backward-compatible. This means enums (such as `intent_tag` values 'Chốt đơn', 'Hỏi thông tin', 'Phản hồi SP', 'Yêu cầu hỗ trợ' and all `question_tag` values) must be output in Vietnamese.
- **Step 4**: Since `AnalyzeCommentsJob.php` builds its own prompt in `buildSystemPrompt()`, this function must be optimized in sync with `CommentAnalyzer` to prevent background comments analysis from running obsolete prompt logic.

---

## 3. Caveats
- We did not implement these changes directly on the production files in `backend/app/Ai/Agents/` or `backend/app/Jobs/` because we are a read-only investigation explorer. Instead, we have written the fully optimized classes to our working directory (Artifacts Index below).
- We assume that the LLM models configured in the environment (`deepseek-v4-flash` or `gemini-3.1-flash-lite`) support English system instructions combined with Vietnamese output constraints without hallucinating or defaulting to English output text.

---

## 4. Conclusion
We have completed the optimization design of system prompts. The proposed prompts structure instructions cleanly with XML tags, incorporate reasoning guides, and provide few-shot examples of inputs and Vietnamese JSON outputs. We have also drafted the synchronization update for `AnalyzeCommentsJob.php`.

---

## 5. Verification Method
After applying the proposed changes (copying proposed files to their actual paths):
1. **PHP Syntax Verification**:
   ```bash
   php -l backend/app/Ai/Agents/CommentAnalyzer.php
   php -l backend/app/Ai/Agents/LiveSessionAnalyzer.php
   php -l backend/app/Jobs/AnalyzeCommentsJob.php
   ```
2. **PHPUnit Test Verification**:
   ```bash
   php artisan test tests/Feature/AnalyzeCommentsJobTest.php tests/Feature/LiveSessionAiInsightsTest.php
   ```
3. **Frontend Compile Verification**:
   ```bash
   npm run build
   ```

---

## Remaining Work
The following next steps must be performed by the implementer agent:
1. Replace `d:\Workspace\livestream\backend\app\Ai\Agents\CommentAnalyzer.php` with the content of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_CommentAnalyzer.php`.
2. Replace `d:\Workspace\livestream\backend\app\Ai\Agents\LiveSessionAnalyzer.php` with the content of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_LiveSessionAnalyzer.php`.
3. Update the `buildSystemPrompt` function in `d:\Workspace\livestream\backend\app\Jobs\AnalyzeCommentsJob.php` with the content of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_AnalyzeCommentsJob_buildSystemPrompt.php`.
4. Run validation checks to verify syntax and test compliance.
