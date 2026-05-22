## 2026-05-22T13:27:11Z

You are teamwork_preview_worker.
Your working directory is d:\Workspace\livestream\.agents\worker_prompt_opt_1\.
Your task is to implement the prompt optimizations in the codebase.

## Objective
Apply the proposed English prompts with XML tags and Chain-of-Thought reasoning to the PHP classes and synchronize the duplicated prompt in the background job:
1. Replace the contents of `backend/app/Ai/Agents/CommentAnalyzer.php` with the contents of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_CommentAnalyzer.php`.
2. Replace the contents of `backend/app/Ai/Agents/LiveSessionAnalyzer.php` with the contents of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_LiveSessionAnalyzer.php`.
3. In `backend/app/Jobs/AnalyzeCommentsJob.php`, replace the `buildSystemPrompt` function (lines 567 to 654) with the contents of `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_AnalyzeCommentsJob_buildSystemPrompt.php`.

## Verification Instructions
After replacing the files, perform the following verification:
1. Run syntax checks:
   - `php -l backend/app/Ai/Agents/CommentAnalyzer.php`
   - `php -l backend/app/Ai/Agents/LiveSessionAnalyzer.php`
   - `php -l backend/app/Jobs/AnalyzeCommentsJob.php`
2. Run backend test suite:
   - Run `vendor\bin\phpunit` or `php artisan test` inside the `backend` folder. Specifically ensure that `tests/Feature/AnalyzeCommentsJobTest.php` and `tests/Feature/LiveSessionAiInsightsTest.php` pass successfully.
3. Verify frontend build:
   - Run `npm run build` in the backend folder to make sure it builds without errors.
4. Document all findings, command outputs, and file changes in `changes.md` and `handoff.md` under your working directory `d:\Workspace\livestream\.agents\worker_prompt_opt_1\`.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.
