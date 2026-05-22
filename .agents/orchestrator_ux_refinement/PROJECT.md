# Project: Livestream SaaS Subscription & Payment System - UX Refinement

## Architecture
- **Backend**: Laravel 11.x MVC structure.
  - Models: `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`.
  - Features validation update to support `-1` (infinity).
  - Outbound APIs & Admin configurations dashboard.
- **Frontend**: React 18 + Inertia.js (React routes, Tailwind, and shadcn/ui components).
  - User Menu: dynamic labels from `auth.subscription`.
  - Checkout Modal: dynamic beneficiary details, optimized spacing and responsive sizing.
  - Landing Page: updated layout for CTA buttons.
  - Livestream Show & Index Page: premium badge style, localStorage preservation, spinners on long-running actions.

## Code Layout
- **Models**:
  - `backend/app/Models/SubscriptionPackage.php`
  - `backend/app/Models/UserSubscription.php`
  - `backend/app/Models/PaymentConfig.php`
  - `backend/app/Models/Transaction.php`
- **Controllers & APIs**:
  - `backend/app/Http/Controllers/SubscriptionController.php`
  - `backend/app/Http/Controllers/CheckoutController.php`
  - `backend/app/Http/Controllers/PaymentCallbackController.php`
  - `backend/app/Http/Controllers/Admin/AdminPackageController.php`
  - `backend/app/Http/Controllers/Admin/AdminPaymentConfigController.php`
- **Inertia Pages & Components**:
  - `backend/resources/js/Pages/Subscription/Index.tsx`
  - `backend/resources/js/Pages/Lives/Index.tsx`
  - `backend/resources/js/Pages/Lives/Show.tsx`
  - `backend/resources/js/Pages/Lives/Setup.tsx`
  - `backend/resources/js/Components/nav-user.tsx`
  - `backend/resources/js/Pages/Admin/Payments/Index.tsx`
  - `backend/resources/views/landing.blade.php`

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Codebase Exploration | Audit and identify files/lines to modify | none | PLANNED |
| 2 | Backend Implementation | Dynamic details checkouts, Admin Dashboard Revenue dynamic calculation, Backend packages features validation | M1 | PLANNED |
| 3 | Frontend Implementation | Dynamic user menu, optimized spacing & modal, landing page buttons, livestream badges, localStorage & spinners, client gating | M1 | PLANNED |
| 4 | Testing & Verification | Run php artisan test & npm run build, review changes | M2, M3 | PLANNED |
| 5 | Forensic Audit | Run integrity audit checks | M4 | PLANNED |

## Interface Contracts
- **POST `/api/subscription/checkout`** response needs to contain `beneficiary_bank`, `beneficiary_account`, `beneficiary_name` from active `PaymentConfig`.
- **GET `/admin/dashboard/revenue`** (or computed directly in Inertia share/props) needs to sum successful transaction amounts.
