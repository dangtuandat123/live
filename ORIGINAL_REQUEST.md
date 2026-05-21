# Original User Request

## Initial Request — 2026-05-21T13:57:31Z

# Teamwork Project Prompt — Final

The user wants to perform an evidence-driven deep audit (18-pass workflow) on the implemented "Solution G: Text + Audio + Memory" for their TikTok livestream comment analysis pipeline, checking if everything is implemented correctly according to the best standards and identifying any errors or issues.

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Deep Audit Implementation
Thoroughly audit the AI comment analysis pipeline (Text + Audio + Memory) implemented in the following target files:
- backend/app/Jobs/AnalyzeCommentsJob.php
- backend/app/Models/LiveSession.php
- backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php
- backend/tests/Feature/AnalyzeCommentsJobTest.php

Identify any bugs, race conditions, edge cases, type safety issues, database integrity issues, or performance issues (like N+1 queries or cache issues).

### R2. Adherence to Rules
Verify that the implementation fully adheres to the specific global user rules (RULE[user_global] / RULE[agent.md]) and the Evidence Deep Audit workflow rules.

## Acceptance Criteria

### Audit Completion
- [ ] Perform a full static and code-path analysis covering all 18 passes of the evidence-deep-audit-v3-12k workflow.
- [ ] Produce a comprehensive Audit Report in markdown at `C:\Users\ADMIN\.gemini\antigravity\brain\9e05c9cd-c52d-4900-bfb1-3c02aa45407d\evidence_deep_audit_report.md` covering:
  - Scope Lock + Stack Profile
  - Expected Behavior Contract
  - Coverage Ledger
  - Static UX Matrix, Action Matrix, Copy/Text Matrix
  - Frontend-Backend Matrix, Backend Abuse Matrix
  - Invariant Matrix, State/Async/Race Matrix
  - Security/Privacy Matrix, Performance/Reliability/Data Integrity Matrix
  - Test/Mutation Gap Matrix
  - Findings classified by severity (Critical, High, Medium, Low, Info) with exact locations, evidence, impact, and minimal fixes.
- [ ] Ensure all automated tests (`php artisan test`) pass successfully.

## Follow-up — 2026-05-21T14:14:15Z

# Teamwork Project Prompt — Final

The user wants the teamwork multi-agent team to fix all the High and Medium severity bugs and performance bottlenecks identified in the recent AI comment analysis pipeline audit report ([evidence_deep_audit_report.md](file:///C:/Users/ADMIN/.gemini/antigravity/brain/9e05c9cd-c52d-4900-bfb1-3c02aa45407d/evidence_deep_audit_report.md)).

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Fix Pipeline Stalls
- Address the *Text-less Comments Pipeline Stall* and *Unrecoverable Error Stall* in `AnalyzeCommentsJob.php` so that the comment analysis pipeline continues processing the next batches of comments under all conditions (emoji-only batches, failures, etc.).

### R2. Optimize Stats Aggregation
- Replace the O(N^2) stats recalculation query in `AnalyzeCommentsJob.php` with an efficient delta/incremental update or optimized calculation mechanism to prevent performance degradation on long livestreams.

### R3. Resolve Robustness & Reliability Risks
- Fix the *TypeError Risk* (null check on TikTok live snapshot).
- Adjust the unique lock duration or mechanism to prevent race conditions during long-running API requests.
- Address the brittle manual cache key clearing logic.

### R4. Test Coverage & Validation
- Ensure all automated tests under `backend/tests/Feature/AnalyzeCommentsJobTest.php` pass successfully.
- Implement tests verifying correct pipeline progression on text-less batches and validation of stats updates.

## Acceptance Criteria

### Correct Execution
- [ ] Comment analysis does not stall on emoji-only batches or recoverable/unrecoverable errors.
- [ ] Database stats are updated accurately without full table scans on every batch.
- [ ] All feature tests in `AnalyzeCommentsJobTest.php` pass cleanly without mock mismatch or errors.

