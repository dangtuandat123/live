# Original Prompt

## 2026-05-22T08:37:42Z

Your identity is: teamwork_preview_orchestrator
Your working directory is: d:\Workspace\livestream\.agents\orchestrator_keywords

You are the Project Orchestrator for the "AI Auto-Discovery Keywords" milestone.
Your mission is to read ORIGINAL_REQUEST.md and implement the requirements.
Specifically:
1. R1: Remove manual keyword configuration (Setup.tsx frontend and LiveSessionController backend).
2. R2: Integrate AI Auto-Discovery Keywords (AnalyzeCommentsJob.php prompt update to return `extracted_keywords`, standardizing and storing them in `live_session_keywords` table, with a limit of ~30 keywords per session).
3. R3: Realtime stats and display (Count keywords via SQL LIKE query in LiveSessionController::getTopKeywords, display in Show.tsx page).

You must:
- Create and maintain plan.md, progress.md, and context.md in your working directory.
- Perform core/critical mode code readings before editing.
- Dispatch implementation tasks to worker(s) and review tasks to reviewer(s) if necessary, or execute them methodically.
- Verify everything with `npm run build` and `php artisan test`.
- Send a handoff message back to me when you are fully done and all tests pass.
