## 2026-05-22T03:43:08Z
You are the Worker. Your working directory is d:\Workspace\livestream\.agents\worker_ui_sync_2.
Your task is to implement the Phase 2 UI/UX sync and refinements according to the findings and detailed plans compiled by Explorer 4 at d:\Workspace\livestream\.agents\explorer_ui_sync_4\handoff.md.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Here is the exact task breakdown:

1. R1: User Menu Dynamic Labels & Types
- Update `backend/resources/js/types/index.d.ts` to declare `UserSubscription` and `UserSubscriptionFeatures` interfaces, and update `PageProps` with `auth: { user: User; subscription: UserSubscription | null; }`.
- Update `backend/resources/js/Components/nav-user.tsx` to read `auth` from Inertia page props (`usePage().props`). If the subscription is Pro or Enterprise and is active, replace the label "Nâng cấp Pro" with "Quản lý gói".

2. R2: Spacing & Layout Padding Update
- In each of the following 10 page files, replace the main content container's padding from `p-4 pt-4` or `p-4` to `p-6` or `p-6 pt-6`:
  1. `backend/resources/js/Pages/Dashboard.tsx`
  2. `backend/resources/js/Pages/Lives/Index.tsx`
  3. `backend/resources/js/Pages/Reports/Index.tsx`
  4. `backend/resources/js/Pages/Products/Index.tsx`
  5. `backend/resources/js/Pages/Settings/Index.tsx`
  6. `backend/resources/js/Pages/Admin/Dashboard.tsx`
  7. `backend/resources/js/Pages/Admin/Users/Index.tsx`
  8. `backend/resources/js/Pages/Admin/Packages/Index.tsx`
  9. `backend/resources/js/Pages/Admin/Payments/Index.tsx`
  10. `backend/resources/js/Pages/Admin/Settings/Index.tsx`

3. R2: Checkout Modal Sizing
- In `backend/resources/js/Pages/Subscription/Index.tsx`, update Checkout Modal styling: reduce padding of DialogContent and internal container from `p-5` to `p-4` (or `px-5 py-4`).
- Reduce spacing/gaps in the modal from `space-y-4` to `space-y-3` and `gap-6` to `gap-4`.
- Constrain the QR image element to `max-w-[155px]` (or `max-w-[155px] max-h-[155px] h-auto border rounded`).

4. R3: Landing Page Buttons
- In `backend/resources/views/landing.blade.php`, add `w-full` class to both the "Bắt đầu ngay" anchor tag (around line 770) and the "Đăng ký ngay" anchor tag (around line 814) so they occupy full width in their containers.

5. R4: Livestream Status Badges Redesign
- Redesign the livestream status badges in both `backend/resources/js/Pages/Lives/Index.tsx` and `backend/resources/js/Pages/Lives/Show.tsx`.
- Use modern semi-transparent styling matching primary oklch colors:
  - connecting: `bg-blue-500/10 text-blue-400 border border-blue-500/20 backdrop-blur-md shadow-xs animate-pulse` with ping animation.
  - disconnected: `bg-amber-500/10 text-amber-500 border border-amber-500/20 backdrop-blur-md shadow-xs animate-pulse` with ping animation.
  - ended (or default): `bg-black/40 text-white/90 border border-white/10 backdrop-blur-md`
  - live: `bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md shadow-xs` with ping animation.
  - error: `bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md`

Write your changes summary and verify them by running:
- php artisan test
- npm run build

Report back with the files modified, commands executed, and verification outputs. Use send_message to notify the orchestrator (conversation ID: ddd017b4-48bd-46a1-a53c-05a9021ed31f).
