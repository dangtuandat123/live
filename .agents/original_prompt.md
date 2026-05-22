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

## 2026-05-22T03:09:23Z

Nghiên cứu sâu sắc giao diện (Frontend React) kết hợp Backend Laravel để tìm ra các điểm sai lệch, thừa thãi, thiếu sót, giá trị hardcode hoặc các chi tiết trải nghiệm không hợp lý, sau đó sửa chữa toàn diện để đồng bộ hóa hệ thống.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. UI Audit & Hardcoded Text Elimination
- Quét toàn bộ mã nguồn của các trang giao diện (trong thư mục `resources/js/Pages` bao gồm cả phân vùng Admin và Client).
- Tìm và sửa đổi toàn bộ các phần text hardcode bất hợp lý (ví dụ: hiển thị gói dịch vụ giả, số credit giả, các thông số cứng, text tiếng Anh/tiếng Việt lẫn lộn).
- Đảm bảo tất cả thông số hiển thị (tên gói dịch vụ, credits, trạng thái tài khoản) được lấy từ profile người dùng (`auth.user`) hoặc các API/props thực tế từ backend truyền xuống.

### R2. UI/UX Flow & Interaction Fixes
- Kiểm tra các nút bấm hoặc hành động tương tác chính trên màn hình (như nút "Đăng ký ngay", nút nâng cấp, form cập nhật) để đảm bảo không có nút bấm "chết" (nhấn vào không có phản ứng).
- Bổ sung hoặc cải thiện các trạng thái phản hồi trực quan: loading khi gửi yêu cầu, toast thông báo lỗi hoặc thành công khi thao tác biểu mẫu.
- Đảm bảo tính nhất quán về giao diện (khoảng cách, màu sắc, font chữ, hover animations) theo đúng chuẩn premium của hệ thống.

### R3. Frontend-Backend Contract Alignment
- Đối chiếu các chức năng phân quyền hiển thị trên Frontend với logic phân quyền Gating trên Backend.
- Đảm bảo các chức năng nâng cao (như export dữ liệu, phân tích âm thanh, giới hạn livestream) hiển thị đúng trạng thái kích hoạt/khóa dựa trên gói dịch vụ thực tế của người dùng.
- Hiển thị thông báo/modal nâng cấp gói phù hợp khi người dùng click vào các tính năng bị khóa.

## Acceptance Criteria

### Interface & Experience Quality
- [ ] Không còn text hardcode không khớp với dữ liệu thực tế (như gói dịch vụ, credits, số luồng tối đa) trên Client và Admin dashboard.
- [ ] Tất cả các hành động tương tác chính (nút submit, chuyển hướng, upgrade) hoạt động đúng chức năng, không bị treo hoặc không phản hồi.
- [ ] Các thông báo toast hiển thị chính xác trạng thái kết quả từ backend trả về.
- [ ] Giao diện build thành công bằng `npm run build` không có lỗi.
- [ ] Backend test suite `php artisan test` vượt qua 100%.


## 2026-05-22T03:16:17Z

Nghiên cứu kỹ chuyên sâu giao diện, đối chiếu tổng thể web và backend để khắc phục hoàn toàn các lỗi sai, thừa, thiếu, các giá trị hardcode và các điểm tương tác UI chưa hợp lý.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Loại bỏ các giá trị Hardcode & Đồng bộ cấu hình động
- **Thông tin ngân hàng thụ hưởng**:
  - Tại Frontend `Subscription/Index.tsx` và Backend `SubscriptionController.php`, thay thế thông tin ngân hàng và chủ tài khoản đang bị hardcode cứng ("MB Bank" và "DANG TUAN DAT") bằng dữ liệu lấy động từ cấu hình hoạt động trong database (`PaymentConfig` model).
  - API checkout `/api/subscription/checkout` cần trả về đầy đủ các thông tin cấu hình thụ hưởng này để Frontend hiển thị động lên Checkout Modal.
- **Doanh thu Admin Dashboard**:
  - Thay thế số doanh thu giả định (5.600.000đ) hiển thị trên card thống kê của trang Payments Admin (`Admin/Payments/Index.tsx`) bằng tổng doanh thu thực tế tính bằng `sum('amount')` từ các transaction có trạng thái `success`.

### R2. Khắc phục Dữ liệu Tạm thời & Tương tác "Chết"
- **Duy trì trạng thái bằng localStorage**:
  - Tích hợp `localStorage` để lưu trữ dữ liệu orders tạm thời, bình luận ghim (`pinnedIds`) và đơn hàng được đánh dấu (`markedOrderIds`) trong trang `Lives/Show.tsx`.
  - Các key lưu trữ cần có hậu tố theo `session.id` của livestream (ví dụ: `orders_{id}`, `pinned_{id}`, `marked_{id}`) để đảm bảo dữ liệu không bị mất khi F5 hoặc reload trang, và không bị chồng chéo giữa các phiên live khác nhau.

