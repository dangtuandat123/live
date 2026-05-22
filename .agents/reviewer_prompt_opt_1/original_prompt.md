## 2026-05-22T13:29:23Z
You are teamwork_preview_reviewer.
Your working directory is d:\Workspace\livestream\.agents\reviewer_prompt_opt_1\.
Please review the changes made by the worker for prompt optimization.

## Files Modified:
- `backend/app/Ai/Agents/CommentAnalyzer.php`
- `backend/app/Ai/Agents/LiveSessionAnalyzer.php`
- `backend/app/Jobs/AnalyzeCommentsJob.php`
- `backend/tests/Feature/AnalyzeCommentsJobTest.php`

## Review Objective:
1. Examine prompt correctness, completeness, robustness, and conformance to user requirements (English system prompts, CoT/Few-shot examples, XML tags, Vietnamese output text, unmodified JSON Schema/enums).
2. Run backend test suite (`php artisan test`) and verify that all tests pass.
3. Run frontend build (`npm run build`) and verify it compiles successfully.
4. Verify PHP syntax on modified files.
5. Write your review report to `review.md` and `handoff.md` in your working directory.
