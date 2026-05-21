# Original User Request

## Initial Request — 2026-05-21T13:57:31Z

# Teamwork Project Prompt — Final

The user wants to perform an evidence-driven deep audit (18-pass workflow) on the implemented "Solution G: Text + Audio + Memory" for their TikTok livestream comment analysis pipeline, checking if everything is implemented correctly according to the best standards and identifying any errors or issues.

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Deep Audit Implementation
Thoroughly audit the AI comment analysis pipeline (Text + Audio + Memory) implemented in the following target files:
- backend/app/Jobs/AnalyzeCommentsJob.php
- backend/app/Models/LiveSession.php
- backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php
- backend/tests/Feature/AnalyzeCommentsJobTest.php

Identify any bugs, race conditions, edge cases, type safety issues, database integrity issues, or performance issues (like N+1 queries or cache issues).

### R2. Adherence to Rules
Verify that the implementation fully adheres to the specific global user rules (RULE[user_global] / RULE[agent.md]) and the Evidence Deep Audit workflow rules.

## Acceptance Criteria

### Audit Completion
- [ ] Perform a full static and code-path analysis covering all 18 passes of the evidence-deep-audit-v3-12k workflow.
- [ ] Produce a comprehensive Audit Report in markdown at `C:\Users\ADMIN\.gemini\antigravity\brain\9e05c9cd-c52d-4900-bfb1-3c02aa45407d\evidence_deep_audit_report.md` covering:
  - Scope Lock + Stack Profile
  - Expected Behavior Contract
  - Coverage Ledger
  - Static UX Matrix, Action Matrix, Copy/Text Matrix
  - Frontend-Backend Matrix, Backend Abuse Matrix
  - Invariant Matrix, State/Async/Race Matrix
  - Security/Privacy Matrix, Performance/Reliability/Data Integrity Matrix
  - Test/Mutation Gap Matrix
  - Findings classified by severity (Critical, High, Medium, Low, Info) with exact locations, evidence, impact, and minimal fixes.
- [ ] Ensure all automated tests (`php artisan test`) pass successfully.

## Follow-up — 2026-05-21T14:14:15Z

# Teamwork Project Prompt — Final