### R3. Bổ sung Trạng thái Phản hồi & Toast Notifications
- **Loading Spinners**:
  - Thêm spinner loading khi người dùng click vào nút "Kết thúc phiên phân tích" (`Lives/Show.tsx`) and nút "Xác nhận xóa" livestream (`Lives/Index.tsx`).
- **Toasts thông báo**:
  - Bổ sung thông báo Toast (sử dụng thư viện `sonner` hoặc toast có sẵn) khi: copy tất cả Leads, copy SĐT của khách hàng, lưu đơn hàng tạm thời, kết thúc thành công phiên live.

### R4. Tích hợp Gating kiểm tra ở Frontend (Client-side Gating)
- **Check limits tạo livestream**:
  - Tại trang `Lives/Setup.tsx`, kiểm tra số lượng active streams hiện tại của user so với giới hạn gói dịch vụ (`auth.subscription.features.limit_streams`).
  - If số active streams đã đạt tối đa, vô hiệu hóa nút submit tạo stream và hiển thị cảnh báo yêu cầu nâng cấp gói cước.

### R5. Đồng bộ Backend Validation cho Packages
- **Ràng buộc các trường âm**:
  - Tại `SubscriptionController.php` (phần lưu/cập nhật Packages), cập nhật validation rules cho các trường trong `features` (như `limit_streams` và `ai_credits`) để cho phép giá trị tối thiểu là `-1` (định nghĩa cho vô hạn) thay vì chỉ check `integer`, tránh các lỗi logic khi admin nhập số âm bất kỳ.

## Acceptance Criteria

### UI & Gating Correctness
- [ ] Màn hình thanh toán (Checkout Modal) hiển thị đúng thông tin ngân hàng thụ hưởng lấy từ DB cấu hình (không hardcode).
- [ ] Thẻ tổng doanh thu trên Admin Payments hiển thị chính xác tổng số tiền thực từ các transactions thành công.
- [ ] Các dữ liệu ghim comment, đơn hàng đã tạo và đơn hàng đánh dấu được bảo toàn sau khi reload/F5 trang `Lives/Show.tsx`.
- [ ] Các nút bấm kết thúc livestream và xóa livestream có hiển thị hiệu ứng loading/spinner và vô hiệu hóa click khi đang xử lý.
- [ ] Hiển thị toast thông báo trực quan khi copy thông tin hoặc lưu đơn hàng thành công.
- [ ] Nút tạo livestream ở setup page bị chặn nếu user đã đạt số lượng active streams giới hạn.
- [ ] Các package parameters được validate chặt chẽ trên backend, hỗ trợ giá trị `-1` cho vô hạn.
- [ ] Biên dịch frontend bằng `npm run build` thành công, không gặp lỗi TypeScript.
- [ ] Tất cả các test cases chạy bằng `php artisan test` đều pass.

## 2026-05-22T10:38:58Z

Nghiên cứu kỹ chuyên sâu giao diện, đối chiếu tổng thể web và backend để khắc phục hoàn toàn các lỗi sai, thừa, thiếu, các giá trị hardcode và các điểm tương tác UI/UX chưa hợp lý.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Loại bỏ các giá trị Hardcode & Đồng bộ cấu hình động ở User Menu
- **Menu Nâng cấp Pro**:
  - Tại `nav-user.tsx` (dòng 160), thay thế chữ "Nâng cấp Pro" bị hardcode cứng.
  - Sử dụng hook `usePage().props` để đọc `auth.subscription` động từ Backend.
  - Nếu người dùng đã là gói "Pro" hoặc "Enterprise" và đang hoạt động (`active === true`), đổi nhãn hiển thị thành "Quản lý gói".
  - Cập nhật kiểu dữ liệu TypeScript trong `index.d.ts` để khai báo đầy đủ các thuộc tính của `subscription` (package_name, active, used_ai_credits, features, v.v.).
- **Thông tin ngân hàng thụ hưởng**:
  - Đảm bảo Checkout Modal (`Subscription/Index.tsx`) lấy động các thuộc tính thụ hưởng (`beneficiary_bank`, `beneficiary_account`, `beneficiary_name`) từ response của API checkout `/api/subscription/checkout`, thay vì dùng fallback mặc định là "MB Bank", "11183041", "DANG TUAN DAT".
