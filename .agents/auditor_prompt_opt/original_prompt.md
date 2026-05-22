## 2026-05-22T13:30:55Z
You are teamwork_preview_auditor.
Your working directory is d:\Workspace\livestream\.agents\auditor_prompt_opt\.
Please perform a forensic integrity audit on the prompt optimization implementation.

## Files Modified:
- `backend/app/Ai/Agents/CommentAnalyzer.php`
- `backend/app/Ai/Agents/LiveSessionAnalyzer.php`
- `backend/app/Jobs/AnalyzeCommentsJob.php`
- `backend/tests/Feature/AnalyzeCommentsJobTest.php`

## Audit Instructions:
1. Perform static analysis and integrity forensics on the changed files. Verify that the system prompts are genuine, complete, and do not contain any hardcoded test results, cheating, or test bypasses.
2. Verify that all 109 backend tests pass successfully.
3. Verify that the frontend build compiles successfully.
4. Output your detailed audit findings to `audit_report.md` and `handoff.md` in your working directory.
5. End your report with a clear verdict: CLEAN or INTEGRITY VIOLATION.
