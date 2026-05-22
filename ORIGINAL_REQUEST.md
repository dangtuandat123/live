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

## Follow-up — 2026-05-22T16:54:48+07:00

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

## Follow-up — 2026-05-22T20:23:22+07:00

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

## Follow-up — 2026-05-22T21:06:52+07:00

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

## Follow-up — 2026-05-22T14:32:46Z

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

## Follow-up — 2026-05-22T15:13:26Z

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
- [ ] Không làm phá vỡ cơ chế caching dữ liệu phiên live và không gây ra loop polling/request dư thừa ở frontend.

