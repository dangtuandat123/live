# Project: Livestream SaaS Subscription & Payment System

## Architecture
- **Backend**: Laravel 11.x MVC structure.
  - Models: `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`.
  - Relations: `User` has many subscriptions and transactions.
  - APIs: `SubscriptionController` for user packages list and current status; `CheckoutController` for initiating checkout and VietQR URL generation; `PaymentCallbackController` for handling the public webhook, upgrading subscriptions, and triggering outbound HTTP webhooks.
- **Frontend**: React 18 + Inertia.js (React routes, Tailwind, and shadcn/ui components).
  - Packages pricing page.
  - Checkout page/modal.
  - Admin configurations for payment methods.
  - Admin Packages CRUD.

## Code Layout
- **Models**:
  - `backend/app/Models/SubscriptionPackage.php`
  - `backend/app/Models/UserSubscription.php`
  - `backend/app/Models/PaymentConfig.php`
  - `backend/app/Models/Transaction.php`
- **Migrations**:
  - `backend/database/migrations/create_subscription_packages_table.php`
  - `backend/database/migrations/create_user_subscriptions_table.php`
  - `backend/database/migrations/create_payment_configs_table.php`
  - `backend/database/migrations/create_transactions_table.php`
- **Controllers & APIs**:
  - `backend/app/Http/Controllers/SubscriptionController.php`
  - `backend/app/Http/Controllers/CheckoutController.php`
  - `backend/app/Http/Controllers/PaymentCallbackController.php`
  - `backend/app/Http/Controllers/Admin/AdminPackageController.php`
  - `backend/app/Http/Controllers/Admin/AdminPaymentConfigController.php`
- **Inertia Pages & Components**:
  - `backend/resources/js/Pages/Subscription/Index.tsx` (Packages Listing)
  - `backend/resources/js/Pages/Subscription/Checkout.tsx` or `backend/resources/js/Components/CheckoutModal.tsx` (Checkout Modal)
  - `backend/resources/js/Pages/Admin/Packages/Index.tsx` (Admin Packages CRUD)
  - `backend/resources/js/Pages/Admin/Payments/Index.tsx` (Admin Payment Configs Settings)

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | DB Schema & Models | Create migrations, models, relations, seeders | none | PLANNED |
| 2 | Backend APIs & Callback | API routes, Controllers, Webhook triggering, placeholder parsing | M1 | PLANNED |
| 3 | User Frontend UI | Pricing page, Checkout modal, dynamic VietQR rendering | M2 | PLANNED |
| 4 | Admin Dashboard UI | Packages CRUD, Payment configs CRUD, Secure routing | M2 | PLANNED |
| 5 | E2E Testing & Final Pass | Verify 100% test pass on tests/Feature/SubscriptionPaymentTest.php | M3, M4 | PLANNED |

## Interface Contracts
### Public API Contracts
- **GET `/api/subscription/packages`**:
  - Returns JSON array of active packages: `[{"id": 1, "name": "Pro", "price": 299000, "duration_days": 30, "features": ["audio_analysis"]}]`.
- **GET `/api/subscription/status`**:
  - Returns JSON of the user's active subscription: `{"active": true, "package_id": 1, "expires_at": "2026-06-20T14:43:00Z"}`.
- **POST `/api/subscription/checkout`**:
  - Request: `{"package_id": 1}`.
  - Response: `{"transaction_id": "TX12345", "vietqr_url": "https://api.vietqr.io/..."}`.
- **POST `/api/payments/callback`**:
  - Request: `{"id_user": "{user_id}", "sotien": 299000}`.
  - Response: `{"success": true, "message": "Subscription upgraded successfully"}`.

### Outbound Webhook Contract
- Method: GET/POST/PUT (configured in PaymentConfig).
- Headers: Key-Value pairs resolved from `headers_template`.
- Payload/Params: Key-Value pairs resolved from `params_template` replacing `{user_id}`, `{amount}`, `{transaction_id}`.
