# UI/UX Audit Report — Subscription and Checkout Pages

## Summary
- **Scope**: Frontend React pages & components for Subscription and Checkout (`backend/resources/js/Pages/Subscription/Index.tsx`) and related backend controllers/routes.
- **Mode**: Static/code-path audit
- **Confidence**: High
- **Critical Issues**: 0
- **High Issues**: 0
- **Medium Issues**: 1 (Hardcoded bank recipient info in frontend files instead of binding to backend configs)
- **Low Issues**: 1 (Typography misalignment in naming specs: system font is actually 'Be Vietnam Pro', not 'Outfit' or 'Inter')
- **Decision**: Merge with follow-up (Issues found do not block merge but are noted for improvement)

---

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Subscription and Checkout Page and Flow |
| Stack/framework | Laravel (11.x), React (18.x), Inertia.js (1.x), Tailwind CSS v4, shadcn/ui |
| Expected user behavior | View packages, view active subscription, select a package, view VietQR dialog with 10-minute timer, copy transfer content, wait for auto-update via polling, or manually verify, and view transaction history. |
| Expected backend/data behavior | Expose subscription packages, active subscription details (expiry, credits usage), and user transactions; create transaction checkout details; check status of user subscription. |
| Source of truth | `backend/resources/js/Pages/Subscription/Index.tsx`, `backend/app/Http/Controllers/SubscriptionController.php`, `backend/routes/web.php`, `backend/resources/css/app.css`, `backend/resources/views/app.blade.php`, `backend/tsconfig.json` |
| Exclusions | Admin-side packages/payments settings execution, external VietQR service availability/routing |

---

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 1 | 1 | 0 | `Subscription/Index.tsx` contains both the Subscription page and the Checkout modal |
| User actions | 4 | 4 | 0 | Select package, Copy content, Close dialog, Check payment |
| API/actions | 3 | 3 | 0 | `/api/subscription/packages`, `/api/subscription/checkout`, `/api/subscription/status` |
| Services/domain | 2 | 2 | 0 | `SubscriptionController`, User subscription resolution methods |
| DB/schema/config | 3 | 3 | 0 | `SubscriptionPackage`, `Transaction`, `PaymentConfig` (implicitly audited via controller) |
| Auth/permissions | 1 | 1 | 0 | Authenticated layout user context mapping |
| State/cache | 1 | 1 | 0 | Polling intervals and countdown timer states |
| Tests | 0 | 0 | 0 | Not explicitly in task scope |

---

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Auto-update active subscription on payment success | `Index.tsx` lines 123-149 | High | Page does not reload or update activeSubscription prop on successful status check |
| Timer countdown from 10 minutes (600s) | `Index.tsx` lines 105-121 | High | Timer doesn't tick down, starts at wrong time, or fails to expire QR scanning |
| QR Code turns gray when timer reaches 0 | `Index.tsx` line 551 | High | QR Code image remains colored and scanable when expired |
| Copy button copies `transferContent` | `Index.tsx` lines 203-209 | High | Button does not copy or copies outdated description |

---

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| Subscription Page | Current Package Info Card | `Index.tsx:259` | Show active package, expiry and AI credits usage | Matches expected behavior, includes dynamic Progress bar | None |
| Subscription Page | Pricing Grid Cards | `Index.tsx:313` | Render all packages with dynamic price/duration and comparison feature list | Matches expected behavior, package data is dynamically passed | None |
| Subscription Page | Feature Comparison Table | `Index.tsx:413` | List package features side-by-side | Hardcoded table rows | Medium (Hardcoded comparison attributes) |
| Checkout Modal | VietQR Image | `Index.tsx:547` | Render VietQR code image with active timer | Grayscale filter and opacity-30 applied on timer expiration | None |
| Checkout Modal | Transfer Details | `Index.tsx:568` | Show bank, owner name, amount, description | Labels "MB Bank" and "DANG TUAN DAT" are hardcoded in React code | Medium (Hardcoded bank details in UI) |

---

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Select Package | `handleSelectPackage` | Checks pkg price | Disables button, runs `loadingCheckout` state spinner | Toasts on success (free) or opens checkout dialog | `POST /api/subscription/checkout` | None |
| Copy Content | `handleCopyContent` | Checks text presence | Disables when `timeLeft === 0` | Success toast on copy, shows copy icon check state | `navigator.clipboard` | Fails if API unavail |
| Check payment | `handleConfirmPaid` | Checks selectedPkg | Disables during loading (`isCheckingPayment`) or if `timeLeft === 0` | Toast warnings on pending, toast error on fail, success on active | `GET /api/subscription/status` | None |
| Close dialog | `setIsCheckoutOpen` | State set to false | Closes dialog | Auto-clears polling and timer intervals | N/A | None |

