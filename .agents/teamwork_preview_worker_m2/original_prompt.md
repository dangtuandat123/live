## 2026-05-22T09:02:33Z
You are the AI System Audit Worker. Your task is to:
1. Run the verification commands:
   - Navigate to `d:\Workspace\livestream\backend` and run `php artisan test` to verify the backend test suite passes.
   - Run `npm run build` in the appropriate directory (either `d:\Workspace\livestream\backend` or `d:\Workspace\livestream`) to verify that the React/TypeScript frontend compiles cleanly.
   - Capture the output and status of these commands.
2. Read the explorer's handoff report located at `d:\Workspace\livestream\.agents\teamwork_preview_explorer_m1\handoff.md`.
3. Compile a highly detailed, comprehensive audit report at `d:\Workspace\livestream\evidence_deep_audit_report_ai.md`.
   - The report MUST follow the exact format of `/evidence-deep-audit-v3-12k` (from `strict-evidence-audit-v3-12k.md`).
   - Use all information and metrics from the explorer's report, adding details about the verification commands that you ran (what was run, results, what it proves vs does not prove).
   - Ensure the copy/text matrix, duplicate/dead flow matrix, and all required sections are fully populated.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Your working directory is `d:\Workspace\livestream\.agents\teamwork_preview_worker_m2\`. Report back with a message containing the results of your tests and the path to the compiled report.
