# Original User Request

## Initial Request — 2026-05-22T15:28:55+07:00

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

## Follow-up — 2026-05-22T15:37:16+07:00

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
- [ ] Giao diện phân tích phiên live hiển thị đúng các từ khóa do AI tự động phát hiện kèm theo số lần xuất hiện tương ứng thực tế trong database.
- [ ] Lệnh build frontend `npm run build` chạy thành công 100% không có lỗi.
- [ ] Toàn bộ các test suite backend (`php artisan test`) chạy thành công.

## Follow-up — 2026-05-22T15:58:23+07:00

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
