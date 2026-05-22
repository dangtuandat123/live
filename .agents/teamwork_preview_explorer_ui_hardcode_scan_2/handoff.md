# Handoff & Audit Report

## 1. Observation

A detailed audit of the React Pages in `backend/resources/js/Pages/` and the corresponding Laravel backend files was conducted to identify hardcoded UI components, pricing, features, beneficiary bank details, transaction history, and admin revenues.

### Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Hardcoded values scan & Dynamic migration mapping |
| Stack/framework | Laravel 11, Inertia.js, React 18, TypeScript, TailwindCSS, Eloquent ORM |
| Expected user behavior | Dynamic subscription packages listing, dynamic checkout with DB-backed bank details and VietQR QR codes, view authentic transaction history. |
| Expected backend/data behavior | Admin configurations saved in DB, real-time calculations of admin dashboard stats, and strict API-level validation of package attributes and checkout limits. |
| Source of truth | Model files (`User.php`, `SubscriptionPackage.php`, `PaymentConfig.php`, `Transaction.php`), database migrations, and routes/controllers. |
| Exclusions | External payment gateway API connections (running in offline/local mock mode with callback simulations). |

---

### Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 4 | 4 | 0 | Scanned `Subscription/Index.tsx`, `Admin/Packages/Index.tsx`, `Admin/Payments/Index.tsx`, and `Admin/Dashboard.tsx` |
| User actions | 4 | 4 | 0 | Scanned packages index rendering, checkout dialog trigger, checkout payment rendering, transaction listing |
| API/actions | 3 | 3 | 0 | Checked `GET /subscription`, `GET /api/subscription/packages`, `POST /api/subscription/checkout` |
| Services/domain | 1 | 1 | 0 | Checked `SubscriptionController.php` |
| DB/schema/config | 6 | 6 | 0 | Analyzed `payment_configs`, `subscription_packages`, `transactions`, `user_subscriptions` tables |
| Auth/permissions | 2 | 2 | 0 | Verified role-based dashboard/settings prefix and user active subscription resolver |
| State/cache | 1 | 1 | 0 | Checked database locking/concurrency controls in `SubscriptionController.php` |
| Tests | 5 | 5 | 0 | Ran all 86 unit and feature tests including subscription gating/payments |

---

### Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Tenant/User separation of transactions | `routes/web.php` line 278 | A user attempts to view another's transactions | `$user->transactions()` scope in route | Fully secure; scoped to authenticating user |
| Concurrent free checkout abuse | `SubscriptionController.php` lines 79-95 | Parallel requests for same free package | `lockForUpdate()` on User and `UserSubscription` query | Protected by DB lock transaction |
| Package delete dependencies | `SubscriptionController.php` lines 240-244 | Deleting package with existing users | `UserSubscription` existence check before delete | Blocks deletion with error |

---

### Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Subscription/Index.tsx` | Checkout Modal Bank Name | Line 838: `checkoutData?.beneficiary_bank \|\| 'MB Bank'` | Dynamically shows beneficiary bank name from active configuration. | Defaults to hardcoded 'MB Bank' if missing. | Hardcoded fallback in React and PHP. |
| `Subscription/Index.tsx` | Checkout Modal Account No | Line 847: `checkoutData?.beneficiary_account \|\| '11183041'` | Dynamically shows beneficiary account number. | Defaults to hardcoded '11183041' if missing. | Hardcoded fallback in React and PHP. |
| `Subscription/Index.tsx` | Checkout Modal Account Name | Line 856: `checkoutData?.beneficiary_name \|\| 'DANG TUAN DAT'` | Dynamically shows beneficiary owner name. | Defaults to hardcoded 'DANG TUAN DAT' if missing. | Hardcoded fallback in React and PHP. |
| `Subscription/Index.tsx` | Package features list | Lines 463-547 | Dynamically displays all features associated with the packages. | Checks hardcoded object keys and displays static items. | Any new feature added by admin will not render. |
| `Admin/Dashboard.tsx` | KPI cards change percentage | `routes/web.php` line 148, 154 | Calculates growth percent and activity text dynamically. | Text strings `+15% so với tháng trước` and `Dựa trên phiên live gần đây` are hardcoded. | Stat change descriptions are static. |

---

### Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Initiate Checkout | `handleCheckout` | Check if package ID exists in DB | Displays spinner during `/api/subscription/checkout` call | Shows Dialog with QR and bank details on success | `POST /api/subscription/checkout` | Falls back to hardcoded payment details if DB seed is missing |
| Admin update payments config | `handleSubmit` | Validates method, bank fields, templates, urls | Form submitting state blocked | Triggers Inertia redirection and success toast | `PUT /admin/payments` | Input JSON templates fall back to static text if null in DB |

