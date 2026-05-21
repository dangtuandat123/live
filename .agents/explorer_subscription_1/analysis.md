# LiveStream SaaS Subscription & Payment System Codebase Analysis Report

## Summary
- **Active Mode**: Core Mode
- **Scope**: Core Backend structure (Models, Migrations, Routing, Controllers, Middlewares) and Frontend Inertia React Structure (Pages, Layouts, Components, Navigation).
- **Findings**:
  - Codebase is a Laravel 11 + React Inertia structure using Tailwind CSS and shadcn/ui.
  - A mock subscription pricing block currently exists in the user settings page (`backend/resources/js/Pages/Settings/Index.tsx`).
  - No database schema, Eloquent models, controllers, or backend routes currently exist for subscription packages, active user subscriptions, or payment configurations.
  - An Admin dashboard is already fully set up with access control routing, but it currently estimates revenue using hardcoded numbers and mock plans.
- **Baseline Test Status**: 44 Feature & Unit tests running and passing (100% success rate).

---

## 1. Project Stack & Core Framework Profile
- **Backend Framework**: Laravel 11.x
- **Frontend Framework**: React 18.x (with Inertia.js CSR, TypeScript, shadcn/ui components)
- **Database**: SQLite (configured for local development and testing)
- **Security & Auth**: Standard web middleware, custom role-based middleware (`ensure_admin`), and Sanctum for token authentication.
- **AI Service Integration**: Laravel AI SDK integration configured inside `config/ai.php`.

---

## 2. Existing Laravel Structure & Conventions

### 2.1 Laravel Models
Models are located in `backend/app/Models/`. The relationship mapping shows the following structure:
- **`User.php`**: Represents users, holds settings as JSON (`settings` cast to `array`). Includes the `isAdmin()` method checking `role === 'admin'`.
- **`LiveSession.php`**: Represents a livestream session.
  - `user()`: `belongsTo(User::class)`
  - `products()`: `belongsToMany(Product::class, 'live_session_products')`
  - `events()`: `hasMany(LiveEvent::class)`
  - `keywords()`: `hasMany(LiveSessionKeyword::class)`
  - `stats()`: `hasOne(LiveStat::class)`
  - `statsHistory()`: `hasMany(LiveSessionStatsHistory::class)`
  - Scopes: `scopeForUser($query, $userId)`, `scopeLive($query)`
- **`LiveEvent.php`**: Individual events/comments from TikTok livestream streams.
  - `liveSession()`: `belongsTo(LiveSession::class)`
- **`LiveStat.php`**: Session aggregate KPIs (comments, views, sentiment positive/neutral/negative, and leads count).
  - `liveSession()`: `belongsTo(LiveSession::class)`
- **`Product.php`**: Streamer items registered for tracking matching keywords.
  - `user()`: `belongsTo(User::class)`
  - `liveSessions()`: `belongsToMany(LiveSession::class, 'live_session_products')`

### 2.2 Laravel Controllers
Controllers are located in `backend/app/Http/Controllers/`. The key controllers include:
- **`DashboardController.php`**: Collects aggregated statistics, trending chart data, hot keywords, and top products, returning `Inertia::render('Dashboard', [...])`.
- **`LiveSessionController.php`**: Handles creating, viewing, starting, stopping, and deleting livestream comment tracking pipelines.
- **`ProductController.php`**: Implements CRUD for streamer products with search filtering and performance optimized eager loading.
- **`SettingsController.php`**: Updates user details and user-level AI context extraction parameters.

### 2.3 Laravel Migrations
Migrations are found in `backend/database/migrations/`.
- User tables (`create_users_table`, `add_role_to_users_table`, `add_settings_to_users_table`).
- Livestream tables (`create_live_sessions_table`, `create_live_events_table`, `create_live_stats_table`, etc.).
- System settings config caches in SQLite DB or local `system_settings.json` storage.

### 2.4 Routing & Middleware Conventions
Routes are located in `backend/routes/`.
- **`web.php`**: Uses group middleware `auth` and `verified` for authenticated client actions, rendering React Inertia templates.
- **`auth.php`**: Standard authentication endpoints (Login, Register, Password reset).
- **`EnsureUserIsAdmin.php` (`admin` middleware)**: Protects admin endpoints by aborting with 403 status if `!$user->isAdmin()`.
- **Inertia Share Props**: `HandleInertiaRequests.php` automatically injects `auth.user` into all Inertia page components.

