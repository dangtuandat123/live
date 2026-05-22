## 2026-05-22T14:11:26Z

You are the Subscription UX Worker. Your working directory is d:\Workspace\livestream\.agents\teamwork_preview_worker_ux_refinement.

Please read the Explorer's findings at d:\Workspace\livestream\.agents\teamwork_preview_explorer_ux_refinement\handoff.md to understand the codebase and current gating mechanism.

Implement the subscription limits UX/UI refinements inside backend/resources/js/Pages/Lives/Show.tsx.

Requirements:
1. Polling Error Message Sync:
   - Ensure the polling logic preserves and updates `error_message` from the polling response when status is 'ended' or 'error' (see Explorer's section 5.A).
2. Upgrade Duration Dialog:
   - Pop up a modern, styled upgrade dialog (using @/components/ui/dialog) when the session status is 'ended' and `error_message` contains duration limits (e.g. 'thời lượng tối đa' or 'max duration').
   - Detail the limits of the current active plan and provide an upgrade button redirecting to /subscription.
   - Prevent this dialog from showing repeatedly on page refresh by storing a dismissal flag in sessionStorage for the specific session ID.
3. Upgrade Credits Dialog:
   - Pop up a modern, styled upgrade dialog when the session status is 'error' and `error_message` contains credit limit messages (e.g. 'tín dụng AI' or 'ai credits').
   - Explain clearly that the AI analysis is paused due to credits limit, and provide options to buy more or upgrade.
   - Also use sessionStorage to prevent showing repeatedly on page refresh.
4. Gated Features UI & Trigger:
   - Display a lock icon (e.g., using Lock icon from lucide-react) next to the "Xuất CSV" (Export CSV) and "Copy tất cả" (Copy all) buttons in the potential customers list (inside CustomersPanel component of Show.tsx) if user's subscription does not allow it (canExportLeads is false).
   - When a user clicks these locked features, instead of silent failure or raw error, trigger the upgrade dialog.
5. Subscription Status Banner:
   - Display a compact, elegant Subscription Status Banner/Progress Bar in the dashboard layout (e.g., in the header or near the session stats).
   - Display: Active plan name, max duration, used vs limit AI credits with a progress bar. Show 'Vô hạn' (or 'Unlimited') for any fields that are unlimited (-1).
   - Retrieve plan data from Inertia page props `auth.subscription` (contains package_name, used_ai_credits, features: { max_duration_hours, ai_credits, export_leads, audio_analysis }).
6. Verification & Compilation:
   - Compile frontend assets using `npm run build` in the backend directory.
   - Ensure the build finishes with no type errors or bundling issues.
   - Run backend tests using `php artisan test` (including tests under Feature/SubscriptionGatingTest.php) to ensure no regressions.

DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Write a detailed handoff report in d:\Workspace\livestream\.agents\teamwork_preview_worker_ux_refinement\handoff.md detailing the exact changes made, files modified, build output, and test output. Initialize your BRIEFING.md and progress.md in your directory first.