- **Doanh thu Admin Dashboard**:
  - Thay thế số doanh thu giả định hiển thị trên card thống kê của trang Payments Admin (`Admin/Payments/Index.tsx`) bằng tổng doanh thu thực tế tính bằng `sum('amount')` từ các transaction có trạng thái `success`.

### R2. Tối ưu hóa Khoảng cách (Spacing) & Chiều cao Layout
- **Khoảng cách Header sticky và Content chính**:
  - Tại tất cả các trang chính (bao gồm `Dashboard.tsx`, `Lives/Index.tsx`, `Reports/Index.tsx`, `Products/Index.tsx`, `Settings/Index.tsx`, `Admin/Dashboard.tsx`, `Admin/Users/Index.tsx`, `Admin/Packages/Index.tsx`, `Admin/Payments/Index.tsx`, `Admin/Settings/Index.tsx`), thay thế class padding chính dưới header từ `p-4 pt-4` hoặc `p-4` sang `p-6` (hoặc `p-6 pt-6` nếu cần) để tăng khoảng cách từ 16px lên 24px, mang lại độ thoáng đãng và sang trọng chuẩn UI/UX cao cấp.
- **Tối ưu chiều cao Checkout Modal**:
  - Tại modal thanh toán gói dịch vụ (`Subscription/Index.tsx`), do chiều cao tối đa bị giới hạn ở `max-h-[85vh]` nên rất dễ bị khuất các nút bấm ở Footer trên các màn hình laptop nhỏ (13.3", 14").
  - Tiến hành thu hẹp khoảng cách bằng cách giảm padding của `DialogHeader`, `DialogFooter` và nội dung ở giữa từ `p-5` xuống `p-4` (hoặc `px-5 py-4`).
  - Giảm nhẹ kích thước khung QR code xuống `max-w-[155px]` (vẫn đảm bảo quét tốt nhưng thon gọn hơn) và giảm gap/space-y trong modal từ `gap-6` thành `gap-4`, `space-y-4` thành `space-y-3`.
  - Đảm bảo toàn bộ thông tin thanh toán, QR code và nút footer hiển thị trọn vẹn trên màn hình nhỏ mà không bị che khuất.

### R3. Nút thiếu padding trên Landing Page
- **Landing Page Buttons**:
  - Tại `landing.blade.php`, thẻ `<a>` cho nút "Bắt đầu ngay" (dòng 770) và "Đăng ký ngay" (dòng 814) bị thiếu class padding ngang `px-...` hoặc độ rộng làm chữ bị ép sát lề hai bên nút.
  - Sửa đổi thành `w-full` để nút chiếm toàn bộ chiều ngang card, giúp bố cục dạng cột cân xứng, dễ bấm và chuyên nghiệp tương đương các nút khác của landing page.

### R4. Thiết kế lại các Badge Livestream dịu nhẹ và cao cấp
- **Badge Livestream**:
  - Các badge trạng thái livestream trong `Lives/Index.tsx` (dòng 267, 271) và `Lives/Show.tsx` (dòng 1697) đang dùng màu thô và nổi bật thái quá (`bg-blue-600 hover:bg-blue-700`, `bg-amber-500 hover:bg-amber-600`), lạc quẻ với tông màu xanh lục primary oklch của hệ thống.
  - Thiết kế lại các badge này sang style mờ cao cấp (`bg-.../10 text-... border border-.../20`):
    - `connecting` (Đang kết nối): `bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20` (kèm pulse nhẹ).
    - `disconnected` (Mất kết nối): `bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20` (kèm pulse nhẹ).
    - `ended` (Đã kết thúc) hoặc khác: `bg-muted text-muted-foreground border border-border/50`.
    - `live` (Đang Live): Giữ màu đỏ tươi hoặc tối ưu nhẹ sang `bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20` kết hợp animation ping tròn truyền thống.

### R5. Khắc phục Dữ liệu Tạm thời & Tương tác "Chết"
- **Duy trì trạng thái bằng localStorage**:
  - Tích hợp `localStorage` to lưu trữ dữ liệu orders tạm thời, bình luận ghim (`pinnedIds`) và đơn hàng được đánh dấu (`markedOrderIds`) trong trang `Lives/Show.tsx`.
  - Các key lưu trữ cần có hậu tố theo `session.id` của livestream (ví dụ: `orders_{id}`, `pinned_{id}`, `marked_{id}`) để đảm bảo dữ liệu không bị mất khi F5 hoặc reload trang, và không bị chồng chéo giữa các phiên live khác nhau.
