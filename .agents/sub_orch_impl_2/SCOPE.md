# Scope: Subscription and Payment System Implementation

## Architecture
- **Backend**: Laravel 11.x MVC
  - Models: `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`
  - Controllers:
    - `SubscriptionController` (Public APIs)
    - `CheckoutController` (Public Checkout APIs)
    - `PaymentCallbackController` (Payment gateway callback webhook)
    - `Admin\AdminPackageController` (Admin subscription packages CRUD)
    - `Admin\AdminPaymentConfigController` (Admin payment config CRUD)
  - Relations: `User` has many subscriptions and transactions.
  - Outbound Webhooks: Triggered upon successful payment callback to `webhook_url` configured in `payment_configs`.
- **Frontend**: React 18 + Inertia.js (React routes, Tailwind, shadcn/ui)
  - User Packages Listing page: `backend/resources/js/Pages/Subscription/Index.tsx`
  - User Checkout modal/view: `backend/resources/js/Components/CheckoutModal.tsx` or similar
  - Admin Packages CRUD dashboard: `backend/resources/js/Pages/Admin/Packages/Index.tsx`
  - Admin Payment Config Settings dashboard: `backend/resources/js/Pages/Admin/Payments/Index.tsx`

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | DB Schema & Models | Create migrations, models, relations, seeders for subscription/payment | none | PLANNED |
| 2 | Backend APIs & Callback | API routes, Controllers, Webhook triggering, placeholder parsing | M1 | PLANNED |
| 3 | User Frontend UI | Pricing page, Checkout modal, dynamic VietQR rendering | M2 | PLANNED |
| 4 | Admin Dashboard UI | Packages CRUD, Payment configs CRUD, Secure routing | M2 | PLANNED |
| 5 | E2E Testing & Final Pass | Verify 100% test pass on tests/Feature/SubscriptionPaymentTest.php | M3, M4 | PLANNED |
| 6 | Hardening | White-box coverage hardening (Tier 5: Challenger + Worker) | M5 | PLANNED |

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
