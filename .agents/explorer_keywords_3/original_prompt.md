## 2026-05-22T08:38:28Z

You are a teamwork_preview_explorer.
Your working directory is: d:\Workspace\livestream\.agents\explorer_keywords_3
Your task is to explore and analyze the following files in the workspace (d:\Workspace\livestream) to support the "AI Auto-Discovery Keywords" milestone:
- Setup.tsx (backend/resources/js/Pages/Lives/Setup.tsx)
- LiveSessionController (backend/app/Http/Controllers/LiveSessionController.php)
- AnalyzeCommentsJob (backend/app/Jobs/AnalyzeCommentsJob.php)
- Show.tsx (backend/resources/js/Pages/Lives/Show.tsx)
- live_session_keywords table migration (backend/database/migrations/2026_05_21_000005_create_live_session_keywords_table.php)

Specifically:
1. R1: Find where manual keyword configuration is handled in Setup.tsx (input fields, badges, state, props) and LiveSessionController.php (validation, storing in database, etc.). Identify exactly what needs to be removed.
2. R2: Look at AnalyzeCommentsJob.php, the prompt used for AI analysis, and where it stores analyzed/extracted keywords. Identify how to update the prompt to return `extracted_keywords` (array of max 5 keywords, lowercase, short 1-3 words, focusing on products, price, quality, general queries) and how to standardize/persist them to `live_session_keywords` table with a per-session limit of ~30.
3. R3: Analyze LiveSessionController::getTopKeywords and Show.tsx. Show how the top keywords are counted using SQL LIKE query and how they are displayed in the dashboard in Show.tsx.

Produce a detailed analysis/handoff report in your working directory at `analysis.md` and send a message back with the link to the file. Follow the "Strict Evidence Audit v3 - 12k" rules.
