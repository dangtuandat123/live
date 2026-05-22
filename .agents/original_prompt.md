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

- [ ] Không có bug hoặc lỗi hiển thị trắng trang do dữ liệu động bị null/undefined (cần có cơ chế fallback an toàn ở React component khi dữ liệu chưa tải xong).

## 2026-05-22T14:38:26Z

Thực hiện kiểm tra (audit) chuyên sâu và căn chỉnh sự đồng bộ logic giữa giao diện phân tích phiên live (React/Inertia) và service backend (Laravel Controller, AI Job, và Python TikTok Live service) để phát hiện và đề xuất phương án khắc phục các lỗi mâu thuẫn dữ liệu, bất đồng bộ cache, và nhãn hiển thị không nhất quán.

Working directory: `d:\Workspace\livestream`
Integrity mode: development

## Requirements

### R1. Phân tích & Khắc phục lỗi Phễu chuyển đổi (Conversion Funnel Distortion)
- **Vấn đề**: Backend giới hạn danh sách khách hàng tiềm năng trả về là 50 (`limit(50)`). Frontend sử dụng `potentialCustomers.length` làm giá trị cho bước "Có SĐT/ĐC" trong phễu, trong khi bước tiếp theo "Chốt đơn" dùng `stats.leads_count` (không bị giới hạn). Khi số lượng chốt đơn thực tế vượt quá 50, bước đáy phễu sẽ lớn hơn bước trên nó (ví dụ: Có SĐT/ĐC = 50, Chốt đơn = 60), tạo ra tỉ lệ chuyển đổi > 100% (120%) và làm vỡ thiết kế hình học của phễu.
- **Yêu cầu**: Cung cấp tổng số lượng khách hàng tiềm năng chính xác từ backend (ví dụ: trả về trường `potential_customers_count` riêng biệt) để vẽ phễu chuẩn xác mà không phụ thuộc vào độ dài mảng danh sách chi tiết (vốn bị giới hạn để tối ưu hóa hiệu năng hiển thị).

### R2. Chuẩn hóa Nhãn hiển thị (Labeling Alignment)
- **Vấn đề**: 
  - Tại thanh thống kê nhanh dưới video: `stats.leads_count` được hiển thị dưới nhãn **"KH tiềm năng"**.
  - Tại Phễu chuyển đổi: Stage 3 dùng `potentialCustomers.length` nhãn **"Có SĐT/ĐC"**, Stage 4 dùng `stats.leads_count` nhãn **"Chốt đơn"**.
  - Tại tab bên phải: Nhãn tab hiển thị **"KH tiềm năng"** nhưng danh sách lại chứa cả khách hàng chốt đơn và khách hàng chỉ để lại SĐT.
- **Yêu cầu**: Đảm bảo sự thống nhất tuyệt đối về mặt ngữ nghĩa của các thuật ngữ trên giao diện để tránh gây nhầm lẫn cho chủ shop. Nhãn phải phản ánh đúng bản chất dữ liệu (ví dụ: "Số lượng chốt đơn", "Số lượng có SĐT/Địa chỉ").

### R3. Khắc phục lỗi Lưu bộ nhớ đệm (Cache Invalidation Bug)
- **Vấn đề**: Các hàm `show` và `fetchEvents` của `LiveSessionController` sử dụng cache với thời gian sống (TTL) 5 giây (khi live) hoặc 3600 giây (khi kết thúc) cho các dữ liệu `potentialCustomers`, `topProducts`, `topQuestions`, và `statsHistory`. Tuy nhiên:
  - Khi người dùng cập nhật thông tin đơn hàng qua API `updateEvent` (ghim comment, đánh dấu chốt đơn, sửa số lượng/ghi chú/trạng thái), cache không hề bị xóa.
  - Khi tiến trình AI `AnalyzeCommentsJob` hoàn thành phân tích các bình luận mới trong nền, cache cũng không được xóa.
  - Hậu quả: Dữ liệu hiển thị của người dùng bị ghi đè bởi dữ liệu cũ từ cache trong lần polling tiếp theo (đặc biệt nghiêm trọng khi phiên live đã kết thúc, dữ liệu bị đơ suốt 1 tiếng).
- **Yêu cầu**: Bổ sung cơ chế tự động xóa các khóa cache liên quan đến phiên live (`potential_customers`, `top_products`, `top_questions`, `stats_history`) ngay khi có cập nhật từ `updateEvent` hoặc khi `AnalyzeCommentsJob` hoàn thành lưu kết quả phân tích.

### R4. Loại bỏ các Thành phần trùng lặp và Mã thừa (Redundancy & Clean Code)
- **Vấn đề**:
  - Biểu đồ "Phân tích cảm sentiment" (cột trái) và biểu đồ tròn "Phân bổ cảm xúc" (Tab Thống kê) hiển thị thông tin trùng lặp, lãng phí diện tích giao diện.
  - Thẻ "Từ khóa được nhắc nhiều" hiển thị danh sách câu hỏi nổi bật (`topQuestions`) và sản phẩm nổi bật (`topProducts`) ghép lại, không thực sự hiển thị "Từ khóa" (keywords) thực tế.
  - Prop `keywords: string[]` (nạp từ quan hệ `keywords` của `LiveSession`) được truyền xuống client từ backend nhưng hoàn toàn không được sử dụng ở React.
- **Yêu cầu**: Đánh giá và dọn dẹp các thành phần trùng lặp, tối ưu hóa bố cục hiển thị và loại bỏ các prop/truy vấn DB thừa.

