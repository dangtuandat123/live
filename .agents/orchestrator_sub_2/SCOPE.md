# Scope: Subscription and Payment System

## Architecture
This sub-project implements the subscription and billing limits features for the livestream analysis SaaS web application. It integrates Laravel on the backend (using Eloquent models, controllers, and jobs) with React on the frontend (using Inertia.js).

Components:
- **Models**:
  - `SubscriptionPackage`: Package config with price, duration, and `features` JSON (`limit_streams`, `max_duration_hours`, `ai_credits`, `audio_analysis`, `export_leads`).
  - `UserSubscription`: Active/inactive subscription records with expiration logic and `used_ai_credits` tracking.
  - `PaymentConfig`: Holds name, prefix, suffix, webhook_url, method, params_template, headers_template.
  - `Transaction`: Records user payments, amount, and status.
- **Controllers & Middleware**:
  - `SubscriptionController`: Lists packages, processes checkout, checks active subscription.
  - `PaymentCallbackController`: Receives bank callback, updates subscription, records success transaction, dispatches outbound webhook job.
  - `LiveSessionController`: Checks `limit_streams` on creation, checks `max_duration_hours` on event fetching.
  - `HandleInertiaRequests` Middleware: Shares user's active subscription limits and usage metrics with frontend React.
- **Queue Jobs**:
  - `AnalyzeCommentsJob`: Enforces `ai_credits` limit and incrementing usage. Bypasses audio extraction if `audio_analysis` limit is active.
  - `SendOutboundPaymentWebhookJob`: Sends POST/GET/PUT to custom webhook with header/params substitutions.
- **Frontend Pages**:
  - `Subscription/Index.tsx`: User pricing table, feature comparison, AI credits balance, transaction history, checkout modal with MB Bank VietQR and 10min timer.
  - `Admin/Packages/Index.tsx`: CRUD interface for admin, with JSON/structured limit fields.
  - `Admin/Payments/Index.tsx`: Admin panel for configuring payment prefix, suffix, and webhooks.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| M1 | Database & Models | Run/adjust migrations, configure model relations and JSON casts. | none | DONE |
| M2 | Backend API & Gates | Implementation of limits checks, checkout endpoint, callback logic, and outbound webhook dispatch. | M1 | DONE |
| M3 | User Pricing & Checkout UI | Premium UI in Subscription/Index.tsx, VietQR modal with live check pooling, Inertia sharing. | M2 | DONE |
| M4 | Admin Package Upgrade | CRUD UI with input fields for limit configuration and JSON mapping to model features. | M1, M2 | DONE |
| M5 | Automated Tests & Verification | Verify existing tests, write new SubscriptionPaymentTest scenarios, run npm build. | M1-M4 | DONE |

## Interface Contracts
### Client ↔ Server Webhook Callback
- Payload: `{"id_user": "{user_id}", "sotien": {amount}}`
- Return: `{"status": "success", "message": "..."}` or HTTP errors on failures/validation errors.

### Outbound Webhook Integration
- Outbound Method: Configurable POST/GET/PUT.
- Params/Headers Template: JSON format containing placeholders `{user_id}`, `{amount}`, and `{transaction_id}`.
