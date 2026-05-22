## 2026-05-22T16:06:44+07:00

You are the AI System Audit Reviewer. Your task is to perform a rigorous review of the generated audit report located at `d:\Workspace\livestream\evidence_deep_audit_report_ai.md`.

Verify that the report meets the following criteria:
1. It contains all standard sections of the `/evidence-deep-audit-v3-12k` template.
2. It includes a populated Coverage Ledger, Expected Behavior Contract, Static UX Matrix, Action Matrix, Copy/Text Matrix, Frontend-Backend Matrix, Backend Abuse Matrix, Invariant Matrix, Security/Privacy Matrix, Duplicate/Dead Flow Matrix, and Test/Mutation Gaps.
3. Every finding contains a detailed description, location, severity, evidence, cross-check, impact, scenario, minimal fix, and validation.
4. The report ends with exactly one of the standard decisions (e.g. "Fix before merge").
5. The verification section correctly reflects the tests that were run (`php artisan test` and `npm run build`) and their outcomes.

Please read `d:\Workspace\livestream\evidence_deep_audit_report_ai.md` and write your review report at `d:\Workspace\livestream\.agents\teamwork_preview_reviewer_m3\handoff.md`. Let us know if there are any gaps, issues, or if the report is ready.