---

## 3. Inertia React Frontend Architecture

Frontend files are located under `backend/resources/js/`.

### 3.1 Directories
- `Pages/`: Contains views rendered by Inertia controllers.
  - `Admin/`: Dashboard, settings configuration, and user management.
  - `Settings/`: Account settings and user AI preferences.
  - `Dashboard.tsx`: Main user analytics dashboard.
  - `Lives/`, `Products/`, `Reports/`: Views for managing livestreams, products, and reports.
- `Components/`: Reusable layouts and custom elements.
  - `app-sidebar.tsx`: Collapsible user sidebar containing links to Overview, Lives, Products, Reports, Settings. Renders administrative link if `auth.user.role === 'admin'`.
  - `admin-sidebar.tsx`: Sidebar containing system overview, user control, system settings, and exit buttons.
- `Layouts/`: Layout wrappers.
  - `AuthenticatedLayout.tsx`: Wraps standard pages inside `AppSidebar` navigation.
  - `AdminLayout.tsx`: Wraps admin pages inside `AdminSidebar`.

---

## 4. Subscription & Payment System Implementation Plan

Currently, no subscription backend or data tables exist. In the user settings page, the subscription details are hardcoded mockups. Below is the proposed design and implementation plan.

### 4.1 Proposed Database Schema (Migrations)

#### 1. `subscription_packages` table
This stores information about packages/plans (e.g. Free, Pro, Business).
```php
Schema::create('subscription_packages', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // e.g. "Gói Pro"
    $table->string('slug')->unique(); // e.g. "pro"
    $table->unsignedInteger('price'); // Monthly price in VND (e.g. 299000)
    $table->unsignedInteger('max_sessions'); // Limit of live sessions per month (e.g. 30, or -1 for unlimited)
    $table->json('features')->nullable(); // JSON list of feature flags (e.g. ["audio_analysis", "realtime_alerts"])
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### 2. `subscriptions` table
Tracks active subscriptions for each user.
```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('package_id')->constrained();
    $table->string('status'); // 'active', 'pending', 'expired', 'cancelled'
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->string('payment_gateway')->nullable(); // 'vnpay', 'momo', 'bank_transfer'
    $table->string('transaction_reference')->nullable()->unique(); // Transaction code from gateway
    $table->timestamps();
});
```

#### 3. `payment_configs` table (or system settings storage extension)
Payment gateway settings (such as Momo token, VNPAY hash key, bank details for VietQR) should be configurable by administrators.
- **Option A (Relational)**: Create a `payment_configs` table (`key`, `value`, `group`).
- **Option B (JSON file storage)**: Extend the existing `system_settings.json` schema inside storage to add a `payment_gateways` node. Option B matches the current implementation pattern used by `Admin/Settings` controllers.

### 4.2 Proposed Backend Controllers & Routing

#### 4.2.1 Customer Routes (`routes/web.php`)
```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Subscription index and detail pages
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    
    // Callback pages for digital payment providers
    Route::get('/subscription/callback/{gateway}', [SubscriptionController::class, 'callback'])->name('subscription.callback');
});