---

### Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| `'MB Bank'` | `Subscription/Index.tsx` line 839 | Actual active bank | Fallback to hardcoded | Yes, fallback is hardcoded |
| `'11183041'` | `Subscription/Index.tsx` line 848 | Actual active account no | Fallback to hardcoded | Yes, fallback is hardcoded |
| `'DANG TUAN DAT'` | `Subscription/Index.tsx` line 857 | Actual account name | Fallback to hardcoded | Yes, fallback is hardcoded |

---

### Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Render Subscriptions page | `Subscription/Index.tsx` | `GET /subscription` | None | Auth required | Fetches packages sorted by price, user transactions, and active sub | Renders plans, active duration, and transactions history | Package feature keys are hardcoded in React, but dynamic JSON in DB |
| Trigger package payment | `handleCheckout` | `POST /api/subscription/checkout` | `{package_id: number}` | Auth required, package exists validation | Creates a transaction; retrieves active `PaymentConfig` details | Returns transaction ID, QR code URL, and bank info | Falls back to static bank details on error/null config |

---

### Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Beneficiary Details | Malicious Admin | Payment Config Form | Lack of strict validation on QR template URL structure | Injection of malicious QR templates redirecting payments to wrong beneficiary accounts | Medium |
| Package Pricing | Client | Checkout API | None | Attempting to forge free checkout (handled properly via price === 0 validation on server) | Low (Resolved) |

---

### Detailed Findings

#### Finding 1: Hardcoded Bank Fallback Details (Medium Severity)
* **Location**:
  - Frontend: `backend/resources/js/Pages/Subscription/Index.tsx` (lines 838-858)
  - Backend: `backend/app/Http/Controllers/SubscriptionController.php` (lines 146-150, 175-177)
* **Evidence**:
  ```tsx
  {checkoutData?.beneficiary_bank || 'MB Bank'}
  {checkoutData?.beneficiary_account || '11183041'}
  {checkoutData?.beneficiary_name || 'DANG TUAN DAT'}
  ```
  And in the controller:
  ```php
  $vietQrTemplate = $paymentConfig->qr_template ?? 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';
  $accountNoVal = rawurlencode($paymentConfig->account_no ?? '11183041');
  'beneficiary_bank' => $paymentConfig->bank_name ?? 'MB Bank',
  ```
* **Why wrong**: Keeps configuration credentials coupled within code. If these fallbacks trigger, the user pays to the old credentials.
* **Suggested Fix**: Remove hardcoded fallbacks from both React views and PHP code. Instead, enforce payment configuration checks on the backend (throw configuration errors/503 if missing) and require the admin to set them up on install.

#### Finding 2: Hardcoded Features Properties on Subscription (Medium Severity)
* **Location**: `backend/resources/js/Pages/Subscription/Index.tsx` (lines 463-547, 583-694)
* **Evidence**:
  ```tsx
  {pkg.features.limit_streams === -1 ? 'Vô hạn phiên livestream' : `Tối đa ${pkg.features.limit_streams} phiên livestream`}
  ```
* **Why wrong**: The database supports a dynamic JSON structure for package features (`subscription_packages.features`), and the Admin package CRUD handles arbitrary arrays of strings as tags. However, `Subscription/Index.tsx` has a strict TypeScript `PackageFeatures` interface and explicitly checks hardcoded keys, preventing the addition of new feature rows without a codebase change.
* **Suggested Fix**: Return a dynamic list of features from the database (either as an array of strings representing plan list items, or a config metadata schema mapping key names to dynamic labels).

#### Finding 3: Static KPI Dashboard Change Labels (Low Severity)
* **Location**: `backend/routes/web.php` (lines 148, 154)
* **Evidence**:
  ```php
  'change' => '+15% so với tháng trước',
  'trend' => 'up',
  ```
* **Why wrong**: The percentage growth and trend direction for Admin revenue is hardcoded, presenting false dashboard metrics.
* **Suggested Fix**: Query current month's revenue against previous month's revenue dynamically in the admin dashboard route and compute the percentage difference and trend.

---

## 2. Logic Chain

