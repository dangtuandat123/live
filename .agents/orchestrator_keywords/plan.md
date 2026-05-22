# Plan: AI Auto-Discovery Keywords

## Steps
1. **Exploration & Code Reading**:
   - Spawn `teamwork_preview_explorer` to explore:
     - `Setup.tsx` (frontend configuration page)
     - `LiveSessionController` (backend logic for setup and keyword loading/counting)
     - `AnalyzeCommentsJob` (batch processing and AI prompt logic)
     - `Show.tsx` (frontend display page)
     - Database schema/migration for `live_session_keywords`
2. **Implementation**:
   - Spawn `teamwork_preview_worker` to:
     - Remove frontend tracking configuration from `Setup.tsx`.
     - Remove backend manual keyword validation and storing from `LiveSessionController`.
     - Update AI prompt in `AnalyzeCommentsJob` to extract keywords.
     - Save/standardize keywords to `live_session_keywords` database table (up to 30 per session).
     - Update `LiveSessionController::getTopKeywords` to dynamically count frequencies of these keywords using SQL `LIKE`.
     - Verify with `npm run build` and `php artisan test`.
3. **Review & Verification**:
   - Spawn `teamwork_preview_reviewer` to review code changes.
4. **Audit**:
   - Spawn `teamwork_preview_auditor` to audit integrity and correctness.