// Public webhook route (exclude from CSRF in bootstrap/app.php)
Route::post('/subscription/webhook/{gateway}', [SubscriptionWebhookController::class, 'handle']);
```

#### 4.2.2 Admin Routes (`routes/web.php` inside the admin prefix group)
```php
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    // Packages CRUD
    Route::resource('packages', AdminPackageController::class)->names('admin.packages');
    
    // Payment Configuration
    Route::get('/payments', [AdminPaymentConfigController::class, 'index'])->name('admin.payments.index');
    Route::put('/payments', [AdminPaymentConfigController::class, 'update'])->name('admin.payments.update');
});
```

### 4.3 Proposed Frontend Structure

#### 4.3.1 Packages Pricing Page
- **Path**: `backend/resources/js/Pages/Subscription/Index.tsx`
- **Purpose**: Displays the pricing matrix for Free, Pro, and Business plans. Fetches package data from backend `SubscriptionController`.
- **Integrations**: Replaces the hardcoded cards in Settings page; "Upgrade" button should navigate here.

#### 4.3.2 Checkout Page/Modal
- **Standalone Page**: `backend/resources/js/Pages/Subscription/Checkout.tsx` OR **Reusable Component**: `backend/resources/js/Components/CheckoutModal.tsx`
- **UI Elements**:
  - Bill summary details.
  - Payment method selectors (VietQR Bank Transfer, VNPAY, Momo).
  - VietQR QR-Code container (renders dynamically using amount, bank branch, and custom reference code).
  - Countdown clock (for pending bank notifications).

#### 4.3.3 Admin Package CRUD Dashboard
- **Path**: `backend/resources/js/Pages/Admin/Packages/Index.tsx`
- **UI Elements**:
  - Table showing package details (Price, Session limits, Feature status).
  - Action buttons: Add New, Edit, Delete packages.
  - Form dialogs built using shadcn/ui `<Dialog>` and `<Input>` components.

#### 4.3.4 Admin Payment Configuration Tab
- **Path**: `backend/resources/js/Pages/Admin/Payments/Index.tsx`
- **UI Elements**:
  - Secure inputs for Stripe keys, VNPAY secret codes, or Momo credentials.
  - Input field for admin bank branch, account name, and account number for VietQR generation.

---

## 5. Baseline Test Verification
A full suite execution was performed via `php artisan test`.
- **Total Tests Executed**: 44
- **Passed Tests**: 44
- **Assertions Asserted**: 392
- **Execution Duration**: 2.69 seconds

The current test baseline verifies auth routing, session CRUD, background comments analysis, lock safety constraints, and profile editing. No tests are failing, making it safe to introduce subscription system modifications.

---

## 6. Project Coverage Report (Core Mode)

| Category | Audited Scope | Full Files Read | Scanned / Listed Only | Gaps / Unknowns |
|---|---|---|---|---|
| **Models** | `app/Models/` | `User.php`, `Product.php`, `LiveSession.php`, `LiveEvent.php`, `LiveStat.php` | `LiveSessionKeyword.php`, `LiveSessionStatsHistory.php` | None |
| **Controllers** | `app/Http/Controllers/` | `DashboardController.php`, `ProductController.php` | `LiveSessionController.php`, `ProfileController.php`, `ReportController.php`, `SettingsController.php` | None |
| **Migrations** | `database/migrations/` | None | All migrations (file names list, schema attributes inferred) | Internal table index specifications |
| **Routes** | `routes/` | `web.php`, `api.php` | `auth.php`, `console.php` | None |
| **Middlewares** | `app/Http/Middleware/` | `EnsureUserIsAdmin.php`, `HandleInertiaRequests.php` | None | None |
| **React Pages** | `resources/js/Pages/` | `Settings/Index.tsx`, `Admin/Settings/Index.tsx` | `Admin/Dashboard.tsx`, `Admin/Users/Index.tsx`, `Dashboard.tsx` | UI styling details |
| **React Components** | `resources/js/Components/` | `app-sidebar.tsx`, `admin-sidebar.tsx`, `Layouts/AdminLayout.tsx`, `Layouts/AuthenticatedLayout.tsx` | `ui/` components folder | Internal styling scripts |
| **Tests** | `tests/` | `tests/Feature/AnalyzeCommentsJobTest.php` | `tests/Feature/AnalyzeCommentsJobAdversarialTest.php`, other auth tests | None |

---

## 7. Evidence Ledger

| Area | Conclusion/Fact | Evidence Source File | Direct Evidence Reference | Confidence |
|---|---|---|---|---|
| Admin Authorization | Admin actions require `ensure_admin` middleware | `routes/web.php` | Route group middleware config (lines 63-199) | High |
| Role Check Implementation | User checks `role === 'admin'` to verify access | `app/Models/User.php` | `isAdmin()` implementation (lines 36-39) | High |
| Inertia Auth injection | React pages receive auth user info implicitly | `app/Http/Middleware/HandleInertiaRequests.php` | `share()` method maps `auth.user` (lines 34-36) | High |
| System Config Format | Configuration stored inside `system_settings.json` | `routes/web.php` & `Admin/Settings/Index.tsx` | Read/write local storage operations (lines 172-181) | High |
| Subscription Visuals | Setting page renders mock subscription statistics | `resources/js/Pages/Settings/Index.tsx` | Card with title "Gói đăng ký" (lines 106-140) | High |
