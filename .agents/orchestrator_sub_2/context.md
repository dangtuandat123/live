# Context - Subscription & Gating System

This file indexes all relevant components, paths, and relationships.

## Models & Migrations
- `SubscriptionPackage` (`backend/app/Models/SubscriptionPackage.php`)
- `UserSubscription` (`backend/app/Models/UserSubscription.php`)
- `Transaction` (`backend/app/Models/Transaction.php`)
- `PaymentConfig` (`backend/app/Models/PaymentConfig.php`)
- Migrations in `backend/database/migrations`

## Controllers & Routes
- `SubscriptionController` (`backend/app/Http/Controllers/SubscriptionController.php`)
- `PaymentCallbackController` (`backend/app/Http/Controllers/PaymentCallbackController.php`)
- `LiveSessionController` (`backend/app/Http/Controllers/LiveSessionController.php`)
- `routes/api.php`, `routes/web.php`

## Middleware
- `HandleInertiaRequests` (`backend/app/Http/Middleware/HandleInertiaRequests.php`)

## Jobs
- `AnalyzeCommentsJob` (`backend/app/Jobs/AnalyzeCommentsJob.php`)

## Frontend Pages (React + Inertia)
- `Subscription/Index.tsx` (`frontend/src/Pages/Subscription/Index.tsx`)
- `Admin/Packages/Index.tsx` (`frontend/src/Pages/Admin/Packages/Index.tsx`)
- `Lives/Show.tsx` (`frontend/src/Pages/Lives/Show.tsx` - for lead export gating)

## Test Suites
- `tests/Feature/SubscriptionPaymentChallengerTest.php`
- `tests/Feature/SubscriptionPaymentTest.php`