- **Bổ sung Trạng thái Phản hồi & Toast Notifications**:
  - Thêm spinner loading khi người dùng click vào nút "Kết thúc phiên phân tích" (`Lives/Show.tsx`) và nút "Xác nhận xóa" livestream (`Lives/Index.tsx`).
  - Bổ sung thông báo Toast (sử dụng thư viện `sonner` hoặc toast có sẵn) khi: copy tất cả Leads, copy SĐT của khách hàng, lưu đơn hàng tạm thời, kết thúc thành công phiên live.

### R6. Tích hợp Gating kiểm tra ở Frontend (Client-side Gating)
- **Check limits tạo livestream**:
  - Tại trang `Lives/Setup.tsx`, kiểm tra số lượng active streams hiện tại của user so với giới hạn gói dịch vụ (`auth.subscription.features.limit_streams`).
  - Nếu số active streams đã đạt tối đa, vô hiệu hóa nút submit tạo stream và hiển thị cảnh báo yêu cầu nâng cấp gói cước.

### R7. Đồng bộ Backend Validation cho Packages
- **Ràng buộc các trường âm**:
  - Tại `SubscriptionController.php` (phần lưu/cập nhật Packages), cập nhật validation rules cho các trường trong `features` (như `limit_streams` và `ai_credits`) để cho phép giá trị tối thiểu là `-1` (định nghĩa cho vô hạn) thay vì chỉ check `integer`, tránh các lỗi logic khi admin nhập số âm bất kỳ.

## Acceptance Criteria

### UI & Gating Correctness
- [ ] Nhãn ở User Menu đổi động thành "Quản lý gói" nếu user đang có gói Pro hoặc Enterprise còn hoạt động.
- [ ] Khai báo TypeScript cho `UserSubscription` trong `index.d.ts` hoàn thiện, không bị lỗi compile.
- [ ] Nút bấm trên Landing Page rộng toàn bộ card (`w-full`), bố cục cân đối và đẹp mắt.
- [ ] Toàn bộ nội dung Checkout Modal (bao gồm cả mã QR và các nút bấm footer) hiển thị trọn vẹn trên màn hình 13.3"/14" mà không bị khuất nút bấm "Tôi đã chuyển tiền".
- [ ] Các badge trạng thái livestream dùng style mờ cao cấp, không dùng màu thô xanh dương/cam đậm, đồng bộ với thiết kế chung.
- [ ] Khoảng cách content chính với header sticky rộng rãi, thoáng mát (`p-6` thay vì `p-4 pt-4` hay `p-4`).
- [ ] Thẻ tổng doanh thu trên Admin Payments hiển thị chính xác tổng số tiền thực từ các transactions thành công.
- [ ] Các dữ liệu ghim comment, đơn hàng đã tạo và đơn hàng đánh dấu được bảo toàn sau khi reload/F5 trang `Lives/Show.tsx`.
- [ ] Các nút bấm kết thúc livestream và xóa livestream có hiển thị hiệu ứng loading/spinner và vô hiệu hóa click khi đang xử lý.
- [ ] Hiển thị toast thông báo trực quan khi copy thông tin hoặc lưu đơn hàng thành công.
- [ ] Nút tạo livestream ở setup page bị chặn nếu user đã đạt số lượng active streams giới hạn.
- [ ] Các package parameters được validate chặt chẽ trên backend, hỗ trợ giá trị `-1` cho vô hạn.
- [ ] Biên dịch frontend bằng `npm run build` thành công, không gặp lỗi TypeScript.
- [ ] Tất cả các test cases chạy bằng `php artisan test` đều pass.

## 2026-05-22T04:40:06Z

Nghiên cứu kỹ chuyên sâu giao diện, đối chiếu tổng thể web và backend để khắc phục hoàn toàn các lỗi sai, thừa, thiếu, các giá trị hardcode và các điểm tương tác UI/UX chưa hợp lý.

Working directory: d:\Workspace\livestream\backend
Integrity mode: development

## Requirements

### R1. Loại bỏ các giá trị Hardcode & Đồng bộ cấu hình động ở User Menu
- **Menu Nâng cấp Pro**:
  - Tại `nav-user.tsx` (dòng 160), thay thế chữ "Nâng cấp Pro" bị hardcode cứng.
  - Sử dụng hook `usePage().props` để đọc `auth.subscription` động từ Backend.
  - Nếu người dùng đã là gói "Pro" hoặc "Enterprise" và đang hoạt động (`active === true`), đổi nhãn hiển thị thành "Quản lý gói".
  - Cập nhật kiểu dữ liệu TypeScript trong `index.d.ts` để khai báo đầy đủ các thuộc tính của `subscription` (package_name, active, used_ai_credits, features, v.v.).
