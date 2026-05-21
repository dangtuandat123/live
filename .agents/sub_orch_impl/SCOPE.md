# Scope: Livestream SaaS Subscription & Payment System Implementation

## Architecture
- **Backend**: Laravel 11.x
  - Models & Migrations: `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, `Transaction`.
  - Relations: `User` has many subscriptions (`UserSubscription`) and transactions (`Transaction`).
  - APIs: `SubscriptionController` for package listing and current user status; `CheckoutController` for creating checkout transaction and returning VietQR URL; `PaymentCallbackController` for public webhook handling, upgrading user subscription, and firing outbound webhooks.
  - Admin Controllers: `AdminPackageController` and `AdminPaymentConfigController`.
- **Frontend**: React 18 + Inertia.js (React routes, Tailwind, and shadcn/ui components).
  - Subscription packages listing/pricing page (`Subscription/Index.tsx`).
  - Checkout component/modal (`Subscription/Checkout.tsx` or `CheckoutModal.tsx`).
  - Admin configuration CRUD for packages (`Admin/Packages/Index.tsx`) and payment methods (`Admin/Payments/Index.tsx`).

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | DB Schema & Models | Create migrations, models, relations, seeders | none | PLANNED |
| 2 | Backend APIs & Callback | API routes, Controllers, Webhook triggering, placeholder parsing | M1 | PLANNED |
| 3 | User Frontend UI | Pricing page, Checkout modal, dynamic VietQR rendering | M2 | PLANNED |
| 4 | Admin Dashboard UI | Packages CRUD, Payment configs CRUD, Secure routing | M2 | PLANNED |
| 5 | E2E Testing & Final Pass | Verify 100% test pass on tests/Feature/SubscriptionPaymentTest.php | M3, M4 | PLANNED |
| 6 | Hardening | White-box coverage hardening (Tier 5) | M5 | PLANNED |

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
