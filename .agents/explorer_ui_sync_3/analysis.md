# Báo cáo Phân tích Chi tiết - Admin & Backend Integrations

## Summary
- **Phạm vi (Scope)**: Các trang Admin Packages/Payments, middleware Inertia và các controller Backend liên quan đến Subscription/Payments.
- **Phương thức (Mode)**: static/code-path audit (kèm chạy test suite xác thực)
- **Độ tin cậy (Confidence)**: High
- **Critical**: 0
- **High**: 0
- **Medium**: 2 (1. Hardcode tổng doanh thu tại trang Payments Admin; 2. Thiếu đồng bộ ràng buộc độ dài và miền giá trị của `features` từ UI xuống validation của API backend khi cập nhật/tạo package)
- **Low**: 1 (Trường giá tiền cho phép giá trị âm hoặc giá trị quá lớn không có cận trên)
- **Decision**: Merge with follow-up (Tất cả tính năng cốt lõi hoạt động đúng, phân quyền chặt chẽ, các lỗi phát hiện đều thuộc nhóm cải tiến giao diện hoặc làm sạch mã nguồn)

---

## Scope, Stack, and Source of Truth
| Item | Value |
|---|---|
| Target | Trang Admin Packages (`Admin/Packages/Index.tsx`), Admin Payments (`Admin/Payments/Index.tsx`), Middleware `HandleInertiaRequests.php`, các Controller `SubscriptionController.php`, `PaymentCallbackController.php`, `LiveSessionController.php` |
| Stack/framework | Laravel 11, React 18, TypeScript, Tailwind CSS, Inertia.js |
| Expected user behavior | Admin có thể quản lý danh sách gói cước (tạo, sửa, xem) và thiết lập cấu hình thanh toán. Người dùng thông thường được phân quyền giới hạn stream, giới hạn thời lượng live, giới hạn AI credits, tính năng Audio Analysis và xuất lead. |
| Expected backend/data behavior | Backend xác thực và kiểm soát các giới hạn bằng middleware/controller. Thanh toán được xử lý thông qua Webhook callback của VietQR, nâng cấp gói dịch vụ tự động và ghi nhận Transaction an toàn bằng database lock. |
| Source of truth | Các file: `User.php`, `HandleInertiaRequests.php`, `SubscriptionController.php`, `PaymentCallbackController.php`, `LiveSessionController.php`, `AnalyzeCommentsJob.php` |
| Exclusions | Không thực hiện kiểm thử giao diện trực quan (pixel-perfect visual QA) |

---

## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | 4 | 4 | 0 | Đã đọc hoàn toàn Packages, Payments Admin, Subscription Index, Live Session Show |
| User actions | 5 | 5 | 0 | Form submit tạo/sửa gói cước, sửa cấu hình thanh toán, xuất lead, checkout |
| API/actions | 6 | 6 | 0 | Đầy đủ các route admin và client cho subscription |
| Services/domain | 3 | 3 | 0 | Traced logic qua TikTokService, RunwareAiService và User Model |
| DB/schema/config | 4 | 4 | 0 | Đã kiểm tra 4 bảng liên quan: `subscription_packages`, `user_subscriptions`, `transactions`, `payment_configs` |
| Auth/permissions | 3 | 3 | 0 | Kiểm tra phân quyền admin, Inertia share, resolveActiveSubscription |
| State/cache | 2 | 2 | 0 | Lock transaction database (`lockForUpdate`), caching/polling |
| Tests | 4 | 4 | 0 | Đã phân tích và chạy toàn bộ 30 test cases feature |

---

## Expected Behavior Contract
| Behavior | Source | Confidence | What would be wrong |
|---|---|---|---|
| Tạo/Sửa Package phải cập nhật DB | `SubscriptionController.php` | High | Dữ liệu features bị null hoặc thiếu trường mặc định dẫn tới lỗi ứng dụng cho người dùng. |
| Giới hạn Livestream hoạt động | `LiveSessionController.php` | High | Người dùng gói thấp có thể mở không giới hạn stream hoặc livestream quá thời lượng tối đa cho phép. |
| Chặn phân tích AI khi hết credit | `AnalyzeCommentsJob.php` | High | AI vẫn tiếp tục phân tích bình luận làm phát sinh chi phí Runware/Gemini API mặc dù user hết hạn/hết credit. |
| Webhook callback an toàn | `PaymentCallbackController.php` | High | Race condition khi gọi webhook trùng lặp, dẫn tới việc nâng cấp gói trùng hoặc kéo dài thời hạn quá mức. |