- **Thông tin ngân hàng thụ hưởng**:
  - Đảm bảo Checkout Modal (`Subscription/Index.tsx`) lấy động các thuộc tính thụ hưởng (`beneficiary_bank`, `beneficiary_account`, `beneficiary_name`) từ response của API checkout `/api/subscription/checkout`, thay vì dùng fallback mặc định là "MB Bank", "11183041", "DANG TUAN DAT".
- **Đồng bộ hóa dữ liệu & Doanh thu Admin Dashboard**:
  - Tại `routes/web.php` (route `admin.dashboard`), thay thế cách tính doanh thu giả định (`$totalUsers * 299000` và `$usersCount * 299000`) bằng doanh thu thực tế từ database:
    - Card KPI "Tổng doanh thu" (thay thế "Doanh thu ước tính"): tính tổng `amount` của các `Transaction` có trạng thái `success`.
    - Biểu đồ tăng trưởng doanh thu (`revenueData`): tính tổng doanh thu thực tế từ các `Transaction` có trạng thái `success` được tạo trong từng tháng tương ứng.
    - Người dùng gần đây (`recentUsers`): lấy thông tin gói cước thực tế của người dùng bằng cách gọi `$u->resolveActiveSubscription()->package->name` (nếu không có thì mặc định là `'Free'`) thay vì giả định chia dư theo ID (`$u->id % 3 === 0 ? 'Pro' : ...`).
  - Thay thế số doanh thu giả định hiển thị trên card thống kê của trang Payments Admin (`Admin/Payments/Index.tsx`) bằng tổng doanh thu thực tế tính bằng `sum('amount')` từ các transaction có trạng thái `success`.
- **Trang quản lý người dùng Admin (Admin Users Page)**:
  - Cập nhật route `admin.users.index` để lấy danh sách người dùng kèm theo gói cước thực tế của họ (sử dụng eager loading `activeSubscription.package` để tránh N+1 query).
  - Cập nhật frontend `Admin/Users/Index.tsx` để hiển thị thêm một cột "Gói" hiển thị badge tương ứng với gói cước thực tế của người dùng (Free, Pro, Business/Enterprise).

### R2. Tối ưu hóa Khoảng cách (Spacing) & Chiều cao Layout
- **Khoảng cách Header sticky và Content chính**:
  - Tại tất cả các trang chính (bao gồm `Dashboard.tsx`, `Lives/Index.tsx`, `Reports/Index.tsx`, `Products/Index.tsx`, `Settings/Index.tsx`, `Admin/Dashboard.tsx`, `Admin/Users/Index.tsx`, `Admin/Packages/Index.tsx`, `Admin/Payments/Index.tsx`, `Admin/Settings/Index.tsx`), thay thế class padding chính dưới header từ `p-4 pt-4` hoặc `p-4` sang `p-6` (hoặc `p-6 pt-6` nếu cần) để tăng khoảng cách từ 16px lên 24px, mang lại độ thoáng đãng và sang trọng chuẩn UI/UX cao cấp.
- **Tối ưu chiều cao Checkout Modal**:
  - Tại modal thanh toán gói dịch vụ (`Subscription/Index.tsx`), do chiều cao tối đa bị giới hạn ở `max-h-[85vh]` nên rất dễ bị khuất các nút bấm ở Footer trên các màn hình laptop nhỏ (13.3", 14").
  - Tiến hành thu hẹp khoảng cách bằng cách giảm padding của `DialogHeader`, `DialogFooter` và nội dung ở giữa từ `p-5` xuống `p-4` (hoặc `px-5 py-4`).
  - Giảm nhẹ kích thước khung QR code xuống `max-w-[155px]` (vẫn đảm bảo quét tốt nhưng thon gọn hơn) và giảm gap/space-y trong modal từ `gap-6` thành `gap-4`, `space-y-4` thành `space-y-3`.
  - Đảm bảo toàn bộ thông tin thanh toán, QR code và nút footer hiển thị trọn vẹn trên màn hình nhỏ mà không cần cuộn sâu.

### R3. Nút thiếu padding trên Landing Page
- **Landing Page Buttons**:
  - Tại `landing.blade.php`, thẻ `<a>` cho nút "Bắt đầu ngay" (dòng 770) và "Đăng ký ngay" (dòng 814) bị thiếu class padding ngang `px-...` hoặc độ rộng làm chữ bị ép sát lề hai bên nút.
  - Sửa đổi thành `w-full` để nút chiếm toàn bộ chiều ngang card, giúp bố cục dạng cột cân xứng, dễ bấm và chuyên nghiệp tương đương các nút khác của landing page.

