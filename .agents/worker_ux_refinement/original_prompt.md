## 2026-05-22T21:36:16Z
Objective: Implement the UX/UI Refinement of Subscription Limits.
Specifically, perform the following tasks:

1. R1: Low Time Warning & Badge:
   - Modify Show.tsx: Add an amber warning banner at the top of the page when a live session is active/connecting and has consumed >=85% of its max duration or has <10 minutes left.
   - Update UpgradeDurationDialog to clarify that all comment history, metrics, and prospective customer lists are safely retained in the database for access or export.
   - Update Lives/Index.tsx and Dashboard.tsx: Display status badges "Bị ngắt (Hết giờ)" or "Đạt giới hạn" using the error_message.
   - Update backend controllers (LiveSessionController.php, DashboardController.php) to return the error_message field in the session listing data.

2. R2: Low Credits Alert & Sidebar:
   - Modify Show.tsx: Add an amber warning banner at the top when used credits >= 90% of the limit (and limit != -1).
   - Modify app-sidebar.tsx and progress.tsx: Support custom indicatorClassName and color the progress bar red (used >= 90%) or amber (used >= 80% and < 90%) when credits are low.

3. R3: Setup Limits Card & Gating:
   - Modify Lives/Setup.tsx: Display a card detailing the subscription package limits (Streams, Duration, AI credits, Premium features: export CSV, audio analysis).
   - Lock/disable the submit button and show a warning banner with a CTA "Nâng cấp gói ngay" redirecting to route('subscription.index') and a link back to the list of live sessions if active streams reach the limit.

4. R4: Audio Analysis Gating UI:
   - Modify Show.tsx: Display a visual indicator card for "Phân tích âm thanh AI" in the left column. If the package supports it (audio_analysis = true), show a green active state with pulse animation. If not, show a lock icon and "Bản nâng cấp" badge. Clicking the locked card triggers the upgrade dialog.
   - Unify and hoist the upgrade dialog in LivesShow to be triggered via LiveContext by subcomponents.

5. Verification:
   - Run tests: php artisan test
   - Build frontend: npm run build

Refer to the exploration handoff reports for details and code patches:
- Show & Dialogs: d:\Workspace\livestream\.agents\explorer_show_dialogs\handoff.md and proposed_changes.patch
- Setup & Sidebar: d:\Workspace\livestream\.agents\explorer_setup_sidebar\handoff.md
- Index & Backend: d:\Workspace\livestream\.agents\explorer_index_backend\handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Write your final report to d:\Workspace\livestream\.agents\worker_ux_refinement\handoff.md.