The user wants the teamwork multi-agent team to fix all the High and Medium severity bugs and performance bottlenecks identified in the recent AI comment analysis pipeline audit report ([evidence_deep_audit_report.md](file:///C:/Users/ADMIN/.gemini/antigravity/brain/9e05c9cd-c52d-4900-bfb1-3c02aa45407d/evidence_deep_audit_report.md)).

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Fix Pipeline Stalls
- Address the *Text-less Comments Pipeline Stall* and *Unrecoverable Error Stall* in `AnalyzeCommentsJob.php` so that the comment analysis pipeline continues processing the next batches of comments under all conditions (emoji-only batches, failures, etc.).

### R2. Optimize Stats Aggregation
- Replace the O(N^2) stats recalculation query in `AnalyzeCommentsJob.php` with an efficient delta/incremental update or optimized calculation mechanism to prevent performance degradation on long livestreams.

### R3. Resolve Robustness & Reliability Risks
- Fix the *TypeError Risk* (null check on TikTok live snapshot).
- Adjust the unique lock duration or mechanism to prevent race conditions during long-running API requests.
- Address the brittle manual cache key clearing logic.

### R4. Test Coverage & Validation
- Ensure all automated tests under `backend/tests/Feature/AnalyzeCommentsJobTest.php` pass successfully.
- Implement tests verifying correct pipeline progression on text-less batches and validation of stats updates.

## Acceptance Criteria

### Correct Execution
- [ ] Comment analysis does not stall on emoji-only batches or recoverable/unrecoverable errors.
- [ ] Database stats are updated accurately without full table scans on every batch.
- [ ] All feature tests in `AnalyzeCommentsJobTest.php` pass cleanly without mock mismatch or errors.

## Follow-up — 2026-05-21T14:42:10Z

# Teamwork Project Prompt — Final

Complete the subscription, payment, and admin configuration features for the livestream analysis SaaS web application.

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Database Schema & Models
- Create `subscription_packages` migration and model (fields: `id`, `name`, `price`, `duration_days`, `features` JSON).
- Create `user_subscriptions` migration and model (fields: `id`, `user_id`, `subscription_package_id`, `starts_at`, `expires_at`, `status`).
- Create `payment_configs` migration and model (fields: `id`, `name`, `prefix`, `suffix`, `webhook_url`, `method`, `params_template` JSON/TEXT, `headers_template` JSON/TEXT).
- Create `transactions` migration and model (fields: `id`, `user_id`, `amount`, `payment_config_id`, `status` [pending, success, failed], `transaction_id`).
- Enable Eloquent relations on `User` model (`subscriptions`, `transactions`).

### R2. Backend APIs & Webhook Callback Handler
- **Subscription API**: Endpoints to list active packages and check the current user's subscription status.
- **Payment API**: Checkout endpoint creating a pending transaction and returning the dynamic VietQR URL:
  `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
  where `{Prefix}` and `{Suffix}` are retrieved from the active `payment_configs`.
- **Payment Callback Endpoint** (POST `/api/payments/callback`):
  - Accepts a JSON payload: `{"id_user": "{user_id}", "sotien": {amount}}`
  - Updates the corresponding user's subscription status (sets/renews the package, calculates expiration dates).
  - Creates/updates a success transaction record.
  - Triggers an outbound HTTP webhook notification to the `webhook_url` configured in `payment_configs` using the configured Method, Headers Template, and Params Template (parsing placeholders: `{user_id}`, `{amount}`, `{transaction_id}`).

### R3. Admin Panel UI (Settings & CRUD)
- Build a responsive Admin interface to manage `payment_configs`. Fields match the design mockup:
  - Name (text input)
  - Prefix (text input), Suffix (text input)
  - Webhook URL (text input)
  - Method (dropdown select: POST, GET, PUT)
  - Params Template (textarea JSON editor) with placeholders: `{user_id}`, `{amount}`, `{transaction_id}`
  - Headers Template (textarea JSON editor)
- Build subscription package management panel (CRUD packages).
- Secure the admin routes using auth middleware and role/permission check (e.g., admin role).

### R4. User Frontend Checkout UI
- Build a beautiful packages listing page showcasing subscription options.
- Add checkout modal/view displaying:
  - Selected package details (Price, Duration)
  - Dynamic VietQR image rendering
  - Instruction copy detailing transfer code syntax: `Prefix {userId} Suffix`.

### R5. Automated Verification Tests
- Create a Feature test case `tests/Feature/SubscriptionPaymentTest.php` verifying:
  - Payment callback processing upgrades user subscriptions.
  - Successful payment callbacks trigger outbound webhook forwards with correct headers/params replacement.
  - Payment config CRUD settings are saved successfully.

## Acceptance Criteria

### Backend & Payments
- [ ] Users can query package list and initiate checkout.
- [ ] VietQR URL correctly uses dynamic prefix, suffix, user_id, and amount.
- [ ] Public callback POST `/api/payments/callback` upgrades user subscriptions correctly.
- [ ] Successful payments trigger outbound HTTP webhook forwarding matching template placeholders.

### UI & UX
- [ ] Admin panel saves payment configurations (prefix, suffix, webhook parameters, and headers) and uses them correctly.
- [ ] Users can view and buy subscription packages with clear VietQR images.
- [ ] All forms validate inputs correctly.

### Testing
- [ ] All automated tests (`php artisan test`) pass successfully.

## Follow-up — 2026-05-21T21:51:27Z

# Teamwork Project Prompt — Final

> Status: Launched
> Goal: Complete the subscription and payment system with VietQR and Admin panel

Complete the subscription, payment, and admin configuration features for the livestream analysis SaaS web application.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Database Schema & Models
- Create migration and model for `subscription_packages` (fields: `id`, `name`, `price`, `duration_days`, `features` JSON).
- Create migration and model for `user_subscriptions` (fields: `id`, `user_id`, `subscription_package_id`, `starts_at`, `expires_at`, `status`).
- Create migration and model for `payment_configs` (fields: `id`, `name`, `prefix`, `suffix`, `webhook_url`, `method`, `params_template` JSON/TEXT, `headers_template` JSON/TEXT).
- Create migration and model for `transactions` (fields: `id`, `user_id`, `amount`, `payment_config_id`, `status` [pending, success, failed], `transaction_id`).
- Establish relationships in the `User` model to associate with subscriptions and transactions.

### R2. Backend APIs & Webhook Callback Handler
- **Subscription API**: Endpoints to list available packages, active package, and check user subscription status.
- **Payment API**: Checkout endpoint creating a pending transaction and returning the dynamic VietQR URL:
  `https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}`
  where `{Prefix}` and `{Suffix}` are retrieved from the active `payment_configs` and `{userId}` is the logged-in user's ID.
- **Payment Callback Endpoint** (POST `/api/payments/callback`):
  - Accepts a JSON payload: `{"id_user": "{user_id}", "sotien": {amount}}`
  - Matches the payment to the user, upgrades their subscription (computes expiration date based on package `duration_days`), and saves the successful transaction.
  - Fires an outbound webhook POST/GET/PUT to the configured `webhook_url` in `payment_configs`, replacing placeholders `{user_id}`, `{amount}`, and `{transaction_id}` in `params_template` and custom headers from `headers_template`.

### R3. Admin Panel UI (Settings & CRUD)
- Build a responsive Admin setting UI matching the mockup schema:
  - Name (text input)
  - Prefix (text input), Suffix (text input)
  - Webhook URL (text input)
  - Method (dropdown select: POST, GET, PUT)
  - Params Template (textarea JSON editor) with placeholders: `{user_id}`, `{amount}`, `{transaction_id}`
  - Headers Template (textarea JSON editor)
- Build CRUD UI for subscription packages.
- Secure all admin routes using appropriate middleware.

### R4. User Pricing & Checkout UI
- Build a subscription package listing page.
- Add checkout view/modal displaying:
  - Package details (Name, Price, Duration).
  - Dynamic VietQR image rendering based on active settings.
  - Specific money transfer instructions with exact transfer code syntax: `Prefix {userId} Suffix`.

### R5. Verification Tests
- Create automated feature tests in `tests/Feature/SubscriptionPaymentTest.php` to verify:
  - Checkout generates correct VietQR URL.
  - Payment callback API correctly upgrades a user's subscription and records transactions.
  - Payment callback successfully fires outbound webhooks with correct replacements.

## Acceptance Criteria

### Backend & Payments
- [ ] Users can query packages and initialize a checkout session.
- [ ] VietQR URL correctly uses dynamic prefix, suffix, user_id, and amount.
- [ ] Public callback POST `/api/payments/callback` upgrades user subscriptions correctly.
- [ ] Successful payments trigger outbound HTTP webhook forwarding matching template placeholders.

### UI & UX
- [ ] Admin panel saves payment configurations (prefix, suffix, webhook parameters, and headers) and uses them correctly.
- [ ] Users can view and buy subscription packages with clear VietQR images.
- [ ] Interface aligns with premium design standards (curated colors, Outfit/Inter typography, responsive layout).

### Testing
- [ ] All automated tests (`php artisan test`) pass successfully.

## Follow-up — 2026-05-21T15:34:23Z

Thực hiện rà soát chuyên sâu toàn bộ dự án livestream phân tích bình luận SaaS (cả Frontend và Backend), hoàn thiện 100% tất cả các tính năng, giao diện, các nút bấm, tối ưu hóa logic backend, bảo mật, và đảm bảo chất lượng chuẩn production.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Deep Audit & Bug Fixing (Backend & Frontend)
- Thực hiện rà soát toàn bộ mã nguồn của dự án (các controller, routes, models, jobs, views).
- Tìm kiếm và sửa lỗi logic, đặc biệt chú ý 3 lỗi được cảnh báo trước đó (Package Price Resolution, Lack of Callback Idempotency, Free Package Checkout Abuse) và các lỗi tiềm ẩn khác.
- Kiểm tra tính đồng bộ dữ liệu giữa Client và Server: xử lý đầy đủ các state (Loading, Empty, Error, Success).

### R2. UI/UX Polishing
- Rà soát các trang giao diện của người dùng (Dashboard, Lives, Products, Reports, Settings, Subscription) và Admin (Dashboard, Users, Settings, Packages, Payments).
- Sửa đổi các copy text bị trùng lặp, nhãn/placeholder không rõ ràng, các hành động chết hoặc các nút bấm chưa được gắn handler hoặc hoạt động không đúng logic.
- Đảm bảo thiết kế cao cấp, đồng bộ, responsive, không bị vỡ layout trên bất kỳ trang nào.

### R3. Security & Robustness
- Sanitize các input đầu vào tại API Controllers.
- Đảm bảo phân quyền truy cập (Role/Permission) tại route và controller chính xác (chỉ Admin mới được cấu hình payment và gói dịch vụ; user chỉ xem được subscription của chính mình).
- Đảm bảo kiểm tra các điều kiện biên của dữ liệu (ví dụ: giá trị âm, null, hoặc sai kiểu dữ liệu).

### R4. Test Coverage & Compilation
- Chạy toàn bộ test suite hiện có của Laravel và đảm bảo không có bài test nào bị lỗi.
- Đảm bảo biên dịch (assets build) thành công 100% không có cảnh báo hoặc lỗi TypeScript.

## Acceptance Criteria

### Hệ thống hoạt động chuẩn logic
- [ ] Không còn lỗi logic trùng lặp giao dịch (Idempotency) khi xử lý callback từ ngân hàng.
- [ ] Việc chọn gói dịch vụ tại checkout phân giải chính xác theo đúng ID gói, không bị nhầm lẫn giữa các gói trùng giá tiền.
- [ ] Người dùng không thể spam đăng ký gói Free để được gia hạn vô hạn ngày sử dụng.
- [ ] Quyền Admin và User được phân tách rõ ràng ở cả route và controller backend.

### Giao diện hoàn thiện 100%
- [ ] Tất cả các nút bấm trên giao diện đều có hoạt động (không có nút bấm chết).
- [ ] Trạng thái Loading và Error hiển thị rõ ràng và tinh tế khi gọi API bất đồng bộ.
- [ ] Toàn bộ UI tuân thủ thiết kế hiện đại, responsive, không có placeholders.

### Kiểm thử và biên dịch
- [ ] 100% các bài test của Laravel (`php artisan test`) đều PASS.
- [ ] Lệnh `npm run build` chạy thành công không có lỗi compile.

## Follow-up — 2026-05-21T16:03:00Z

Thiết kế và hoàn thiện hệ thống gói dịch vụ đăng ký (Subscription Packages) và trang mua gói (Pricing & Checkout) chuẩn nhất cho SaaS LiveStream AI, phân chia chi tiết các giới hạn sử dụng (Active Streams, Max Duration, AI Credits, Audio, Export) ở cả Frontend và Backend để đảm bảo hiệu năng và chống lạm dụng tài nguyên.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Pricing Page & Checkout Flow (User Panel)
- Nâng cấp giao diện trang `Subscription/Index.tsx` thành giao diện cao cấp:
  - Hiển thị bảng so sánh tính năng (Feature Comparison Table) chi tiết giữa các gói (Free, Pro, Enterprise).
  - Tích hợp thêm phần hiển thị số dư **Tín dụng AI (AI Credits)** còn lại và thời gian hết hạn của gói hiện tại của user.
  - Hiển thị danh sách Lịch sử giao dịch (Transaction History) dạng bảng ngay dưới bảng giá, bao gồm các trường: Mã giao dịch, Gói đăng ký, Số tiền, Trạng thái (Thành công, Chờ thanh toán), Ngày tạo.
- Thiết kế Modal checkout VietQR sang xịn, có đếm ngược thời gian thanh toán (10 phút), hiển thị trạng thái xử lý thời gian thực bằng hiệu ứng động (Micro-animations). Tự động gọi API kiểm tra trạng thái kích hoạt mỗi 5 giây.

### R2. Feature Limits & Resource Gates (Backend & Frontend Enforcement)
- **Giới hạn Active Streams (`limit_streams`)**: Khi người dùng tạo phiên live mới (`LiveSessionController@store`), đếm số phiên đang chạy (`connecting`, `live`). Nếu vượt quá `limit_streams` của gói đăng ký hiện tại (Free: 1, Pro: 5, Enterprise: không giới hạn -1), chặn tạo mới và trả về thông báo lỗi.
- **Giới hạn thời lượng phiên (`max_duration_hours`)**: Khi đồng bộ trạng thái live (`LiveSessionController@fetchEvents` hoặc qua cron/job), nếu thời lượng livestream (tính từ `started_at`) vượt quá `max_duration_hours` của gói hiện tại (Free: 1 giờ, Pro: 4 giờ, Enterprise: 24 giờ), tự động dừng phiên live (`status = ended`, cập nhật `ended_at` và ghi chú lỗi thích hợp).
- **Giới hạn Tín dụng AI (`ai_credits`)**: Thêm cột `used_ai_credits` vào bảng `user_subscriptions` (mặc định 0). Trong `AnalyzeCommentsJob`, trước khi gửi bình luận tới AI, kiểm tra xem số bình luận đã phân tích của gói hiện tại (`used_ai_credits`) có vượt quá giới hạn gói (`ai_credits` - Free: 1,000, Pro: 50,000, Enterprise: 500,000) hay không. Nếu hết, chặn phân tích và cập nhật trạng thái lỗi: "Đã hết tín dụng AI của gói dịch vụ". Khi phân tích thành công, cộng dồn số lượng bình luận vào `used_ai_credits`.
- **Giới hạn Audio Analysis (`audio_analysis`)**: Trong `AnalyzeCommentsJob@handle`, nếu gói đăng ký hiện tại có `audio_analysis` = false, bỏ qua việc trích xuất file âm thanh từ livestream TikTok để tiết kiệm tài nguyên hệ thống (gán `$audioB64 = null`).
- **Giới hạn Export Leads (`export_leads`)**: Trên giao diện chi tiết phiên live (`Lives/Show.tsx`), nếu gói đăng ký hiện tại có `export_leads` = false, nút "Xuất leads CSV" và "Sao chép Leads" sẽ bị khóa (hoặc hiển thị modal yêu cầu nâng cấp gói khi bấm vào).

### R3. Admin Package Management Upgrade
- Nâng cấp trang quản lý gói của Admin (`Admin/Packages/Index.tsx`) để hỗ trợ cấu hình có cấu trúc cho các tính năng giới hạn (thay vì chỉ nhập text list đơn giản):
  - Số lượng streams tối đa (input number, -1 là vô hạn).
  - Thời lượng tối đa của một phiên live (input number, tính bằng giờ).
  - Số lượng bình luận phân tích AI tối đa (input number, -1 là vô hạn).
  - Phân tích âm thanh (toggle/switch hoặc checkbox).
  - Xuất leads CSV (toggle/switch hoặc checkbox).
- Lưu các tính năng này chuẩn hóa dưới dạng JSON trong cột `features` của model `SubscriptionPackage`.

### R4. Performance & Validation
- Tạo một migration bổ sung cột `used_ai_credits` (mặc định 0) vào bảng `user_subscriptions`.
- Chia sẻ thông tin gói đăng ký hiện tại, hạn mức sử dụng (used vs max) thông qua Middleware `HandleInertiaRequests` để toàn bộ frontend React đều dễ dàng truy cập và kiểm tra quyền.
- Đảm bảo tất cả các test suite của Laravel (`php artisan test`) đều pass 100%, không bị ảnh hưởng bởi logic phân quyền mới.
- Biên dịch thành công assets (`npm run build`) không phát sinh lỗi TypeScript.

## Acceptance Criteria

### Frontend Pricing & Checkout
- [ ] Giao diện `Subscription/Index.tsx` hiển thị bảng so sánh chi tiết tính năng, trạng thái tín dụng AI, và danh sách lịch sử giao dịch rõ ràng.
- [ ] Modal thanh toán hiển thị VietQR với MB Bank, đúng nội dung chuyển khoản, hỗ trợ copy clipboard nhanh, đếm ngược thời gian thanh toán và tự động cập nhật trạng thái khi nhận được tiền.
- [ ] Chia sẻ thông tin subscription qua Inertia Share để hiển thị trạng thái và giới hạn quyền lợi ở Dashboard, Lives Setup và Lives Show.

### Backend Enforcement (Feature Gates)
- [ ] Chặn tạo live session nếu vượt quá số lượng active streams cho phép.
- [ ] Tự động kết thúc phiên live và cập nhật trạng thái nếu thời lượng live vượt quá `max_duration_hours`.
- [ ] Tích lũy số comment phân tích vào `used_ai_credits` và chặn phân tích AI tiếp tục nếu vượt quá `ai_credits` của gói.
- [ ] `AnalyzeCommentsJob` tự động bỏ qua trích xuất audio nếu gói hiện tại không cho phép `audio_analysis`.
- [ ] Admin có thể cập nhật các cấu hình `limit_streams`, `max_duration_hours`, `ai_credits`, `audio_analysis`, `export_leads` một cách trực quan trong trang quản lý gói.
- [ ] Các unit/feature test chạy thành công 100%.

## Follow-up — 2026-05-21T23:35:20+07:00

Thiết kế và hoàn thiện tối ưu hệ thống gói dịch vụ đăng ký (Subscription Packages), trang mua gói (Pricing & Checkout), quản lý giao dịch và cấu hình phân chia tính năng giới hạn nâng cao (Active Streams, Max Duration, AI Credits, Audio, Export) ở cả Frontend và Backend cho nền tảng SaaS LiveStream AI.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Pricing Page, Live Usage Indicators & Checkout Flow (User Panel)
- **Giao diện bảng giá cao cấp (`Subscription/Index.tsx`)**:
  - Thiết kế UI hiện đại, responsive, sử dụng gradient đẹp mắt và các hover micro-animations sinh động.
  - Hiển thị bảng so sánh tính năng (Feature Comparison Table) chi tiết giữa các gói (Free, Pro, Enterprise).
  - Tích hợp khu vực hiển thị trực quan mức độ sử dụng tài nguyên hiện tại: **AI Credits** (thanh progress bar phần trăm sử dụng kèm số cụ thể), số lượng **Active Streams** đang chạy / tối đa, thời gian hết hạn của gói hiện tại.
  - Bảng lịch sử giao dịch (Transaction History) hiển thị đầy đủ, phân trang rõ ràng, định dạng tiền tệ VND và Badge màu sắc tương ứng trạng thái (Thành công, Đang xử lý, Thất bại).
- **Trải nghiệm Checkout VietQR nâng cao**:
  - Modal thanh toán hiển thị VietQR với logo ngân hàng và thông tin tài khoản chuyên nghiệp.
  - Hỗ trợ nút Copy nhanh cho số tài khoản và nội dung chuyển khoản chuyển màu xanh khi copy thành công.
  - Cơ chế đếm ngược 10 phút. Khi hết hạn, QR code tự động chuyển grayscale, khóa nút xác nhận, và hiển thị thông báo giao dịch đã hết hạn.
  - Polling trạng thái thanh toán tự động mỗi 5 giây khi modal mở. Nếu backend kích hoạt thành công, đóng modal và bắn toast thông báo chúc mừng kèm theo reload page.

### R2. Feature Limits & Resource Gates (Backend Gating & Graceful Stop)
- **Chặn Active Streams (`limit_streams`)**:
  - Khi tạo livestream mới (`LiveSessionController@store`), đếm các luồng có status `connecting` hoặc `live`. Nếu vượt quá giới hạn gói (ví dụ Free: 1, Pro: 5), chặn và trả về lỗi `403 Forbidden` kèm thông điệp rõ ràng yêu cầu nâng cấp gói.
- **Tự động đóng Live quá thời lượng (`max_duration_hours`)**:
  - Định kỳ (trong Job đồng bộ hoặc khi fetch events của session), tính toán thời gian chạy thực tế của livestream. Nếu vượt quá `max_duration_hours` của gói, tự động stop livestream, ghi nhận `ended_at`, chuyển status sang `ended`, và lưu message lỗi chi tiết: "Livestream bị dừng tự động do vượt quá giới hạn thời lượng của gói dịch vụ".
- **Kiểm soát Tín dụng AI (`ai_credits` & `used_ai_credits`)**:
  - Trong `AnalyzeCommentsJob`, kiểm tra số credits khả dụng trước khi gọi AI. Nếu số credits đã sử dụng (`used_ai_credits`) vượt quá hoặc bằng `ai_credits` cho phép, hủy bỏ phân tích, gán log lỗi: "Đã hết tín dụng AI của gói dịch vụ" để bảo vệ tài nguyên hệ thống. Tích lũy số credit thực tế đã tiêu hao sau mỗi lần phân tích thành công vào DB.
- **Bảo vệ tính năng nâng cao (`audio_analysis`, `export_leads`)**:
  - Nếu gói không hỗ trợ `audio_analysis` (false), bỏ qua việc gọi API trích xuất âm thanh TikTok, gán null để tiết kiệm tài nguyên mạng/băng thông.
  - Nếu gói không hỗ trợ `export_leads` (false), ẩn/khóa nút "Xuất leads CSV" và "Sao chép Leads" trên trang `Lives/Show.tsx`, hiển thị popup gợi ý nâng cấp gói dịch vụ khi click.

### R3. Admin Package & Payment Configurations CRUD
- **Quản lý gói đăng ký trực quan (`Admin/Packages/Index.tsx`)**:
  - Thiết kế form thêm/sửa gói dịch vụ cho phép cấu hình trực tiếp các tham số giới hạn dưới dạng input số và checkbox (limit_streams, max_duration_hours, ai_credits, audio_analysis, export_leads) thay vì nhập text thô.
  - Đảm bảo dữ liệu được lưu chuẩn hóa dưới dạng JSON trong trường `features` ở database.
  - Cung cấp cơ chế Backward Compatibility tự động convert dữ liệu cũ từ dạng mảng text sang JSON object khi cập nhật hoặc truy xuất.
- **Thiết lập Cổng Thanh toán VietQR**:
  - Trang Admin cấu hình tài khoản nhận tiền, thiết lập prefix/suffix chuyển khoản và link webhook callback bảo mật.

### R4. Database Seeders & E2E Verification
- Cập nhật database seeders (`DatabaseSeeder`, `SubscriptionPackageSeeder`) để tự động tạo đầy đủ các gói Free, Pro, Enterprise với cấu hình feature chuẩn.
- Viết/Cập nhật các test suite bảo đảm độ bao phủ (coverage) 100% cho:
  - Chặn stream quá hạn mức, tự động ngắt livestream chạy quá giờ.
  - Chặn phân tích AI khi hết credits.
  - Kiểm thử xử lý concurrency trong checkout & webhook callback để chống double-activation.

## Acceptance Criteria

### User Interface & Checkout Experience
- [ ] Trang `Subscription/Index.tsx` hiển thị thông tin gói hiện tại, thanh tiến trình sử dụng AI credits chi tiết, bảng so sánh tính năng và lịch sử giao dịch.
- [ ] Modal thanh toán VietQR hiển thị mã QR nét, nút Copy hoạt động đúng, đếm ngược 10 phút, tự động chuyển màu grayscale khi hết giờ, tự động đóng và reload khi kích hoạt thành công qua polling.
- [ ] Giao diện chi tiết livestream (`Lives/Show.tsx`) khóa tính năng xuất lead nếu gói hiện tại không hỗ trợ và hiển thị Upgrade Dialog.

### System Enforcement & Admin Control
- [ ] Backend chặn tạo stream vượt quá giới hạn và tự động kết thúc stream quá thời lượng tối đa.
- [ ] Job phân tích bình luận kiểm tra tín dụng AI còn lại, cập nhật đúng số credits đã dùng và tắt phân tích audio nếu gói không hỗ trợ.
- [ ] Admin CRUD Packages lưu cấu trúc JSON `features` thành công, có cơ chế xử lý tương thích ngược dữ liệu cũ.
- [ ] Chạy `php artisan test` pass 100% và `npm run build` không có lỗi TypeScript.