### R4. Thiết kế lại các Badge Livestream dịu nhẹ và cao cấp
- **Badge Livestream**:
  - Các badge trạng thái livestream trong `Lives/Index.tsx` (dòng 267, 271) và `Lives/Show.tsx` (dòng 1697) đang dùng màu thô và nổi bật thái quá (`bg-blue-600 hover:bg-blue-700`, `bg-amber-500 hover:bg-amber-600`), lạc quẻ với tông màu xanh lục primary oklch của hệ thống.
  - Thiết kế lại các badge này sang style mờ cao cấp (`bg-.../10 text-... border border-.../20`):
    - `connecting` (Đang kết nối): `bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20` (kèm pulse nhẹ).
    - `disconnected` (Mất kết nối): `bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20` (kèm pulse nhẹ).
    - `ended` (Đã kết thúc) hoặc khác: `bg-muted text-muted-foreground border border-border/50`.
    - `live` (Đang Live): Giữ màu đỏ tươi hoặc tối ưu nhẹ sang `bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20` kết hợp animation ping tròn truyền thống.

### R5. Khắc phục Dữ liệu Tạm thời & Tương tác "Chết"
- **Duy trì trạng thái bằng localStorage**:
  - Tích hợp `localStorage` để lưu trữ dữ liệu orders tạm thời, bình luận ghim (`pinnedIds`) và đơn hàng được đánh dấu (`markedOrderIds`) trong trang `Lives/Show.tsx`.
  - Các key lưu trữ cần có hậu tố theo `session.id` của livestream (ví dụ: `orders_{id}`, `pinned_{id}`, `marked_{id}`) để đảm bảo dữ liệu không bị mất khi F5 hoặc reload trang, và không bị chồng chéo giữa các phiên live khác nhau.
- **Bổ sung Trạng thái Phản hồi & Toast Notifications**:
  - Thêm spinner loading khi người dùng click vào nút "Kết thúc phiên phân tích" (`Lives/Show.tsx`) và nút "Xác nhận xóa" livestream (`Lives/Index.tsx`).
  - Bổ sung thông báo Toast (sử dụng thư viện `sonner` hoặc toast có sẵn) khi: copy tất cả Leads, copy SĐT của khách hàng, lưu đơn hàng tạm thời, kết thúc thành công phiên live.

### R6. Tích hợp Gating kiểm tra ở Frontend (Client-side Gating)
- **Check limits tạo livestream**:
  - Tại trang `Lives/Setup.tsx`, kiểm tra số lượng active streams hiện tại của user so với giới hạn gói dịch vụ (`auth.subscription.features.limit_streams`).
  - Nếu số active streams đã đạt tối đa, vô hiệu hóa nút submit tạo stream và hiển thị cảnh báo yêu cầu nâng cấp gói cước.

### R7. Đồng bộ Backend Validation cho Packages
- **Ràng buộc các trường âm**:
  - Tại `SubscriptionController.php` (phần lưu/cập nhật Packages), cập nhật validation rules cho các trường trong `features` (như `limit_streams` và `ai_credits`) để cho phép giá trị tối thiểu là `-1` (định nghĩa cho vô hạn) thay vì chỉ check `integer`, tránh các lỗi logic khi admin nhập số âm bất kỳ.

## Acceptance Criteria

### UI & Gating Correctness
- [ ] Nhãn ở User Menu đổi động thành "Quản lý gói" nếu user đang có gói Pro hoặc Enterprise còn hoạt động.
- [ ] Khai báo TypeScript cho `UserSubscription` trong `index.d.ts` hoàn thiện, không bị lỗi compile.
- [ ] Nút bấm trên Landing Page rộng toàn bộ card (`w-full`), bố cục cân đối và đẹp mắt.
- [ ] Toàn bộ nội dung Checkout Modal (bao gồm cả mã QR và các nút bấm footer) hiển thị trọn vẹn trên màn hình 13.3"/14" mà không bị khuất nút bấm "Tôi đã chuyển tiền".
- [ ] Các badge trạng thái livestream dùng style mờ cao cấp, không dùng màu thô xanh dương/cam đậm, đồng bộ với thiết kế chung.
- [ ] Khoảng cách content chính với header sticky rộng rãi, thoáng mát (`p-6` thay vì `p-4 pt-4` hay `p-4`).
- [ ] Dữ liệu Admin Dashboard (doanh thu, biểu đồ, recent users) được đồng bộ từ dữ liệu thực tế thay vì tính giả lập.
- [ ] Thẻ tổng doanh thu trên Admin Payments hiển thị chính xác tổng số tiền thực từ các transactions thành công.
- [ ] Trang quản lý người dùng Admin (`Admin/Users/Index.tsx`) có cột "Gói" hiển thị đúng gói cước thực tế của người dùng.
- [ ] Các dữ liệu ghim comment, đơn hàng đã tạo và đơn hàng đánh dấu được bảo toàn sau khi reload/F5 trang `Lives/Show.tsx`.
- [ ] Các nút bấm kết thúc livestream và xóa livestream có hiển thị hiệu ứng loading/spinner và vô hiệu hóa click khi đang xử lý.
- [ ] Hiển thị toast thông báo trực quan khi copy thông tin hoặc lưu đơn hàng thành công.
- [ ] Nút tạo livestream ở setup page bị chặn nếu user đã đạt số lượng active streams giới hạn.
- [ ] Các package parameters được validate chặt chẽ trên backend, hỗ trợ giá trị `-1` cho vô hạn.
- [ ] Biên dịch frontend bằng `npm run build` thành công, không gặp lỗi TypeScript.
- [ ] Tất cả các test cases chạy bằng `php artisan test` đều pass.

