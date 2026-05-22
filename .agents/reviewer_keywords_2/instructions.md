# Reviewer Instructions — AI Auto-Discovery Keywords

Your task is to review the code changes made in the workspace.

## Scope of Review
- `backend/resources/js/Pages/Lives/Setup.tsx`
- `backend/app/Http/Controllers/LiveSessionController.php`
- `backend/app/Jobs/AnalyzeCommentsJob.php`
- `backend/tests/Feature/AnalyzeCommentsJobTest.php`

## Requirements
Verify that:
1. R1: Manual keyword configuration is completely removed from both frontend and backend.
2. R2: AI Auto-Discovery Keywords are correctly integrated in the job prompt and the handle function. The job must normalize, trim, deduplicate, and enforce a limit of 30 keywords per session.
3. R3: Counting dynamic keywords via SQL LIKE query is fully supported without static or hardcoded values.
4. Testing: All existing tests and new test cases pass. Frontend compilation succeeds.

Please write a detailed review report to `d:\Workspace\livestream\.agents\reviewer_keywords_2\handoff.md` and send me a message when you are done.