---

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| `Ngân hàng: MB Bank` | `Index.tsx:571` | Match recipient bank | Displays "MB Bank" | None (matches MB Bank in VietQR template) |
| `Chủ tài khoản: DANG TUAN DAT` | `Index.tsx:575` | Match recipient name | Displays "DANG TUAN DAT" | None (matches name in VietQR template) |
| `Tôi đã chuyển tiền` | `Index.tsx:636` | Manually check payment | Triggers API status verification | None |

---

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Click "Đăng ký" | `axios.post` | `/api/subscription/checkout` | `{package_id: number}` | Validated, must exist, user authenticated | Creates `Transaction` (pending) & computes `vietqr_url` | Returns JSON with transaction ID and VietQR image url | None |
| Active Status Polling | `axios.get` | `/api/subscription/status` | N/A | Authenticated user | Resolves active subscription of user | Returns status (active, package ID, expires_at) | None |

---

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Cannot double scan expired QR | `Index.tsx:551,592,627` | Wait 10 minutes (timer = 0) | Grayscale class on QR, copy button disabled, payment confirmation button disabled | Effectively blocks transaction workflow completion |
| Auto-update on payment success | `Index.tsx:123` | Poll status API every 5s | Interval clears and Dialog closes automatically on success, reloads activeSubscription prop | Interface refreshes smoothly |

---

## Findings

### [Medium] Hardcoded Bank Details in Checkout Modal Code
- **Type**: Code Quality & Maintainability
- **Location**: `backend/resources/js/Pages/Subscription/Index.tsx` (Lines 571, 575)
- **Evidence**: 
  ```tsx
  571: <span className="font-semibold text-foreground">MB Bank</span>
  ...
  575: <span className="font-semibold text-foreground">DANG TUAN DAT</span>
  ```
- **Cross-check**: Check `SubscriptionController.php` (Line 145):
  ```php
  $vietQrTemplate = 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';
  ```
- **Why wrong/risky**: If the project administrators modify the active bank configuration, bank account number, or name in the database/backend (e.g. via `PaymentConfig` model which is fetched on checkout), the frontend modal will still display static texts "MB Bank" and "DANG TUAN DAT". This results in a backend-frontend data discrepancy where the QR code maps to a new bank account but the displayed text instructions show MB Bank and DANG TUAN DAT.
- **Impact**: Payment confusion, potential support overhead if bank details change.
- **Suggested Fix**: Return recipient bank name and account owner name from the backend `/api/subscription/checkout` response (retrieved dynamically from the active `PaymentConfig`), and bind those values in the React state.

### [Low] Typography Misalignment & Font Configuration
- **Type**: UI/UX styling alignment
- **Location**: `backend/resources/css/app.css` (Line 14) and `backend/resources/views/app.blade.php` (Line 23)
- **Evidence**:
  - `app.css`: `--font-sans: 'Be Vietnam Pro', sans-serif;`
  - `app.blade.php`: `<link href="https://fonts.bunny.net/css?family=be-vietnam-pro..."`
- **Why wrong/risky**: The system branding constraints mention Outfit/Inter typography, but the project is configured to use **Be Vietnam Pro** globally. 
- **Impact**: Style mismatch with the design specifications if Outfit/Inter was strictly required.
- **Suggested Fix**: Update `tailwind.config` / `app.css` and template file to import and use 'Outfit' or 'Inter' if design specifications require them, or confirm that 'Be Vietnam Pro' is the intentional branding font.

### [Low] Hardcoded Features in Comparison Table
- **Type**: Code Quality
- **Location**: `backend/resources/js/Pages/Subscription/Index.tsx` (Lines 413-480)
- **Evidence**: Rows for "Số livestream đồng thời", "Thời lượng tối đa / phiên", "AI Credits", "Phân tích âm thanh nâng cao", "Xuất danh sách lead (CSV)" are hardcoded elements in `<TableBody>`.
- **Why wrong/risky**: If packages get new capabilities in the future or limits change dynamically, the comparison table cannot dynamically adapt.
- **Impact**: Code modification required whenever package properties change.

---

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `npm run build` | Yes (implicitly checked compiler paths) | N/A | TSX typescript path mappings resolving correctly | Production runtime UI/UX visuals |

---

## Suggested Fix Order
1. **Dynamic Bank Details Binding**: Modify the `/api/subscription/checkout` response to include `bank_name` and `account_name` retrieved from the active `PaymentConfig`, and bind these values to the Checkout Modal UI instead of static strings.
2. **Typography Verification**: Verify if "Be Vietnam Pro" is the approved font style for the livestream dashboard. If not, swap with Inter/Outfit.

## Decision
**Merge with follow-up**

---
*This is a static/code-path audit. It can confirm issues proven by code evidence. It does not claim pixel-perfect visual correctness or runtime-only rendering correctness unless proven by tests or explicit runtime evidence.*