## 2026-05-22T05:38:33Z

Chuyển trang Cài đặt (`/settings`) của dự án livestream từ giao diện hardcode tĩnh sang giao diện động hoàn toàn, đồng bộ dữ liệu thực tế từ Laravel Backend (thông tin gói cước, số lượng live session đã dùng, trạng thái kết nối tài khoản TikTok).

Working directory: `d:\Workspace\livestream`
Integrity mode: development

## Requirements

### R1. Động hóa Gói đăng ký (Subscription Card)
- Đồng bộ thông tin gói đăng ký từ `auth.subscription` (được chia sẻ qua Inertia Shared Data).
- Cập nhật backend (ví dụ: `HandleInertiaRequests.php` hoặc `SettingsController.php`) để bổ sung thêm giá (`price`) và chu kỳ (`duration_days`) của gói cước hiện tại của người dùng vào props, tránh hardcode giá.
- Liệt kê động các tính năng đi kèm gói cước (số luồng livestream tối đa `limit_streams`, thời lượng live tối đa `max_duration_hours`, AI credits `ai_credits`, v.v.) dựa trên thông tin gói hiện tại.

### R2. Động hóa và Quản lý kết nối TikTok (Platform Connections Card)
- Hiện tại danh sách platform đang bị hardcode. Cần chuyển trạng thái kết nối TikTok thành động.
- Lưu thông tin tài khoản TikTok liên kết vào cột `settings` (JSON) của bảng `users` dưới khóa `tiktok_username` (hoặc cấu trúc tương đương trong `settings`).
- Hỗ trợ API (hoặc dùng các routes/controllers hiện có của settings) để:
  - Kết nối tài khoản TikTok mới: Cho phép người dùng nhập username TikTok (ví dụ qua một Dialog/Modal đơn giản hoặc input trực tiếp) và lưu lại.
  - Ngắt kết nối tài khoản TikTok: Xóa username TikTok đã liên kết.
- Cập nhật giao diện để hiển thị đúng trạng thái "Đã kết nối" + username (nếu có `settings.tiktok_username`) hoặc "Chưa kết nối".

### R3. Thống kê sử dụng động
- Tính toán và hiển thị số lượng livestream đang chạy hiện tại của user (`status` là `connecting` hoặc `live`) so với giới hạn luồng của gói cước (`limit_streams`).
- Tính toán và hiển thị tổng số phiên livestream user đã tạo trong chu kỳ hiện tại (từ `starts_at` của subscription) hoặc trong tháng này.

## Acceptance Criteria

### Gói đăng ký
- [ ] Card "Gói đăng ký" hiển thị đúng tên gói cước đang hoạt động của người dùng (Free, Pro, Enterprise).
- [ ] Hiển thị chính xác giá tiền của gói (ví dụ: "0đ", "299.000đ/tháng"...) và ngày hết hạn.
- [ ] Liệt kê chính xác giới hạn luồng của gói (ví dụ: "1 luồng", "5 luồng", "Không giới hạn luồng").

### Kết nối nền tảng
- [ ] Người dùng có thể nhấn nút "Kết nối" trên dòng TikTok, nhập username TikTok (ví dụ: `@shopabc`), bấm lưu và giao diện cập nhật ngay lập tức thành "Đã kết nối" kèm username.
- [ ] Người dùng có thể nhấn nút "Ngắt kết nối" và xác nhận để xóa tài khoản liên kết, giao diện chuyển về "Chưa kết nối".
- [ ] Thông tin kết nối được lưu trữ bền vững trong cột `settings` của User ở cơ sở dữ liệu.

