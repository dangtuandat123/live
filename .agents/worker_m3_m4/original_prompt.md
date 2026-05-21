## 2026-05-21T16:26:57Z

You are the Worker. Your working directory is d:\Workspace\livestream\.agents\worker_m3_m4.
You must implement the frontend changes for Milestones 3 & 4 and update the seeder.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Please perform the following file modifications:

1. `backend/database/seeders/SubscriptionPackageSeeder.php`:
   Update seeded packages' features to include the missing keys:
   - 'Free':
     'limit_streams' => 1,
     'max_duration_hours' => 1,
     'ai_credits' => 1000,
     'audio_analysis' => false,
     'export_leads' => false,
   - 'Pro':
     'limit_streams' => 5,
     'max_duration_hours' => 4,
     'ai_credits' => 50000,
     'audio_analysis' => true,
     'export_leads' => true,
   - 'Enterprise':
     'limit_streams' => -1,
     'max_duration_hours' => 24,
     'ai_credits' => 500000,
     'audio_analysis' => true,
     'export_leads' => true,

2. `backend/resources/js/Pages/Subscription/Index.tsx`:
   - Update Props, Transaction, and ActiveSubscription interfaces to support `transactions`, `used_ai_credits`, and `features`.
   - In the current subscription status Card: Add used AI credits vs limit balance displaying progress bar using the `Progress` component or a custom Tailwind styled progress bar. If limit is `-1`, show 'Vô hạn' and skip or fill the progress bar cleanly.
   - Add a Feature Comparison table showing all differences between packages (limit_streams, max_duration_hours, ai_credits, audio_analysis, export_leads) for all packages in `packages` prop.
   - Add a Transaction History table mapping over the `transactions` prop. Display transaction ID, package name, formatted amount (VND), status with badges, and date.
   - In the VietQR checkout Dialog:
     - Implement a 10-minute countdown timer (600 seconds) in React. If the timer reaches 0, stop status polling, show an expiration message, and disable the confirmation button.
     - Show a micro-animation or spinning loader indicating 'Đang chờ thanh toán...' or 'Đang chờ chuyển khoản...'.

3. `backend/resources/js/Pages/Lives/Show.tsx`:
   - Retrieve `auth` from `usePage().props`.
   - Add a state `showUpgradeDialog` (boolean).
   - Gate the 'Xuất CSV' and 'Copy tất cả' buttons: if `features.export_leads` is false, intercept click to set `showUpgradeDialog(true)`.
   - Render a Dialog component at the bottom of the page requesting the user to upgrade to Pro/Enterprise to export leads, with a button navigating to `/subscription`.

4. `backend/resources/js/Pages/Admin/Packages/Index.tsx`:
   - Update types `SubscriptionPackage` and `PackageFeatures`.
   - In Create and Edit modals, replace the dynamic string list `featuresList` input with structured limit configurations (number inputs for `limit_streams`, `max_duration_hours`, `ai_credits`, and checkboxes for `audio_analysis`, `export_leads`).
   - On submission, pass these nested under the single `features` JSON object.
   - In the package list table, replace the array `.map()` features rendering with structured badges display of limits to prevent rendering crashes.

5. Run verification:
   - Run `php artisan db:seed --class=SubscriptionPackageSeeder` to refresh packages features (or migrate fresh).
   - Run `php artisan test` in `backend` folder to ensure all tests pass successfully.
   - Run `npm run build` in `backend` folder to compile assets.

Create a handoff report in `d:\Workspace\livestream\.agents\worker_m3_m4\handoff.md` summarizing the changes, verification results, and any warnings.
Report completion back by sending a message to me (conversation ID: c2f4d0ab-8b04-4d53-9af4-38b0cbe15af3).
