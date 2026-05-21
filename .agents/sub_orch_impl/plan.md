# Plan - Subscription & Payment System Implementation

This plan outlines the steps for implementing the subscription and payment system.

## Milestone 1: DB Schema & Models
- Implement `SubscriptionPackage` model and migration.
- Implement `UserSubscription` model and migration.
- Implement `PaymentConfig` model and migration.
- Implement `Transaction` model and migration.
- Add Eloquent relations to `User` model.
- Add basic seeds for packages and payment config.

## Milestone 2: Backend APIs & Callback
- Define routes in `api.php`.
- Implement `SubscriptionController` (`packages`, `status`).
- Implement `CheckoutController` (`checkout` -> generates transaction, retrieves payment config, builds VietQR URL).
- Implement `PaymentCallbackController` (`callback` -> validates amount/user, updates/renews subscription, triggers outbound HTTP webhook with parsed placeholders).

## Milestone 3: User Frontend UI
- Build Packages Pricing Page (`Subscription/Index.tsx`).
- Build Checkout Modal/View (`Subscription/Checkout.tsx` or `CheckoutModal.tsx`) with dynamic VietQR rendering and payment instructions.

## Milestone 4: Admin Dashboard UI
- Build Packages CRUD (`Admin/Packages/Index.tsx`).
- Build Payment Configs CRUD (`Admin/Payments/Index.tsx`).
- Secure admin routes with auth middleware & role checks.

## Milestone 5: E2E Testing & Final Pass
- Poll/wait for `TEST_READY.md`.
- Implement Feature test case `tests/Feature/SubscriptionPaymentTest.php`.
- Run E2E test suite and fix issues until 100% pass.

## Milestone 6: Hardening
- Run Challenger to find code coverage/robustness gaps.
- Improve code quality and fix gaps.
