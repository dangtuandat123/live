## 2026-05-21T13:59:37Z
You are an Explorer agent. Your working directory is d:\Workspace\livestream\.agents\explorer_1 (please create it or write there).
Your mission is to perform the initial stage of the evidence-driven deep audit on the TikTok livestream comment analysis pipeline (Solution G: Text + Audio + Memory).
Target files:
- backend/app/Jobs/AnalyzeCommentsJob.php
- backend/app/Models/LiveSession.php
- backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php
- backend/tests/Feature/AnalyzeCommentsJobTest.php

Please:
1. Read and analyze each of these files fully.
2. Draft an Expected Behavior Contract (what each file/component is supposed to do, inputs, outputs, side effects, invariants).
3. Identify potential bugs, race conditions, edge cases, type safety issues, database integrity issues, or performance issues (like N+1 queries or cache issues).
4. Write your findings and the draft of the 18-pass audit matrices to `d:\Workspace\livestream\.agents\explorer_1\analysis.md`.
5. Write a handoff report at `d:\Workspace\livestream\.agents\explorer_1\handoff.md`.
6. Once finished, send a message to the orchestrator (conversation ID: d74b98dc-e0bd-4c10-ad7c-e7f6d6cd9d2c) with your results.
