## 2026-05-22T03:35:38Z
You are the Worker. Your working directory is d:\Workspace\livestream\.agents\worker_ui_sync_fix_1.
Your task is to fix a critical logic bug in the livestream duration gating logic.

Specifically:
- In `backend/app/Http/Controllers/LiveSessionController.php`, inside the `checkAndStopIfDurationExceeded` function (or wherever duration gating is evaluated, approximately line 1009), the system compares elapsed hours with `max_duration_hours`.
- If `max_duration_hours` is configured as `-1` (representing unlimited duration), any active livestream is immediately and erroneously terminated.
- You must update this logic to skip the duration check if `max_duration_hours` equals `-1`.
- Review the code, verify the exact location, apply the fix, and ensure that if there are any other places doing a duration check, they are also safe.
- Add or update a test in the test suite to verify that a livestream session with `max_duration_hours = -1` does not auto-terminate.
- Run `php artisan test` and `npm run build` to verify all tests pass and assets compile.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Report back with the changes made and verification results using send_message.