---

## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
| `Admin/Packages/Index.tsx` | Create/Edit Package Dialog | Line 477-484, 622-629 | Form nhập giá trị features dạng checkbox/input phù hợp với định dạng DB. | Đúng kỳ vọng, có checkbox riêng cho export_leads, audio_analysis, input cho limit_streams. | Không có |
| `Admin/Payments/Index.tsx` | Revenue statistics | Line 105-144 | Hiển thị tổng doanh thu từ dữ liệu Transaction được truyền từ Backend qua Inertia props. | Giao diện hiển thị tổng doanh thu cố định bằng số hardcoded (5.600.000đ). | **Medium**: Số liệu tổng doanh thu trên card là giả/fake, không khớp với tổng doanh thu thực từ danh sách Transaction. |
| `Admin/Payments/Index.tsx` | Transaction status badges | Line 230-245 | Trạng thái transaction chuyển đổi chính xác (success, pending, failed). | Hiển thị chính xác theo trường `status`. | Không có |

---

## Action Matrix
| Action | Handler | Validation | Disabled/Loading | Success/Error | API | Risk |
|---|---|---|---|---|---|---|
| Tạo gói cước mới | `createForm.post` | Client-side & Server-side | Nút submit hiển thị `processing` | Quay lại trang danh sách, hiển thị toast success | `POST /admin/packages` | Trùng tên gói cước (đã được backend validate `unique:subscription_packages,name`) |
| Sửa gói cước | `editForm.put` | Client-side & Server-side | Nút submit hiển thị `processing` | Đóng dialog, hiển thị toast success | `PUT /admin/packages/{id}` | Trùng tên gói cước khi sửa (đã được loại trừ ID hiện tại trong backend validation) |
| Cập nhật cấu hình VietQR | `form.post` | Server-side validation | Nút submit hiển thị `processing` | Reload trang, hiển thị toast success | `POST /admin/payment-config` | Thiếu trường hoặc template QR không hợp lệ |
| Xuất danh sách Lead (CSV/Copy) | `handleCopyAll` / `exportLeadsCSV` | Client-side (`canExportLeads`) | Không có trạng thái loading, phản hồi ngay | Hiện toast báo đã copy hoặc tải file CSV xuống | Client-side trigger | User bypass frontend check gọi copy trực tiếp (không rủi ro vì dữ liệu chỉ là hiển thị từ API trước đó) |

---

## Copy/Text Matrix
| Text | Location | User expectation | Actual behavior | Mismatch |
|---|---|---|---|---|
| "Xuất file danh sách SĐT (export_leads)" | `Admin/Packages/Index.tsx:484` | Giải thích rõ ý nghĩa trường checkbox | Khớp với ý nghĩa phân quyền cho gói cước | Không |
| "Phân tích âm thanh livestream (audio_analysis)" | `Admin/Packages/Index.tsx:472` | Phân tích âm thanh realtime | Khớp với thuộc tính feature | Không |
| "Bạn đã đạt giới hạn số lượng livestream active tối đa..." | `LiveSessionController.php:135` | Thông báo rõ ràng lý do không thể mở thêm stream | Trả về thông báo chi tiết chứa số lượng tối đa của gói | Không |

---

## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
| Cập nhật gói cước | `editForm.put` | `PUT /admin/packages/{id}` | Payload gồm name, price, duration_days, features | Auth admin, check rules validation | Update record `subscription_packages` | Redirect back, update table | Không |
| Cập nhật thiết lập VietQR | `form.post` | `POST /admin/payment-config` | qr_template, bank_id, account_no, account_name | Auth admin | UpdateOrCreate `payment_configs` ID 1 | Redirect back, toast success | Không |
| Tạo phiên live mới | `post('/lives')` | `POST /lives` | tiktok_username, name, products, v.v. | Auth user, check `limit_streams` | Đếm live session active, validate | Redirect to live show page | Không |

---

## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay | Result |
|---|---|---|---|---|---|
| `POST /subscription/checkout` | Trả về 401 | Không ảnh hưởng (sử dụng `$request->user()`) | Truyền gói không tồn tại -> Trả về 404 | Tạo nhiều transaction pending -> được giải phóng khi có transaction mới | Bảo vệ an toàn |
| `POST /api/payments/callback` | Cho phép truy cập không auth (Webhook công cộng) | Không ảnh hưởng | Gửi sai định dạng, sai chữ ký, số tiền không khớp | Nhận webhook trùng lặp nhiều lần | Đã dùng `lockForUpdate` và check trạng thái transaction `success` để chặn replay |

---

## Invariant and State Matrix
| Invariant/Flow | Code locations | Attack case | Evidence | Result |
|---|---|---|---|---|
| Không cho phép livestream đồng thời vượt hạn mức | `LiveSessionController.php:124-138` | User gửi 2 request tạo stream song song | Sử dụng transactional check và validation exception | Chặn tạo livestream vượt hạn mức thành công |
| Tự động dừng livestream khi hết thời gian | `LiveSessionController.php:993-1020` | User tiếp tục live vượt số giờ gói cho phép | API show/fetchEvents tự động trigger dừng, update status sang `ended` | Tự động chấm dứt session và ngắt kết nối TikTok |
| Đảm bảo credits AI không bị âm | `AnalyzeCommentsJob.php:76-88` | Nhiều comment được gửi đến cùng lúc khi credits sắp hết | Job AI check credit ở đầu và trừ credit nguyên tử (`increment`) cuối transaction | Dừng phân tích comment ngay lập tức khi hết credit |

---

## Security/Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
| Quyền quản trị hệ thống | Người dùng thường | `/admin/*` | Middleware validation | Truy cập trái phép trang quản lý gói cước & doanh thu | Bảo vệ an toàn (chặn qua middleware `admin` trong `web.php`) |

---

## Duplicate/Dead Flow Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
| `CheckoutController` | 0 trong web.php | Không có file chết | Logic của CheckoutController trước đây đã được tích hợp trực tiếp vào `SubscriptionController` để quản lý tập trung luồng thanh toán và đăng ký gói. |

---

## Test/Mutation Gaps
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
| Extend active subscription | `SubscriptionPaymentTest::test_callback_extends_active_subscription_of_same_package` | Thay đổi logic gia hạn cộng dồn thời gian | Có | Không |
| Bị chặn tạo livestream vượt limit | `SubscriptionGatingTest::test_stream_limit_gating` | Tắt logic kiểm tra limit trong `LiveSessionController` | Có | Không |

---

## Findings

### 1. [Medium] Hardcoded tổng doanh thu tại trang Payments Admin
- **Loại (Type)**: Giao diện giả / Fake UI card data
- **Vị trí (Location)**: `backend/resources/js/Pages/Admin/Payments/Index.tsx` (Dòng 105-144)
- **Minh chứng (Evidence)**:
  ```tsx
  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
    <CardTitle className="text-sm font-medium">Tổng doanh thu</CardTitle>
    <DollarSign className="h-4 w-4 text-muted-foreground" />
  </CardHeader>
  <CardContent>
    <div className="text-2xl font-bold">5.600.000 đ</div>
    <p className="text-xs text-muted-foreground">+20.1% so với tháng trước</p>
  </CardContent>
  ```
- **Lý do sai/rủi ro (Why wrong/risky)**: Số liệu doanh thu hiển thị trên Dashboard Admin được thiết lập cố định (hardcoded), không phản ánh đúng doanh thu thực tế được tính từ tổng số tiền của các Transaction thành công trong cơ sở dữ liệu.
- **Tác động (Impact)**: Quản trị viên không nắm được báo cáo tài chính chính xác nếu chỉ nhìn vào thẻ thống kê nhanh này.
- **Giải pháp tối thiểu (Minimal fix)**: Tính toán tổng doanh thu trên backend (bằng cách SUM các transaction có status `success`) và truyền qua Inertia props để hiển thị động trên UI.

### 2. [Medium] Thiếu đồng bộ ràng buộc features của gói cước từ Client xuống Server
- **Loại (Type)**: API Validation gap
- **Vị trí (Location)**: `SubscriptionController.php` (các hàm `storeAdminPackage` và `updateAdminPackage`)
- **Minh chứng (Evidence)**:
  ```php
  'features.limit_streams' => 'required|integer',
  'features.max_duration_hours' => 'required|integer|min:1',
  'features.ai_credits' => 'required|integer',
  ```
  Trong khi đó ở phía Frontend:
  - `limit_streams` có miền lựa chọn bao gồm `-1` (không giới hạn) hoặc các số dương.
  - `ai_credits` có miền lựa chọn bao gồm `-1` (không giới hạn) hoặc các số dương.
