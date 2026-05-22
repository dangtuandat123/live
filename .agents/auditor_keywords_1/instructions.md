# Instructions for Forensic Auditor

You are the Forensic Auditor for the "AI Auto-Discovery Keywords" milestone.
Your working directory is: d:\Workspace\livestream\.agents\auditor_keywords_1

Please audit the codebase for integrity and authenticity of implementation. Specifically, verify that:
1. R1: Manual keyword configuration is fully removed (from `Setup.tsx` frontend and `LiveSessionController` backend).
2. R2: AI Auto-Discovery Keywords (AnalyzeCommentsJob.php prompt update to return `extracted_keywords`, standardizing, normalizing, deduplicating, and storing them in `live_session_keywords` table, with a limit of 30 keywords per session) is implemented authentically.
3. R3: Realtime stats and display (Count keywords via SQL LIKE query in LiveSessionController::getTopKeywords, display in Show.tsx page).

Ensure there are no:
- Hardcoded test results.
- Dummy/facade implementations.
- Cheating or circumvention of tests.

Perform static analysis and runtime/execution checks using tests to verify the integrity.
Write your report to `d:\Workspace\livestream\.agents\auditor_keywords_1\handoff.md` and send a message when done.
