# Plan - AI Prompt Optimization

## Objective
Assess, optimize, and verify the AI prompt system in `CommentAnalyzer.php` and `LiveSessionAnalyzer.php` by converting system prompts to English, utilizing Chain-of-Thought (CoT) and XML structuring, while returning results in Vietnamese conforming to original JSON schemas.

## Milestones

| # | Milestone Name | Description | Status |
|---|----------------|-------------|--------|
| 1 | Exploration & Design | Analyze existing prompts in `CommentAnalyzer.php` and `LiveSessionAnalyzer.php` and design optimized English versions with CoT and XML. | PLANNED |
| 2 | Implementation | Update the system prompts in both files preserving compatibility and existing schemas. | PLANNED |
| 3 | Verification | Run unit tests, verify build succeeds, syntax check, and run Forensic Auditor. | PLANNED |

## Verification Strategy
- Run unit tests: `php artisan test` (backend)
- Build frontend (if modified/impacted, though we are not touching frontend): `npm run build`
- PHP syntax checks on updated files.