### R5. Đồng bộ hóa logic trích xuất Số điện thoại
- **Vấn đề**: 
  - Tại `fetchAndStoreEvents` (khi lưu comment mới), hệ thống dùng Regex `0\d{9,10}` để tự động xác định `has_phone`.
  - Tại `AnalyzeCommentsJob` (khi AI phân tích), trường `has_phone` của AI trả về sẽ ghi đè trực tiếp lên giá trị DB. Nếu AI nhận diện sai hoặc bỏ sót số điện thoại, giá trị `has_phone` đúng đắn được tính từ Regex ban đầu sẽ bị mất.
- **Yêu cầu**: Đảm bảo an toàn dữ liệu, ưu tiên tính chính xác tuyệt đối của Regex. Chỉ ghi đè hoặc bổ sung nếu Regex chưa phát hiện ra nhưng AI phát hiện ra, không để AI ghi đè `false` lên kết quả `true` của Regex.

## Acceptance Criteria

### Tính chính xác của Phễu và Nhãn
- [ ] Không xuất hiện tỉ lệ chuyển đổi > 100% trong phễu chuyển đổi trong bất kỳ trường hợp nào.
- [ ] Nhãn thống kê, nhãn phễu và tên tab thống nhất về mặt ngữ nghĩa (ví dụ: Leads chốt đơn phải thống nhất gọi là "Chốt đơn" hoặc "Số đơn hàng", danh sách khách hàng tiềm năng bao gồm cả khách để lại SĐT phải được mô tả rõ ràng).

### Trải nghiệm cập nhật thời gian thực & Cache
- [ ] Khi thực hiện lưu cập nhật đơn hàng ở tab "KH tiềm năng", dữ liệu thay đổi lập tức được bảo toàn ở lần polling tiếp theo mà không bị giật/hoàn tác về trạng thái cũ.
- [ ] Khi phiên live đã kết thúc (`ended`), mọi thao tác chỉnh sửa ghi chú hay trạng thái đơn hàng của khách hàng được cập nhật và hiển thị lập tức trên giao diện.

### Tối ưu hóa giao diện & Mã nguồn
- [ ] Loại bỏ hoặc thiết kế lại biểu đồ cảm xúc trùng lặp để giao diện gọn gàng, trực quan hơn.
- [ ] Loại bỏ trường/prop `keywords` thừa không sử dụng ra khỏi Inertia response và React component.
- [ ] Lệnh build frontend `npm run build` chạy thành công 100% không cảnh báo/lỗi TypeScript.
- [ ] Toàn bộ các test suite backend (`php artisan test`) chạy thành công.

## 2026-05-22T08:28:55Z