- **Lý do sai/rủi ro (Why wrong/risky)**: Phía backend chỉ validate `integer` mà không quy định cụ thể miền giá trị hợp lệ (ví dụ: tối thiểu là `-1`), cho phép gửi các số âm không hợp lệ như `-5` hay `-100` lên cơ sở dữ liệu.
- **Tác động (Impact)**: Nếu admin nhập giá trị âm tùy ý, logic kiểm tra giới hạn (ví dụ: `if ($limitStreams !== -1 && $activeSessionsCount >= $limitStreams)`) có thể hoạt động sai lệch, khóa quyền của người dùng ngay lập tức.
- **Giải pháp tối thiểu (Minimal fix)**: Thêm rule validate tùy chỉnh hoặc tối thiểu cụ thể, đảm bảo giá trị chỉ thuộc tập số nguyên dương hoặc bằng `-1` (ví dụ sử dụng rule `min:-1`).

### 3. [Low] Thiếu chặn cận trên cho trường giá tiền của gói cước
- **Loại (Type)**: Validation improvement
- **Vị trí (Location)**: `SubscriptionController.php` (các hàm `storeAdminPackage` và `updateAdminPackage`)
- **Minh chứng (Evidence)**:
  `'price' => 'required|integer|min:0'`
- **Lý do sai/rủi ro (Why wrong/risky)**: Không giới hạn giá trị tối đa của giá tiền, cho phép tạo các gói cước có giá trị cực lớn vượt ngưỡng xử lý của kiểu dữ liệu hoặc hiển thị trên giao diện.
- **Giải pháp tối thiểu (Minimal fix)**: Ràng buộc thêm `'price' => 'required|integer|min:0|max:100000000'` (100 triệu VND hoặc hạn mức thực tế tối đa).

---

## Product/UX/Text/Duplicate Issues
- **Payments Admin Page**: Các thống kê số lượng giao dịch thành công/thất bại và biểu đồ doanh thu cũng đang là các thành phần giả lập (mockup data hoặc hardcoded), chỉ có bảng danh sách giao dịch ở dưới là dữ liệu thực từ backend.
- **Checkout Controller Integration**: Đã xác nhận không có file controller dư thừa nào của `CheckoutController.php`, logic checkout đã được gộp gọn gàng vào `SubscriptionController.php`.

---

## Test Gaps
- Hiện tại hệ thống test suite đã bao phủ đầy đủ tất cả các trường hợp biên quan trọng bao gồm:
  - Gating giới hạn stream, thời lượng live, AI credits, audio analysis.
  - Tạo/Gia hạn/Nâng cấp/Hủy gói cước qua Webhook callback.
  - Chống race condition và xử lý callback thanh toán trùng lặp.
- **Test Gap phát hiện**: Thiếu test xác thực luồng tự động ngắt livestream từ phía background khi vượt quá thời lượng cho phép mà không cần gọi API (hiện tại tính năng này chỉ được kích hoạt thụ động khi người dùng gọi API `show` hoặc `fetchEvents` của Live Session).

---

## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
| `php artisan test --filter Subscription` | Có | 30 tests Passed | Kiểm thử đơn vị và tích hợp của các tính năng Subscription, Database, Gating và Callback hoạt động ổn định và chính xác. | Không chứng minh được giao diện hiển thị không bị lỗi layout (pixel-perfect visual correctness). |

---

## Missed-risk / Limitations
- Đây là báo cáo phân tích tĩnh (static code-path analysis) kết hợp chạy kiểm thử tự động (feature/unit tests).
- Báo cáo này không bao phủ được các lỗi có thể phát sinh trong môi trường production thực tế do độ trễ mạng khi gọi API Runware AI hoặc webhook thật từ ngân hàng VietQR.

---

## Suggested Fix Order
1. **Sửa thẻ Tổng doanh thu trên Admin Payments**: Thay số hardcoded `5.600.000 đ` bằng hàm `sum('amount')` từ danh sách Transaction thành công.
2. **Cập nhật Validation Rules cho `features` gói cước**: Ràng buộc thêm `min:-1` cho `limit_streams` và `ai_credits` để tránh lỗi logic nghiệp vụ.

---

## Decision
Merge with follow-up
