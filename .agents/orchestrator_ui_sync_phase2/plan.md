# Plan — UI and Backend Alignment Phase 2

## Goal
Resolve five key requirements (R1: Conversion Funnel, R2: Labeling, R3: Cache Invalidation, R4: Redundancy & Clean Code, R5: Phone Extraction synchronization) between React frontend and Laravel backend.

## Phase 1: Exploration & Codebase Analysis
- **Goal**: Identify exact lines and logic for the 5 requirements in backend controllers, jobs, and frontend pages.
- **Action**: Spawn `teamwork_preview_explorer` to do a deep static analysis and document recommended changes.
- **Verification**: Check the explorer's report for accurate file paths, code snippets, and alignment plans.

## Phase 2: Implementation
- **Goal**: Implement backend and frontend alignment changes based on the exploration findings.
- **Action**: Spawn `teamwork_preview_worker` to apply the fixes.
- **Rules**: Follow strict security, performance, quality, and vietnam-translation / premium UX rules. No hardcoding.

## Phase 3: Review & Verification
- **Goal**: Verify that code matches conventions and tests pass.
- **Action**: Spawn `teamwork_preview_reviewer` to check the diff.
- **Action**: Run `php artisan test` and `npm run build` using the subagents to verify the build and tests.
- **Action**: Spawn `teamwork_preview_auditor` to check for integrity violations.