Quét, phân tích và tìm tất cả các lỗi (functional, UI/UX, logic, đồng bộ, bảo mật, hiệu năng) có trong trang quản lý phiên live `http://localhost:8000/lives/3` và các controller, jobs, components liên quan.

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Phân tích tĩnh và logic đồng bộ UI/UX (Lives/Show.tsx)
Kiểm tra chi tiết file [Show.tsx](file:///d:/Workspace/livestream/backend/resources/js/Pages/Lives/Show.tsx) xem có:
- Lỗi hardcode dữ liệu hoặc văn bản hiển thị.
- Sự mâu thuẫn giữa các component hiển thị (ví dụ: hiển thị trạng thái kết nối, đếm comment, sản phẩm, khách hàng tiềm năng).
- Các lỗi về component lifecycle, render thừa, state, props không đồng bộ khi polling dữ liệu mỗi 5 giây (`lives.fetch-events`).

### R2. Đối chiếu logic Backend và APIs liên quan
Kiểm tra sự đồng bộ và xử lý lỗi giữa [LiveSessionController.php](file:///d:/Workspace/livestream/backend/app/Http/Controllers/LiveSessionController.php) (các method `show`, `fetchEvents`, `stop`, `updateEvent`) với frontend:
- Dữ liệu trả về từ API `fetchEvents` có khớp chính xác cấu trúc frontend mong đợi không?
- Logic tính toán cảm xúc (sentiment positive/neutral/negative) có đồng bộ và nhất quán giữa DB, Job (`AnalyzeCommentsJob`) và frontend không?
- Các API endpoints cập nhật sự kiện (`/api/live-events/{id}`) có được xử lý phân quyền và validate an toàn không?

### R3. Kiểm tra logic nền (Jobs/Services)
Kiểm tra [AnalyzeCommentsJob.php](file:///d:/Workspace/livestream/backend/app/Jobs/AnalyzeCommentsJob.php) và [TikTokService.php](file:///d:/Workspace/livestream/backend/app/Services/TikTokService.php) liên quan đến live session 3 xem có:
- Tránh race condition hoặc lọt trùng lặp sự kiện comment/order.
- Logic auto-healing hoạt động ổn định khi Python service bị mất kết nối hoặc restart không.

## Verification Plan

### Automated Verification
- Chạy PHPUnit test suite hiện có của dự án để đảm bảo không làm hỏng logic nền:
  `php artisan test`
- Chạy lệnh biên dịch frontend để phát hiện các lỗi type hoặc build của `Show.tsx`:
  `npm run build`

### Static Code Review & Mapping
- Thực hiện trace luồng dữ liệu từ database -> controller -> view/inertia response -> client-side state update để chỉ ra chính xác các dòng code có lỗi hoặc nguy cơ lỗi cao.

## Acceptance Criteria

### Báo cáo kiểm thử chi tiết
- [ ] Danh sách lỗi được phân loại theo mức độ nghiêm trọng (Critical, High, Medium, Low) cùng với đường dẫn file cụ thể.
- [ ] Chỉ ra các lỗi không khớp kiểu dữ liệu (type mismatch) giữa backend controller và frontend React component PageProps.
- [ ] Đề xuất phương án sửa chữa tối thiểu và an toàn nhất cho từng lỗi phát hiện được mà không làm ảnh hưởng đến các phiên live khác.

## 2026-05-22T08:37:16Z

Chuyển đổi tính năng "Từ khóa được nhắc nhiều" (Top Keywords) trên trang phân tích phiên live từ cơ chế cấu hình thủ công (chủ shop nhập từ khóa thô) sang cơ chế AI tự động phân tích và trích xuất từ khóa nổi bật (AI Auto-Discovery Keywords).

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Loại bỏ cấu hình Từ khóa thủ công
- **Frontend**: Xóa bỏ hoàn toàn phần giao diện "Từ khóa theo dõi" (nhập từ khóa rồi nhấn Enter/Thêm) trong trang chuẩn bị phiên live (Setup.tsx).
- **Backend**: Loại bỏ phần validate và lưu trữ từ khóa thủ công trong controller khi tạo phiên livestream mới (LiveSessionController).

### R2. Tích hợp AI Auto-Discovery Keywords
- **Prompt AI**: Cập nhật Prompt hệ thống của tiến trình AI phân tích bình luận (AnalyzeCommentsJob.php) để AI tự động trích xuất các từ khóa nổi bật trong batch bình luận.
  - Yêu cầu AI trả về thêm trường `extracted_keywords` (mảng gồm tối đa 5 từ khóa nổi bật nhất trong batch bình luận hiện tại, viết thường, ngắn gọn 1-3 từ, tập trung vào sản phẩm, giá cả, chất lượng, thắc mắc chung).
- **Lưu trữ từ khóa**: Khi nhận kết quả phân tích từ AI, backend sẽ tự động chuẩn hóa và lưu các từ khóa này vào bảng `live_session_keywords` liên kết với phiên live (nếu chưa tồn tại, và giới hạn số lượng từ khóa tối đa khoảng 30 từ mỗi phiên live để tránh phình to dữ liệu).

### R3. Thống kê và hiển thị Từ khóa thời gian thực
- **Backend đếm tần suất**: Giữ nguyên logic đếm tần suất thực tế của các từ khóa này bằng câu lệnh SQL `LIKE` thô trong `LiveSessionController::getTopKeywords` để đảm bảo độ chính xác số lượng đếm, cập nhật real-time và không phụ thuộc vào khả năng đếm số của AI.
- **Frontend hiển thị**: Hiển thị danh sách các từ khóa do AI phát hiện và đếm được trên trang Lives Show (Show.tsx) một cách tự nhiên.

## Acceptance Criteria

### Giao diện chuẩn bị (Setup)
- [ ] Không còn ô nhập "Từ khóa theo dõi" hay danh sách badge từ khóa thủ công trên trang Setup.

### Phân tích AI & Thống kê
- [ ] Khi chạy phiên live và có comment mới gửi lên, AI tự động trích xuất các từ khóa nổi bật (ví dụ: "chất liệu", "mã hàng", "ship", "giá") và lưu vào cơ sở dữ liệu.
- [ ] Giao diện phân tích phiên live hiển thị đúng các từ khóa do AI tự động phát hiện kèm theo số lần xuấtian tương ứng thực tế trong database.
- [ ] Lệnh build frontend `npm run build` chạy thành công 100% không có lỗi.
- [ ] Toàn bộ các test suite backend (`php artisan test`) chạy thành công.

## 2026-05-22T08:58:23Z

Thực hiện một đợt đánh giá chuyên sâu (Evidence-driven Static/Code-path Audit) toàn diện đối với toàn bộ hệ thống AI (bình luận, audio, trích xuất từ khóa, credit AI) trong codebase sử dụng workflow `/evidence-deep-audit-v3-12k` để phát hiện lỗi, rủi ro bảo mật, bất đồng bộ và lỗ hổng logic.

Working directory: d:\Workspace\livestream
Integrity mode: development

## Requirements

### R1. Áp dụng quy trình Đánh giá chuyên sâu (Pass 0 đến Pass 18)
- Sử dụng đúng cấu trúc và nội dung hướng dẫn của workflow `/evidence-deep-audit-v3-12k` để kiểm tra toàn bộ hệ thống AI.
- Tạo báo cáo audit chi tiết lưu tại file d:\Workspace\livestream\evidence_deep_audit_report_ai.md.

### R2. Đánh giá chất lượng Prompt & Khả năng xử lý kết quả của AI
- Kiểm tra system prompt và các tham số gọi AI trong AnalyzeCommentsJob.php và CommentAnalyzer.php.
- Kiểm tra tính an toàn của định dạng JSON phản hồi từ AI, cách hệ thống xử lý khi AI trả về sai định dạng, thiếu trường, hoặc chứa mã độc/dữ liệu rác.
- Đánh giá sự an toàn của logic ghi đè dữ liệu AI lên dữ liệu thực tế (ví dụ: trường has_phone, intent_tag, question_tag).

### R3. Kiểm tra Invariants & Abuse Cases đối với hệ thống AI
- Kiểm tra cơ chế giới hạn tín dụng AI (ai_credits và used_ai_credits) của gói đăng ký thành viên (Subscription).
- Rà soát nguy cơ bypass giới hạn credit hoặc spam gửi request AI liên tục gây cạn kiệt tài nguyên (rate limit, race condition khi dispatch job phân tích comment).
- Đánh giá độ tin cậy của tiến trình thu thập audio 3s từ livestream qua Python FFmpeg để gửi lên AI đa phương thức (multimodal analysis).

### R4. Rà soát Cache & Đồng bộ Frontend
- Kiểm tra cơ chế xóa cache (Cache::forget) sau khi AI Job hoàn thành để đảm bảo dữ liệu phân tích mới nhất được đẩy lên giao diện kịp thời.
- Rà soát sự đồng bộ kiểu dữ liệu (Types/Contracts) giữa API phản hồi AI và các React components nhận dữ liệu trên frontend.

## Acceptance Criteria

### Báo cáo Audit (evidence_deep_audit_report_ai.md)
- [ ] Báo cáo được tạo đầy đủ tất cả các Pass từ Pass 0 đến Pass 18 theo đúng cấu trúc chuẩn của `/evidence-deep-audit-v3-12k`.
- [ ] Phải liệt kê đầy đủ các Scope, Stack, Coverage Ledger, Expected Behavior Contract, Static UX Matrix, Action Matrix, Backend Abuse Matrix, Invariant Matrix, State/Race Matrix, Security Matrix, và Test/Mutation Gaps.
- [ ] Mỗi Finding được liệt kê phải có đầy đủ: Loại (Type), Mức độ (Severity), Vị trí (Location), Bằng chứng (Evidence), Đối chiếu chéo (Cross-check), Kịch bản lỗi (Scenario), Đề xuất sửa đổi tối thiểu (Minimal fix).
- [ ] Báo cáo phải kết luận bằng một trong bốn quyết định chuẩn (ví dụ: Block merge / Merge with follow-up / Safe within audited scope / Fix before merge).

### Chất lượng mã nguồn
- [ ] Không làm hỏng hoặc thay đổi bất kỳ code logic AI nào đang hoạt động tốt (trừ khi có sự đồng ý hoặc phát hiện lỗi nghiêm trọng cần sửa).
- [ ] Toàn bộ các test suite hiện có của Laravel (php artisan test) vẫn phải chạy qua thành công.

## 2026-05-22T09:54:48Z

Cải thiện chất lượng phân tích của AI Insights (Tổng kết) và Cảnh báo AI trên Dashboard Livestream, giúp thông tin thực tế, mang lại giá trị vận hành cao hơn, đồng thời tối ưu hóa cơ chế tự động phân tích định kỳ.

Working directory: d:/Workspace/livestream/backend
Integrity mode: development

## Requirements

### R1. Phân tích AI Insights thực tế (Tổng kết & Cảnh báo sinh từ LLM)
- **Database**: Thêm cột `ai_insights` (text, nullable) và `ai_alerts` (json, nullable) vào bảng `live_sessions` để lưu trữ dữ liệu phân tích chi tiết.
- **Agent AI**: Thiết kế một agent AI mới là `App\Ai\Agents\LiveSessionAnalyzer.php` (hoặc cấu trúc tương đương) kế thừa cấu trúc Laravel Ai để phân tích tổng thể phiên live. Agent này sẽ đọc:
  - Danh sách 150 bình luận gần đây nhất của phiên live.
  - Các thống kê thô hiện tại từ `live_stats` (tổng view, comment, likes, follows, chốt đơn, cảm xúc tích cực/tiêu cực/trung lập thô).
  - Bộ nhớ ngữ cảnh cũ (`ai_context_summary` hoặc `session_note`).
  - Danh sách sản phẩm đang bán và từ khóa đang theo dõi.
- **Output của LLM**: Trả về cấu trúc JSON duy nhất:
  - `summary`: Một đoạn tóm tắt sâu sắc (tối đa 400 ký tự) về diễn biến phiên live, thái độ của khách hàng với sản phẩm, các vấn đề nổi cộm.
  - `alerts`: Danh sách các cảnh báo thực tế (tối đa 5 cảnh báo). Mỗi cảnh báo chứa:
    - `type`: `danger` | `warning` | `info` | `success`
    - `title`: Tiêu đề cảnh báo (ví dụ: "Nhu cầu size L tăng cao", "Khiếu nại về ship")
    - `desc`: Mô tả chi tiết vấn đề
    - `action`: Gợi ý hành động chi tiết và thực tế cho streamer (ví dụ: "Streamer nên giải thích rõ chính sách miễn phí vận chuyển cho đơn trên 500k", "Nhắc nhở khách mua size XL thay thế do size L đã hết hàng").

### R2. Tối ưu hóa cơ chế Trigger phân tích định kỳ
- **Tần suất phân tích**: Việc phân tích Live Insights tổng hợp (gọi `LiveSessionAnalyzer`) tiêu tốn nhiều credit hơn nên cần được giới hạn tần suất (throttle). Chỉ tự động kích hoạt phân tích insights khi có bình luận mới chưa phân tích VÀ thời gian kể từ lần phân tích insights gần nhất của phiên live này tối thiểu là **30 giây** (sử dụng Cache để lưu timestamp lần chạy gần nhất `live_session_{id}_last_insight_time`).
- **Nút bấm thủ công**: Tạo thêm một API endpoint (hoặc phương thức trong controller) cho phép streamer chủ động bấm nút "Làm mới Insights" trên UI để kích hoạt phân tích insights ngay lập tức, bỏ qua thời gian chờ 30 giây.

### R3. Cập nhật giao diện Dashboard (Frontend)
- **Hiển thị Insights**: Chỉnh sửa file Show.tsx (d:/Workspace/livestream/backend/resources/js/Pages/Lives/Show.tsx) để render trực tiếp dữ liệu `ai_insights` và `ai_alerts` từ API trả về.
- **Nút bấm manual**: Thêm nút bấm "Cập nhật AI Insights" (với hiệu ứng loading và disabled khi đang xử lý) ở góc panel Tổng kết AI để streamer có thể click yêu cầu phân tích ngay.
- **UX cải tiến**: Hiển thị rõ ràng phần "Gợi ý hành động" (`action`) cho mỗi cảnh báo trong danh sách Cảnh báo AI với màu sắc và icon tương ứng theo `type`.
- **Fallback**: Giữ logic hiển thị thống kê thô cũ làm fallback nếu dữ liệu `ai_insights` chưa được sinh ra.

## Acceptance Criteria

### Backend & Database
- [ ] Có file migration tạo các trường `ai_insights` và `ai_alerts` trong bảng `live_sessions` (hoặc bảng liên quan).
- [ ] Chạy migration thành công (`php artisan migrate`).
- [ ] Agent `LiveSessionAnalyzer` được định nghĩa chính xác, gửi prompt hệ thống chất lượng và bắt buộc đầu ra đúng định dạng JSON cấu trúc.
- [ ] Tần suất tự động phân tích Live Insights được kiểm soát bằng Cache lock/timestamp (tối thiểu 30 giây một lần).
- [ ] API endpoint `lives.fetch-events` trả về đầy đủ `ai_insights` và `ai_alerts` mới nhất của session. Có API route riêng hoặc option để trigger phân tích thủ công.

### Frontend
- [ ] Nút bấm "Cập nhật AI Insights" hoạt động tốt, gửi request trigger phân tích thành công và cập nhật lại UI ngay lập tức.
- [ ] Panel "Tổng kết AI" hiển thị văn bản phân tích thực tế sinh từ AI.
- [ ] Panel "Cảnh báo AI" hiển thị đúng danh sách cảnh báo sinh từ AI, hiển thị rõ ràng tiêu đề, mô tả và hành động gợi ý với style màu tương ứng (`danger` -> đỏ, `warning` -> vàng/amber, `info` -> xanh dương/cyan, `success` -> xanh lá/emerald).
- [ ] Chạy build frontend thành công (`npm run build` hoặc typecheck không lỗi).

### Kiểm thử & Tương thích
- [ ] Các tính năng cũ (phân tích comment đơn lẻ, chốt đơn, ghim bình luận) hoạt động bình thường, không bị ảnh hưởng.
- [ ] Unit/Integration tests chạy pass (`php artisan test`).

## 2026-05-22T13:23:22Z

Đánh giá và tối ưu hóa hệ thống prompt AI của dự án (trong `CommentAnalyzer` và `LiveSessionAnalyzer`). Chuyển đổi system prompt sang tiếng Anh với kỹ thuật Chain-of-Thought (CoT) và cấu trúc XML để tối ưu hóa khả năng suy luận của LLM, đồng thời vẫn đảm bảo kết quả trả về bằng tiếng Việt chuẩn xác và đúng JSON schema.

Working directory: d:/Workspace/livestream/backend
Integrity mode: development

## Requirements

### R1. Tối ưu hóa Prompt cho CommentAnalyzer
- Đọc và phân tích `CommentAnalyzer.php`.
- Chuyển đổi system prompt (`instructions()`) sang tiếng Anh.
- Áp dụng cấu trúc prompt rõ ràng (sử dụng các thẻ XML để phân chia Context, Rules, Reasoning Steps, Output Format).
- Tích hợp kỹ thuật Chain-of-Thought hoặc Few-shot examples để hướng dẫn LLM cách phân tích kỹ các sắc thái của bình luận tiếng Việt (phân biệt câu hỏi bình thường, phàn nàn thật sự, cú pháp chốt đơn).
- Giữ nguyên cấu trúc JSON Schema đầu ra (chỉ chứa tiếng Việt/các enum định nghĩa sẵn).

### R2. Tối ưu hóa Prompt cho LiveSessionAnalyzer
- Đọc và phân tích `LiveSessionAnalyzer.php`.
- Chuyển đổi system prompt (`instructions()`) sang tiếng Anh.
- Cải thiện prompt để bắt AI suy luận đa chiều: phân tích không khí phiên live từ comment, các hành vi spam/bất thường, thái độ người mua dựa trên dữ liệu thống kê, và đưa ra gợi ý vận hành thực tế.
- Sử dụng các thẻ XML để định nghĩa đầu vào và cấu trúc suy luận trước khi tạo JSON.
- Đảm bảo đầu ra khớp với JSON Schema gồm: `summary` (tiếng Việt), `alerts` (tiếng Việt).

### R3. Xác thực & Test
- Tạo các unit test để kiểm tra độ chính xác của LLM khi chạy qua các prompt mới.
- Đảm bảo đầu ra JSON của cả hai analyzer được parse thành công, không bị lỗi cú pháp.
- Đảm bảo hệ thống build/typecheck pass và không có lỗi PHP syntax.

## Acceptance Criteria

### Prompt & Code Quality
- [ ] File `CommentAnalyzer.php` có system prompt được viết lại hoàn toàn bằng tiếng Anh chuyên nghiệp, sử dụng XML tags và CoT/Few-shot.
- [ ] File `LiveSessionAnalyzer.php` có system prompt được viết lại bằng tiếng Anh, tối ưu hóa suy luận vận hành.
- [ ] Cả hai file đều giữ nguyên PHP class structure và JsonSchema định nghĩa cũ, đảm bảo backward compatibility với frontend và database.

### Verification & Tests
- [ ] Chạy thành công các unit test cũ và mới liên quan đến phân tích AI.
- [ ] Toàn bộ PHP syntax kiểm tra không có lỗi.
- [ ] Build dự án thành công (`npm run build` không lỗi).

## 2026-05-22T14:06:52Z

Nghiên cứu và cải tiến trải nghiệm người dùng (UX/UI) liên quan đến các giới hạn gói dịch vụ (Free, Trial, Pro, Enterprise) trên trang chi tiết livestream (Lives/Show.tsx). Triển khai các Dialog tự động cảnh báo khi livestream bị ngắt do quá thời lượng gói cước hoặc hết tín dụng AI, hiển thị trực quan thông tin gói cước ngay trên Dashboard, và tối ưu hóa các tính năng bị khóa (gated features) để thúc đẩy tỷ lệ chuyển đổi.

Working directory: d:/Workspace/livestream
Integrity mode: development

## Requirements

### R1. Tự động hiển thị Dialog nâng cấp khi quá thời lượng (Upgrade Duration Dialog)
- Sửa lỗi đồng bộ dữ liệu ở frontend: Đảm bảo trường `error_message` của session được cập nhật chính xác từ API polling ngay cả khi status của session chuyển thành `ended`.
- Tự động hiển thị Dialog cảnh báo khi phát hiện session bị ngắt do quá giới hạn thời lượng của gói cước (ví dụ: `session.status === 'ended'` và `error_message` chứa thông tin quá thời lượng).
- Dialog cần thiết kế hiện đại, premium bằng Shadcn UI, nêu rõ thời lượng giới hạn của gói hiện tại và cung cấp nút "Nâng cấp gói dịch vụ" dẫn đến trang `/subscription`.

### R2. Tự động hiển thị Dialog hết credits (Upgrade Credits Dialog)
- Khi phát hiện session bị lỗi do hết credits AI (ví dụ: `session.status === 'error'` và `error_message` chứa thông tin hết tín dụng AI).
- Thay thế hoặc bổ sung cho banner lỗi đỏ thô bằng một Dialog thông báo chuyên nghiệp, thân thiện. Giải thích rõ việc phân tích AI đã bị tạm dừng do hết credits và cung cấp các lựa chọn mua thêm/nâng cấp gói.

### R3. Gated Features UI & Upgrade Trigger
- Hiển thị trực quan trạng thái khóa (ví dụ: thêm icon ổ khóa nhỏ hoặc badge Premium) trên các tính năng bị giới hạn bởi gói cước của người dùng:
  - Nút "Xuất CSV" và "Copy tất cả" ở danh sách khách hàng tiềm năng.
  - Tính năng phân tích âm thanh (audio analysis) hoặc các tính năng nâng cao khác (nếu có).
- Khi người dùng click vào các tính năng bị khóa này, thay vì không phản hồi hoặc báo lỗi thô, hệ thống phải kích hoạt Dialog nâng cấp tương ứng để hướng dẫn họ nâng cấp.

### R4. Live Session Subscription Status Banner
- Thêm một khu vực hiển thị trạng thái gói cước (Subscription Status Banner hoặc Progress Bar) nhỏ gọn, tinh tế ngay trên Dashboard livestream (ví dụ: ở đầu trang hoặc gần header của session).
- Hiển thị rõ: tên gói hiện tại (Free, Trial, Pro, Enterprise), thời lượng tối đa cho phép mỗi phiên, và thanh tiến trình thể hiện lượng AI credits đã sử dụng (đã dùng / tổng số credits của gói).

## Acceptance Criteria

### UX/UI & Dialogs Correctness
- [ ] Khi livestream đang chạy mà bị ngắt do quá thời lượng (test với status `ended` và error message chứa "thời lượng tối đa"), một Dialog nâng cấp thời lượng phải tự động bật lên lập tức.
- [ ] Khi livestream bị lỗi do hết AI credits (test với status `error` và error message chứa "tín dụng AI"), một Dialog nâng cấp credits phải tự động bật lên lập tức.
- [ ] Tất cả các Dialog sử dụng `@/components/ui/dialog` (Shadcn/ui) with style hiện đại, có shadow, rounded corners, blur backdrop, logo/icon nổi bật (ví dụ: Sparkles, Clock, Shield) và thông điệp kêu gọi hành động rõ ràng.
- [ ] Các tính năng bị khóa (export CSV, copy leads) có hiển thị icon ổ khóa nhỏ hoặc chỉ số trực quan cho biết đây là tính năng giới hạn. Click vào hiện Dialog nâng cấp.
- [ ] Header hoặc vị trí phù hợp trên Livestream Show page có hiển thị một Banner/Card nhỏ hiển thị: "Gói: [Tên Gói] | AI Credits: [Used]/[Limit] [Progress Bar]". Nếu gói cước hỗ trợ vô hạn AI credits hoặc vô hạn thời lượng, hiển thị nhãn "Vô hạn".

### Code Integrity & System Robustness
- [ ] Không làm phá vỡ logic backend hoặc các test case PHPUnit hiện tại (giữ nguyên logic của `SubscriptionGatingTest.php`).
- [ ] Đảm bảo state `session` được cập nhật đầy đủ các trường `error_message` khi polling.
- [ ] Dialog không được hiển thị lặp đi lặp lại vô tận (sử dụng state hoặc session storage hợp lý để người dùng có thể đóng Dialog nếu muốn xem tiếp dữ liệu cũ của phiên livestream đã kết thúc).

## 2026-05-22T14:32:46Z

Nghiên cứu và nâng cấp toàn diện trải nghiệm người dùng (UX/UI) liên quan đến các giới hạn gói dịch vụ (Free, Trial, Pro, Enterprise) trên ứng dụng Livestream Comment Analysis. Đảm bảo hiển thị trực quan, cảnh báo sớm thông minh và cung cấp đường dẫn nâng cấp mượt mà để tăng tỷ lệ chuyển đổi.

Working directory: d:/Workspace/livestream

## Requirements

### R1. Cảnh báo sớm và thông báo rõ ràng về giới hạn thời lượng (Free 1h / Trial 2h)
- **Cảnh báo sắp hết giờ (Low Time Warning Banner)**: Ở trang chi tiết live (`Show.tsx`), nếu phiên live đang hoạt động (`live` hoặc `connecting`) và sắp đạt giới hạn thời lượng của gói cước (ví dụ: đã chạy được 85% thời lượng tối đa, hoặc còn dưới 10 phút), hiển thị một Banner cảnh báo màu hổ phách (Amber) ở đầu trang: *"Phiên livestream sắp đạt giới hạn thời lượng gói cước (còn [X] phút). Vui lòng nâng cấp gói dịch vụ để không bị gián đoạn."* kèm nút CTA "Nâng cấp".
- **Lưu trữ dữ liệu lịch sử**: Làm rõ trong Dialog thông báo tự động ngắt (`UpgradeDurationDialog`) rằng các bình luận và phân tích đã thực hiện trước đó được lưu trữ an toàn trong cơ sở dữ liệu, streamer có thể xem lại hoặc xuất dữ liệu bất cứ lúc nào.
- **Trạng thái đặc biệt trong danh sách**: Ở trang danh sách phiên live (`Lives/Index.tsx` và `Dashboard.tsx`), nếu phiên live bị dừng do quá giới hạn gói cước, hiển thị Badge trạng thái nổi bật (ví dụ: "Bị ngắt (Hết giờ)" hoặc "Đạt giới hạn") thay vì chỉ hiện "Đã kết thúc" chung chung, giúp người dùng nhận biết rõ ràng.

### R2. Cảnh báo sớm về Tín dụng AI (Low Credits Alert)
- **Cảnh báo sắp hết Credits**: Ở trang chi tiết live (`Show.tsx`) và Sidebar (`AppSidebar.tsx`), khi số credits đã dùng đạt mức >= 90% giới hạn gói (và limit khác -1), hiển thị một Warning Alert Banner ở đầu trang chi tiết: *"Tín dụng AI của bạn sắp hết (còn lại [X] credits). Vui lòng nâng cấp hoặc mua thêm để tiếp tục phân tích bình luận."* kèm nút CTA "Mua thêm / Nâng cấp".
- **Sidebar highlight**: Khi credit dưới 10%, hiển thị Progress bar màu đỏ hoặc cam ở Sidebar để thu hút sự chú ý.

### R3. Hiển thị giới hạn gói cước tại trang Setup Live (`Lives/Setup.tsx`)
- Thêm một Card/Box nhỏ hiển thị thông tin chi tiết về gói cước hiện tại của người dùng ngay trên trang Setup:
  - Tên gói hiện tại (Free, Trial, Pro, Enterprise).
  - Các giới hạn áp dụng: Số luồng live đồng thời (đã dùng / tối đa), Thời lượng tối đa mỗi phiên live, Tín dụng AI còn lại.
  - Trạng thái các tính năng premium: Xuất file CSV (Có/Không), Phân tích âm thanh AI (Có/Không).
  - Tích hợp một nút "Nâng cấp gói dịch vụ" dẫn đến `/subscription` ngay cạnh thông tin này để hướng dẫn nâng cấp trước khi tạo livestream.

### R4. Gating trực quan cho tính năng Phân tích Âm thanh (Audio Analysis UI)
- Trực quan hóa trạng thái của tính năng "Phân tích âm thanh AI (Audio Analysis)" trên trang chi tiết livestream:
  - Hiển thị một Indicator (ví dụ: Icon Micro kèm Trạng thái hoạt động) ở gần Header hoặc panel AI Insights.
  - Nếu gói cước hỗ trợ (`audio_analysis: true`), hiển thị: *"Phân tích âm thanh: Đang hoạt động"* (màu xanh lá/emerald).
  - Nếu gói cước không hỗ trợ (`audio_analysis: false`), hiển thị: *"Phân tích âm thanh: Gói Pro"* kèm icon Ổ khóa. Khi người dùng click vào, mở một Dialog giới thiệu tính năng phân tích âm thanh đa phương thức bằng AI và cung cấp nút "Nâng cấp gói dịch vụ".

## Acceptance Criteria

### UX/UI & Dialogs Correctness
- [ ] Màn hình Setup Live hiển thị rõ ràng Card thông tin gói cước kèm nút nâng cấp `/subscription`. Nếu đạt giới hạn stream chạy đồng thời, nút submit bị khóa và hiển thị hướng dẫn nâng cấp rõ ràng.
- [ ] Xuất hiện banner cảnh báo màu hổ phách trước khi hết thời lượng (khi thời gian chạy đạt >= 85% giới hạn) hoặc sắp hết credits (khi dùng >= 90% giới hạn) trên trang chi tiết livestream.
- [ ] Dialog hết thời lượng gói cước hiển thị thông điệp thân thiện, xác nhận dữ liệu đã được lưu trữ và có nút chuyển hướng nhanh sang `/subscription`.
- [ ] Trang danh sách phiên live hiển thị đúng trạng thái đặc biệt "Bị ngắt do quá giờ" đối với các phiên live bị auto-stop do thời lượng.
- [ ] Hiển thị trực quan trạng thái khóa/mở của tính năng Phân tích âm thanh AI trên Show.tsx. Click vào tính năng bị khóa sẽ hiện Dialog nâng cấp tương ứng.
- [ ] Sidebar Credits tự động đổi màu progress bar sang đỏ khi lượng credits đã dùng đạt >= 90%.

### Code Integrity & Performance
- [ ] Tất cả các test suite của Laravel (`php artisan test`) đều chạy qua 100% thành công.
- [ ] Biên dịch frontend sạch sẽ không có lỗi typecheck hoặc build cảnh báo (`npm run build`).
- [ ] Không làm phá vỡ cơ chế caching dữ liệu phiên live và không gây ra loop polling/request dư thừa ở frontend.

## 2026-05-22T15:13:26Z

# Teamwork Project Prompt — Redesign Audio Analysis to Multi-modal Pipeline

Nghiên cứu, thiết kế và tích hợp hoàn chỉnh tính năng Phân tích âm thanh (Audio Analysis) đa phương thức (Text + Audio + Memory) cho ứng dụng Livestream Comment Analysis. Loại bỏ giao diện giả lập (mockup) hiện tại, cho phép AI trích xuất thông tin chi tiết từ âm thanh (audio cues) của livestream và hiển thị trực quan thông tin này lên giao diện cho streamer.

Working directory: d:/Workspace/livestream
Integrity mode: development

## Requirements

### R1. Lưu trữ và xử lý thông tin âm thanh ở Backend
- **Cập nhật database**: Tạo migration để thêm cột `last_audio_cues` (text, nullable) vào bảng `live_sessions` để lưu trữ thông tin AI nghe thấy/phát hiện từ livestream audio snapshot gần nhất. Cập nhật `$fillable` trong model `LiveSession.php`.
- **Cập nhật prompt AI (`AnalyzeCommentsJob.php`)**:
  - Bổ sung yêu cầu trong system prompt để AI (Gemini multimodal) trích xuất một thuộc tính mới trong phản hồi JSON: `"audio_cues"` (string hoặc null, độ dài tối đa 200 ký tự tiếng Việt).
  - Yêu cầu AI lắng nghe clip âm thanh 3s và đúc kết ngắn gọn những gì người bán đang nói hoặc trạng thái phòng live (ví dụ: *"Người bán đang giới thiệu Son màu đỏ"*, *"Phát hiện minigame đoán số đang chạy"*, *"Người bán đang chào người xem mới"*, vv.).
  - Nếu không có audio hoặc không nghe rõ, AI trả về `null` cho trường này.
- **Lưu trữ dữ liệu**: Trích xuất `"audio_cues"` từ response của AI và cập nhật vào thuộc tính `last_audio_cues` của `LiveSession` trong mỗi chu kỳ chạy job xử lý comment batch.

### R2. Thiết kế và Hiển thị giao diện Phân tích Âm thanh thông minh (`Show.tsx`)
- **Tái thiết kế `AudioAnalysisCard`**:
  - Khi gói cước hỗ trợ (`audio_analysis` hoạt động):
    - Hiển thị badge trạng thái màu xanh lá sáng: `"Đa phương thức (Text + Audio + Memory) hoạt động"`.
    - Hiển thị thông tin thực tế mà AI nghe thấy gần nhất dưới dạng chữ trực quan: *"AI vừa nghe thấy: \"[last_audio_cues]\""*.
    - Nếu `last_audio_cues` đang null, hiển thị trạng thái chờ thân thiện: *"Đang lắng nghe livestream để phân tích ngữ cảnh..."*.
    - Cập nhật thời gian phân tích cuối (ví dụ: *"Cập nhật [X] giây trước"*).
    - Giữ hiệu ứng sóng âm hoạt hình (waveform) chạy nhịp nhàng màu xanh để biểu thị quá trình capture đang chạy.
    - Loại bỏ các thành phần mock rác không hoạt động (như danh sách thiết bị đầu vào ảo, thanh điều chỉnh âm lượng ảo).
  - Khi gói cước không hỗ trợ (`audio_analysis` bị khóa):
    - Giữ nguyên overlay khóa kèm Dialog hướng dẫn nâng cấp gói dịch vụ để đảm bảo trải nghiệm gating rõ ràng.

### R3. Kiểm thử và Độ ổn định
- Cập nhật các test suite liên quan (`AnalyzeCommentsJobTest.php`) để bao phủ việc kiểm tra AI trả về trường `audio_cues`, dữ liệu được lưu đúng vào model và cơ chế fallback khi không có audio vẫn hoạt động bình thường.
- Đảm bảo toàn bộ 109 test hiện tại (`php artisan test`) đều pass sạch sẽ.
- Thực hiện build frontend (`npm run build`) không có cảnh báo hay lỗi kiểu dữ liệu.

## Acceptance Criteria

### Backend & AI Ingestion
- [ ] Migration tạo thành công trường `last_audio_cues` trên bảng `live_sessions`.
- [ ] Job `AnalyzeCommentsJob` truyền audio base64 thành công sang Runware, yêu cầu và nhận thành công trường `"audio_cues"` trong JSON.
- [ ] Lưu thành công giá trị `audio_cues` vào cột `last_audio_cues` của phiên live trong DB.

### Frontend UI Correctness
- [ ] `AudioAnalysisCard` hiển thị chính xác chuỗi `last_audio_cues` từ `session` prop được cập nhật theo thời gian thực (qua polling dữ liệu).
- [ ] Giao diện thẻ được thiết kế sạch đẹp, loại bỏ hoàn toàn selector thiết bị micro và slider volume không có tác dụng thực tế.
- [ ] Hiển thị badge trạng thái hoạt động của hệ thống đa phương thức màu emerald/green sáng.
- [ ] Dialog khóa tính năng cho gói dịch vụ thấp hơn vẫn hoạt động bình thường khi click nâng cấp.

### Tests & Stability
- [ ] Chạy thành công lệnh `php artisan test` và đạt 100% tỷ lệ pass cho tất cả các test.
- [ ] Chạy thành công lệnh build production của frontend mà không bị lỗi TypeScript.
