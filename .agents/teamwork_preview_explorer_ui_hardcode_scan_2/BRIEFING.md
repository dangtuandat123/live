# BRIEFING — 2026-05-22T13:52:00Z

## Mission
Scan React files in the Pages directory for hardcoded pricing, features, bank details, transaction history, and admin revenues, mapping them to DB sources and proposing dynamic Laravel APIs.

## 🔒 My Identity
- Archetype: Teamwork explorer
- Roles: Read-only investigator, analyzer, reporter
- Working directory: d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_hardcode_scan_2
- Original parent: dc3d3191-596d-4364-ab79-83c5438a4dd9
- Milestone: UI Dynamic Sync Analysis

## 🔒 Key Constraints
- Read-only investigation — do NOT implement
- CODE_ONLY network mode: no external web access

## Current Parent
- Conversation ID: dc3d3191-596d-4364-ab79-83c5438a4dd9
- Updated: not yet

## Investigation State
- **Explored paths**:
  - `backend/resources/js/Pages/Subscription/Index.tsx`
  - `backend/resources/js/Pages/Admin/Packages/Index.tsx`
  - `backend/resources/js/Pages/Admin/Payments/Index.tsx`
  - `backend/resources/js/Pages/Admin/Dashboard.tsx`
  - `backend/app/Http/Controllers/SubscriptionController.php`
  - `backend/app/Models/SubscriptionPackage.php`
  - `backend/app/Models/PaymentConfig.php`
  - `backend/routes/web.php`
  - `backend/routes/api.php`
  - `backend/database/migrations/*`
  - `backend/database/seeders/*`
- **Key findings**:
  - Located hardcoded beneficiary bank fallbacks (`MB Bank`, `11183041`, `DANG TUAN DAT`) in frontend `Subscription/Index.tsx` and backend `SubscriptionController.php`.
  - Found hardcoded packages feature lists in `Subscription/Index.tsx` comparing specific keys instead of supporting generic or schema-described attributes.
  - Found hardcoded admin dashboard stats metrics (revenue growth percent `+15% so với tháng trước` and user active change detail) in backend `routes/web.php` instead of computing them dynamically.
  - Mapped target areas to DB schemas (`payment_configs`, `subscription_packages`, `transactions`, `users`, `user_subscriptions`).
- **Unexplored areas**: None.

## Key Decisions Made
- Carry out detailed scan of backend/resources/js/Pages/ and analyze all occurrences of hardcoded values, DB schema, and dynamic Laravel APIs.
- Formulate complete design recommendations on moving these to fully dynamic structures.

## Artifact Index
- d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_hardcode_scan_2\handoff.md — Final investigation report
