## 2026-05-21T16:35:20Z

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
