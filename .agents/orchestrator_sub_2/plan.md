# Plan - Subscription, Payment & Gating System Implementation

This plan details the implementation strategy for the subscription, payment (VietQR), and limit gating system.

## Milestones

### Milestone 1: Database & Models Setup (M1)
- **Objective**: Create database migrations and update model configurations for limits and credit tracking.
- **Tasks**:
  - Add migration to add `used_ai_credits` to `user_subscriptions` table (default 0).
  - Configure `UserSubscription` model casts and `$fillable`.
  - Verify database schema and models are ready.
- **Worker/Explorer Role**: `teamwork_preview_worker` to create and run migrations.
- **Verification**: Run `php artisan migrate` and verify `user_subscriptions` schema.

### Milestone 2: Backend API & Gating Logic (M2)
- **Objective**: Implement secure checkout/callback logic and backend gating middleware/logic.
- **Tasks**:
  - Fix vulnerabilities:
    - **Callback Security**: Require signature/token check for callback webhook or secure route structure.
    - **Idempotency**: Track bank references or query unique pending/completed transactions securely inside a transaction block with lockForUpdate.
    - **Price Matching**: Resolve callback package mapping using specific transaction reference rather than only matching price.
    - **Free Package Checkout Abuse**: Enforce database-locked unique active subscription check during checkout to avoid concurrent race conditions.
  - Implement limits checks:
    - `limit_streams`: Limit sessions connecting/live inside `LiveSessionController@store`.
    - `max_duration_hours`: Filter/check session event duration limits.
    - `ai_credits`: Track credit consumption in `AnalyzeCommentsJob` and block when exceeded.
    - `audio_analysis`: Bypass audio extraction if feature disabled.
  - Share active subscription features via `HandleInertiaRequests` middleware.
- **Worker/Explorer Role**: `teamwork_preview_worker` to implement PHP controller, model, and middleware changes.
- **Verification**: Run feature tests for subscription payments.

### Milestone 3: User Pricing & Checkout UI (M3)
- **Objective**: Implement pricing, checkout flow, VietQR payment modal, and history list.
- **Tasks**:
  - Update `Subscription/Index.tsx` to display package cards, features comparison list, user's current subscription, credit balance, and transaction history.
  - Implement VietQR checkout modal:
    - Render QR code containing amount and message.
    - Show 10-minute countdown timer.
    - Status polling endpoint check every 5 seconds.
- **Worker/Explorer Role**: `teamwork_preview_worker` to edit React frontend files.
- **Verification**: Verify frontend compilation (`npm run build`).

### Milestone 4: Admin Package Upgrade (M4)
- **Objective**: CRUD interface to edit subscription limits JSON.
- **Tasks**:
  - Update `Admin/Packages/Index.tsx` with standard input fields for limits (`limit_streams`, `max_duration_hours`, `ai_credits`, `audio_analysis`, `export_leads`).
  - Map input values to the JSON `features` column structure on form submit.
- **Worker/Explorer Role**: `teamwork_preview_worker` to update package configuration UI.
- **Verification**: Verify UI components render properly.

### Milestone 5: Verification & Hardening (M5)
- **Objective**: Ensure all tests and builds pass.
- **Tasks**:
  - Run all automated PHP feature tests (`php artisan test`).
  - Run asset builder (`npm run build`).
  - Fix any linting/compilation issues.
- **Worker/Explorer Role**: `teamwork_preview_worker` and `teamwork_preview_reviewer` / `teamwork_preview_auditor`.
