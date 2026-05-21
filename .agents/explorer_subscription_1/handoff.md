# Handoff Report — Subscription & Payment System Exploration

## 1. Observation
The following file paths, line references, and exact code configurations were examined:
- **Admin Routing and Access Control**:
  - `backend/routes/web.php` (lines 63-199): Admin route definitions (`admin/dashboard`, `admin/users`, `admin/settings`) are guarded by `auth`, `verified`, and `admin` middleware.
  - `backend/app/Http/Middleware/EnsureUserIsAdmin.php` (lines 13-15):
    ```php
    if (! $request->user()?->isAdmin()) {
        abort(403, 'Bạn không có quyền truy cập khu vực này.');
    }
    ```
- **Mock Subscription UI**:
  - `backend/resources/js/Pages/Settings/Index.tsx` (lines 107-140): Renders mock pricing information:
    ```tsx
    <div className="flex items-center justify-between rounded-lg border p-4">
      <div>
        <div className="flex items-center gap-2">
          <span className="font-semibold">Gói Pro</span>
          <Badge>Đang sử dụng</Badge>
        </div>
        ...
      </div>
      ...
    </div>
    ```
- **Existing Migrations**:
  - Path `backend/database/migrations/` was listed. There are no tables corresponding to `subscription_packages`, `user_subscriptions`, or `payment_methods/configurations`.
- **System Settings Storage**:
  - `backend/routes/web.php` (lines 172-181): Shows application configs are stored as a JSON object in `system_settings.json` locally.
- **Verification Commands Run**:
  - Execution of `php artisan test` inside the `backend/` directory.
  - Result: `Tests: 44 passed (392 assertions) Duration: 2.69s`.

## 2. Logic Chain
1. The codebase uses Laravel 11 + React Inertia structure, where controller handlers render Inertia views directly and inject data via React props (observed in `web.php` and `DashboardController.php`).
2. Admin control is already implemented using routing middleware `EnsureUserIsAdmin.php` and role verification on the `User` model, providing a ready template for admin-facing package management routes.
3. Subscription and payment models are entirely absent from migrations and the database, proving that the feature set has not yet been designed or implemented in the persistence layer.
4. User settings display static information for package names, limits, and pricing. Connecting these to the backend requires introducing models for packages/subscriptions, passing them to the page component, and rewriting settings props.
5. Setting up payment gateways will require expanding `system_settings.json` or introducing database config records, accessible through admin endpoints.

## 3. Caveats
- Only static configuration and existing mock files were examined. Payment gateway sandbox details (IPN protocols, security hashes) were not set up.
- Code changes were not performed as this is a read-only investigation.

## 4. Conclusion
- The backend lacks subscription database schemas and controllers.
- The frontend has mock subscription options.
- Introducing subscriptions requires:
  1. Creating migrations for packages and subscriptions.
  2. Setting up customer routing (`/subscription`, `/checkout`, callback webhooks) and controllers.
  3. Setting up admin routing (`/admin/packages`, `/admin/payments`) and controllers.
  4. Creating checkout layouts (`Checkout.tsx` or `CheckoutModal.tsx`) and admin dashboard management views.
- System testing baseline is healthy with 44/44 tests passing.

## 5. Verification Method
- Execute the test suite using `php artisan test` from `backend/` to verify baseline integrity.
- View files `backend/routes/web.php` and `backend/resources/js/Pages/Settings/Index.tsx` to confirm target modification areas.