### Thống kê sử dụng
- [ ] Hiển thị chính xác tỷ lệ số luồng live đang chạy trên tổng số luồng được phép (ví dụ: `0 / 1 luồng`).
- [ ] Hiển thị tổng số phiên livestream đã tạo trong chu kỳ hiện tại hoặc tháng này.

### Tổng thể & Độ tin cậy
- [ ] Frontend build (`npm run build`) thành công 100% không lỗi lầm.
- [ ] Tất cả các test cases (`php artisan test`) đều chạy pass thành công.
- [ ] Thêm unit test kiểm tra chức năng kết nối/ngắt kết nối TikTok.

## 2026-05-22T06:47:57Z

Quét toàn bộ giao diện ứng dụng (React Inertia.js + Laravel) trong thư mục `backend/resources/js/Pages` để phát hiện, loại bỏ dữ liệu tĩnh bị hardcode và chuyển đổi sang dữ liệu động được truyền từ Laravel backend thông qua props hoặc Inertia Shared Data.

Working directory: `d:\Workspace\livestream`
Integrity mode: development

## Requirements

### R1. Rà soát & Phát hiện Hardcode trên toàn bộ các trang giao diện
- Quét toàn bộ các tệp React (`.tsx`, `.ts`) trong thư mục `backend/resources/js/Pages` bao gồm: `Dashboard.tsx`, `Subscription/Index.tsx`, `Reports/Index.tsx`, `Lives/Index.tsx`, `Lives/Setup.tsx`, `Lives/Show.tsx`, và các components dùng chung.
- Tìm kiếm các chuỗi tĩnh hiển thị số liệu, biểu đồ mẫu, thông tin tài khoản, ngân hàng, hoặc cấu hình bị viết cứng (hardcoded) trong mã nguồn frontend.

### R2. Động hóa dữ liệu từ Backend
- Đối với mỗi vị trí phát hiện hardcode, cập nhật Controller Laravel tương ứng để truy vấn dữ liệu thực tế từ database (hoặc cấu hình `.env` / config) và truyền xuống qua Inertia props.
- Trường hợp dữ liệu cần dùng chung cho nhiều trang (như thông tin người dùng, gói cước hiện tại, kết nối tài khoản), sử dụng cơ chế Inertia Shared Data thông qua middleware `HandleInertiaRequests.php`.
- Đảm bảo các thông số nhạy cảm như ngân hàng thụ hưởng, số tài khoản, tên chủ tài khoản được cấu hình động (ví dụ: thông qua bảng `payment_configs` hoặc `.env`) thay vì fallback tĩnh ở client.

### R3. Đảm bảo tính ổn định và hiệu năng
- Giữ nguyên cấu trúc giao diện và trải nghiệm người dùng hiện tại, chỉ thay đổi luồng dữ liệu từ tĩnh sang động.
- Không thay đổi cấu trúc bảng cơ sở dữ liệu hiện tại trừ khi được yêu cầu rõ ràng.
- Đảm bảo không xảy ra lỗi compile/build frontend và backend test suite pass 100%.

## Acceptance Criteria

### Động hóa UI
- [ ] Màn hình Cài đặt (`Settings/Index.tsx`) hiển thị thông tin gói cước, số luồng livestream, giới hạn và thông tin tài khoản TikTok động từ DB.
- [ ] Màn hình Subscription (`Subscription/Index.tsx`) sử dụng thông tin ngân hàng, tài khoản thụ hưởng động từ backend (không có fallback tĩnh mb bank/DANG TUAN DAT cứng ở React code).
- [ ] Màn hình Dashboard (`Dashboard.tsx`) và Reports (`Reports/Index.tsx`) hiển thị đúng dữ liệu thống kê, biểu đồ xu hướng và từ khóa từ dữ liệu live thực tế thay vì dữ liệu mock tĩnh.
- [ ] Các màn hình Lives (`Setup.tsx`, `Show.tsx`, `Index.tsx`) hiển thị thông tin phiên live, danh sách sản phẩm, các số liệu tương tác thực tế từ TikTok Live Event.

### Tổng thể & Kiểm thử
- [ ] Lệnh build frontend `npm run build` chạy thành công không có lỗi TypeScript hay linter.
- [ ] Toàn bộ các test suite backend (`php artisan test`) chạy thành công 100%.
- [ ] Không có bug hoặc lỗi hiển thị trắng trang do dữ liệu động bị null/undefined (cần có cơ chế fallback an toàn ở React component khi dữ liệu chưa tải xong).