1. **Mapping Database Schema**: We verified that `payment_configs` has columns `bank_name`, `bank_id`, `account_no`, `account_name`, and `qr_template`. It is populated via `PaymentConfigSeeder.php` and migration `2026_05_22_000000_add_beneficiary_details_to_payment_configs_table.php` with default values `MB Bank`, `11183041`, etc.
2. **Controller Integration**: `SubscriptionController@checkout` queries the database table `payment_configs` for the active setting. It correctly replaces placeholders in the VietQR URL template and returns `beneficiary_bank`, `beneficiary_account`, and `beneficiary_name` to the frontend checkout request.
3. **Frontend Redundancy**: The frontend checkout Dialog displays these fields, but uses the `|| 'Fallback'` syntax. Since the API returns values from the database, the hardcoded fallbacks in `Subscription/Index.tsx` are only hit if the config row in the DB is empty or missing those columns.
4. **Backend Redundancy**: The controller also has hardcoded fallback values in case the config record is missing fields. 
5. **Decoupling Action**: In order to make the system fully dynamic and clean, we should delete the code-level credentials. If an admin deletes the configuration, the system must show a validation error or disable subscription checkouts rather than falling back to hardcoded credentials, avoiding revenue confusion.

---

## 3. Caveats

- **Webhook Callback Logic**: The payment configuration details determine the webhook signature or params format. We did not audit the webhook payload validator since it was out of scope of the UI scan, but we assumed it uses the configuration templates saved in `PaymentConfig`.
- **Theme and Assets**: Changing features to completely dynamic lists of strings might change the grid or card height matching in the CSS layout of `Subscription/Index.tsx`. The design must handle dynamic content length gracefully.

---

## 4. Conclusion

The application is partially dynamic: it fetches packages and active configs from the DB, but leaves fallback hardcoded constants in the code (MB Bank, DANG TUAN DAT, 11183041) and limits features listing to a fixed UI matrix. 

### Proposed Actionable Fixes

#### A. Dynamic Bank Details & Config Enforcements
1. **Frontend**: Clean up `Subscription/Index.tsx` to remove the default values:
   ```tsx
   {checkoutData?.beneficiary_bank}
   {checkoutData?.beneficiary_account}
   {checkoutData?.beneficiary_name}
   ```
   Add a check: if `checkoutData` is empty or missing, display a warning state in the dialog.
2. **Backend**: Modify `SubscriptionController@checkout` to fail explicitly if payment configs are not set up:
   ```php
   $paymentConfig = PaymentConfig::where('is_active', true)->first();
   if (!$paymentConfig || !$paymentConfig->account_no || !$paymentConfig->bank_name) {
       return response()->json([
           'error' => 'Service Unavailable',
           'message' => 'Cấu hình thanh toán chưa đầy đủ. Vui lòng liên hệ Admin.',
       ], 503);
   }
   ```
   Remove all `?? '11183041'` or `?? 'DANG TUAN DAT'` fallbacks from the PHP code.

#### B. Dynamic Features List
1. **Model Modification**: Add a `features_list` helper method or cast `features` to a list of strings on the package level.
2. **Frontend UI**: Map over the dynamic array of feature descriptions in `Subscription/Index.tsx` instead of checking individual keys:
   ```tsx
   {pkg.features_list && pkg.features_list.map((feature, idx) => (
       <li key={idx} className="flex items-start gap-2">
           <CheckIcon className="mt-0.5 size-4 shrink-0 text-emerald-500" />
           <span>{feature}</span>
       </li>
   ))}
   ```

#### C. Dynamic Revenue & KPI Growth
1. Update `routes/web.php` for Admin Dashboard:
   - Calculate revenue differences between the current month and the previous month.
   - Dynamically build the `change` and `trend` props for the KPI cards.

---

## 5. Verification Method

To verify these changes after implementation, run:

1. **Unit / Feature Tests**:
   Ensure all package and checkout API tests pass:
   ```bash
   php artisan test
   ```
2. **Database Seed Integrity**:
   Verify the seeder is executed correctly to fill the dynamic configuration:
   ```bash
   php artisan db:seed --class=PaymentConfigSeeder
   php artisan db:seed --class=SubscriptionPackageSeeder
   ```
3. **Manual UI Inspection**:
   - Access `/admin/payments` and modify the beneficiary details.
   - Go to `/subscription` as a user and initiate a checkout. Verify the popup modal shows the updated details instead of the old hardcoded ones.
   - Verify changing packages in `/admin/packages` propagates the pricing and features dynamically to the subscription cards page.

---

### Suggested Fix Order
1. **Step 1 (Database & Backend Enforcement)**: Remove fallbacks in `SubscriptionController.php`, update checkout error responses, and add config checks.
2. **Step 3 (Frontend Modal Cleanups)**: Remove default string operators (`||`) in `Subscription/Index.tsx` and introduce loading/error states in checkout.
3. **Step 3 (Dynamic Features Mapping)**: Refactor packages to pass an array of feature strings, rendering them dynamically.
4. **Step 4 (Dashboard Growth Metrics)**: Refactor `routes/web.php` admin stats calculation.

**Decision**: **Merge with follow-up** (Codebase is safe and correct in its current state, but contains static fallbacks which can be refactored to achieve complete dynamic synchronization as outlined above).
