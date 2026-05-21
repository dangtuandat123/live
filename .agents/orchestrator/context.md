# Project Context

## Target Scope
The scope covers:
- Subscription Packages and User Subscriptions models and migrations.
- Payment Configurations and Transactions models and migrations.
- Active Packages listing, checkout, and webhook callback APIs.
- Outbound Webhook forwarding for payment configs.
- Admin management CRUD panels for packages and payment configs.
- User packages listing & checkout UI (VietQR dynamic rendering).
- Feature test cases verifying checkout/callback/webhook forward/CRUD configurations.

## Target Directories
- `backend/app/Models/`
- `backend/database/migrations/`
- `backend/app/Http/Controllers/`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/resources/js/` (Inertia React pages/components)
- `backend/tests/Feature/SubscriptionPaymentTest.php`

## Verification Command
- Automated test command: `php artisan test` in `backend` directory.

## Constraints
- Never write, modify, or create source code files directly.
- All testing and file generation must be performed by subagents.
