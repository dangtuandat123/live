# Project: UI Sync, Dynamic Configurations, and UI/UX Polishing (orchestrator_ui_sync_2)

## Architecture
- **Frontend**: React (Inertia.js), TypeScript, TailwindCSS.
- **Backend**: Laravel PHP, Eloquent, database migrations, controllers, form requests, validation.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| M1 | Dynamic Bank Info & Admin Dashboard Revenue | - Update `Subscription/Index.tsx` & `SubscriptionController.php` to fetch bank info dynamically and remove fallback strings.<br>- Update `Admin/Payments/Index.tsx` to display true total revenue sum. | None | DONE |
| M2 | State Persistence via localStorage | - Integrate `localStorage` with `session.id` suffix for orders, pinned comments, and marked orders in `Lives/Show.tsx`. | None | DONE |
| M3 | Loading Spinners & Toast Notifications | - Add loading spinners on End Analysis (`Lives/Show.tsx`) and Delete Live (`Lives/Index.tsx`).<br>- Add toast notifications for copies, order saving, and session end. | None | DONE |
| M4 | Client-side Gating for Stream Limit | - Integrate stream limit check in `Setup.tsx` using `auth.subscription.features.limit_streams` (-1 as unlimited). | None | DONE |
| M5 | Backend Validation & Duration Fix | - Update validation rules in `SubscriptionController.php` to allow `-1` for package limits.<br>- Fix duration gating bug in `LiveSessionController.php` for unlimited package streams. | None | DONE |
| M6 | User Menu Dynamic Labels & Types | - In `nav-user.tsx` dynamically replace "Nâng cấp Pro" with "Quản lý gói" if active subscription is Pro or Enterprise.<br>- Update TypeScript definitions for subscription in `index.d.ts`. | None | DONE |
| M7 | Spacing, Layout Heights & Checkout Modal Sizing | - Update 10 main pages to use `p-6` (or `p-6 pt-6`) main padding.<br>- Shrink checkout modal padding/height, set QR code max-width to 155px, reduce gap to prevent overflow. | None | DONE |
| M8 | Landing Page Buttons w-full | - Update "Bắt đầu ngay" and "Đăng ký ngay" buttons in `landing.blade.php` to use `w-full`. | None | DONE |
| M9 | Semi-transparent Livestream Status Badges | - Redesign status badges in `Lives/Index.tsx` and `Lives/Show.tsx` with semi-transparent borders and colors matching system branding. | None | DONE |

## Interface Contracts
### nav-user.tsx Subscription State
- User menu reads `auth.subscription` from page props:
  - `active`: boolean
  - `package_name`: string
  - `expires_at`: string
  - `features`: features JSON object
