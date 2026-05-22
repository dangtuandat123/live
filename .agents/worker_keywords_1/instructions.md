# Worker Instructions — AI Auto-Discovery Keywords

Your task is to implement the "AI Auto-Discovery Keywords" milestone in the workspace.

## ⚠️ MANDATORY INTEGRITY WARNING — DO NOT CHEAT
> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.

## Requirements

### R1: Remove manual keyword configuration
- **Frontend `Setup.tsx` (`backend/resources/js/Pages/Lives/Setup.tsx`)**:
  - Remove `keywords` from the `useForm` hook initialization (line 69).
  - Remove `keywordInput` state hook (line 72).
  - Remove `addKeyword()` and `removeKeyword()` helper functions (lines 84–97).
  - Remove the Card UI block rendering "Từ khóa theo dõi" inputs, badges, and Add button (lines 312–363).
- **Backend `LiveSessionController.php` (`backend/app/Http/Controllers/LiveSessionController.php`)**:
  - In the `store()` method, remove the validation rules for `keywords` and `keywords.*` (lines 125–126).
  - In the `store()` method, remove the logic that loops over `$validated['keywords']` and stores them in the database (lines 163–168).

### R2: Integrate AI Auto-Discovery Keywords
- **Backend `AnalyzeCommentsJob.php` (`backend/app/Jobs/AnalyzeCommentsJob.php`)**:
  - Update `buildSystemPrompt()`:
    - Instruct the model to return `extracted_keywords` in the JSON root object (max 5 keywords per batch, lowercase, short 1-3 words, related to products, price, quality, or general queries).
    - Update the sample JSON output format in the prompt to match this structure:
      `{"results": [...], "session_note": "...", "extracted_keywords": ["keyword1", "keyword2"]}`
  - In `handle()`:
    - Parse `extracted_keywords` from the JSON response array returned by Runware AI.
    - Normalize each keyword (convert to lowercase, trim spaces).
    - Filter out duplicates and empty strings.
    - Persist new keywords to the `live_session_keywords` table associated with the current session.
    - Enforce a **hard limit of 30 keywords** per session:
      - Query the current count of keywords in `live_session_keywords` for this session.
      - If it has already reached 30, do not add more.
      - If it is below 30, append new keywords from the batch (ignoring duplicates) until the total count reaches 30.

### R3: Realtime stats and display
- Confirm that `LiveSessionController::getTopKeywords()` counts comment frequencies using SQL `LIKE` queries dynamically against the keywords stored in `live_session_keywords`. Ensure no hardcoding or static data is returned.
- Confirm that `Show.tsx` page displays the top keywords dynamically. (No code changes should be needed for R3 since the existing counting/rendering logic is already fully dynamic and queries the database keywords table).

## Testing and Verification
- Run `npm run build` to verify frontend assets build without errors.
- Run `php artisan test` to check the entire test suite.
- Update/add tests in `tests/Feature/AnalyzeCommentsJobTest.php` to verify:
  - `AnalyzeCommentsJob` extracts `extracted_keywords` from LLM JSON response.
  - Normalization (lowercase, trim) and deduplication are applied.
  - The limit of 30 keywords per session is strictly enforced.
- Ensure all existing tests pass successfully.

## Handoff Report
Upon completion, write a detailed handoff report in your folder `d:\Workspace\livestream\.agents\worker_keywords_1\handoff.md` and notify me via message. Include:
1. Detailed summary of files changed.
2. Commands run (build, tests) and output results.
3. Test code added/modified.
