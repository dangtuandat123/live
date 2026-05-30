# Requirements Document

## Introduction

Tài liệu này mô tả yêu cầu cho việc di chuyển toàn bộ tầng AI của dự án từ service tự viết `RunwareAiService` sang **Laravel AI SDK** chính thức (gọi qua `(new Agent)->prompt(...)` kèm `schema()` cho structured output), sử dụng provider **DeepSeek** (chỉ văn bản — text-only).

Phạm vi bao gồm hai luồng AI hiện có:

1. **Phân tích comment** (Comment Analysis): phân loại từng comment trong phiên TikTok LIVE (sentiment, intent, câu hỏi, sản phẩm, số điện thoại) và sinh dữ liệu phụ trợ cho pipeline (ghi chú phiên, từ khóa tự khám phá).
2. **Phân tích insight phiên** (Session Insights): sinh bản tóm tắt và cảnh báo vận hành cho phiên LIVE.

Thay đổi cốt lõi:

- Gọi AI qua Laravel AI SDK với structured output (`schema()`) thay vì gọi HTTP thủ công và tự parse JSON.
- Chuyển provider sang `deepseek` với model thật (`deepseek-chat`), thay cho model không tồn tại `deepseek-v4-flash`.
- Gỡ `RunwareAiService` khỏi mọi luồng AI.
- **Loại bỏ hoàn toàn tính năng phân tích audio** vì DeepSeek là text-only (đã được người dùng xác nhận).

Toàn bộ hành vi nghiệp vụ hiện tại (chống ảo giác product_tag, đếm lead duy nhất, hạn mức tín dụng AI, bộ nhớ ngữ cảnh giữa các batch, tự khám phá từ khóa, throttle insight, xử lý poison-pill, tự nối tiếp job, vô hiệu hóa cache) phải được giữ nguyên sau khi di chuyển.

## Glossary

- **AI_SDK**: Thư viện `laravel/ai` (v0.7) cung cấp cơ chế Agent và structured output qua `(new Agent)->prompt(...)->schema(...)`.
- **DeepSeek_Provider**: Provider AI tên `deepseek` được khai báo trong `config/ai.php`, dùng driver `deepseek`, khóa `DEEPSEEK_API_KEY`, model lấy từ `DEEPSEEK_MODEL` (mặc định `deepseek-chat`). Chỉ hỗ trợ văn bản.
- **Comment_Analyzer**: Agent (`App\Ai\Agents\CommentAnalyzer`) phân loại một batch comment và trả structured output.
- **Session_Analyzer**: Agent (`App\Ai\Agents\LiveSessionAnalyzer`) sinh bản tóm tắt (summary) và danh sách cảnh báo (alerts) cho phiên LIVE.
- **Analyze_Job**: Job nền `App\Jobs\AnalyzeCommentsJob` điều phối phân tích comment theo batch và kích hoạt phân tích insight.
- **Insights_Endpoint**: Action `refreshInsights()` trong `App\Http\Controllers\LiveSessionController` cho phép làm mới insight thủ công.
- **Runware_Service**: Service cũ `App\Services\RunwareAiService` gọi trực tiếp `api.runware.ai`. Đối tượng cần được gỡ khỏi luồng AI.
- **Comment_Result**: Một phần tử kết quả phân loại comment gồm: `id`, `sentiment`, `intent_tag`, `question_tag`, `product_tag`, `has_phone`.
- **Session_Note**: Chuỗi tóm tắt ngữ cảnh batch hiện tại (tối đa 500 ký tự khi lưu) để cung cấp cho batch kế tiếp; lưu vào cột `ai_context_summary`.
- **Extracted_Keywords**: Danh sách tối đa 5 từ khóa nổi bật do AI trích xuất từ batch comment.
- **AI_Credits**: Hạn mức tín dụng AI của gói thuê bao người dùng (`ai_credits`), với `-1` nghĩa là không giới hạn.
- **Audio_Feature**: Toàn bộ tính năng phân tích âm thanh hiện có, bao gồm: cờ thuê bao `audio_analysis`, việc gọi `TikTokService::getSnapshot()` để lấy audio, trường output `audio_cues`, cột DB `last_audio_cues`, và hiển thị audio trên UI.
- **Lead**: Người xem duy nhất (theo `tiktok_user_id`) có comment được phân loại `intent_tag = "Chốt đơn"`.
- **Structured_Output**: Cơ chế của AI_SDK ràng buộc kết quả trả về theo `schema()` đã khai báo.

## Requirements

### Requirement 1: Phân tích comment qua Laravel AI SDK + DeepSeek

**User Story:** Là kỹ sư hệ thống, tôi muốn luồng phân tích comment chạy qua Laravel AI SDK với provider DeepSeek, để chuẩn hóa cách gọi AI và tận dụng structured output thay vì gọi HTTP thủ công.

#### Acceptance Criteria

1. WHEN Analyze_Job xử lý một batch comment, THE Analyze_Job SHALL gọi Comment_Analyzer thông qua AI_SDK bằng cú pháp `(new Agent)->prompt(...)` kèm `schema()`.
2. WHEN Analyze_Job gọi AI để phân tích comment, THE Analyze_Job SHALL sử dụng DeepSeek_Provider làm provider.
3. THE Comment_Analyzer SHALL khai báo provider là `deepseek` và model được lấy từ cấu hình `DeepSeek_Provider` thay vì giá trị `deepseek-v4-flash`.
4. WHEN AI_SDK trả về kết quả phân tích comment, THE Analyze_Job SHALL nhận dữ liệu dưới dạng Structured_Output theo `schema()` của Comment_Analyzer.
5. THE Analyze_Job SHALL NOT gọi Runware_Service để phân tích comment.

### Requirement 2: Schema và các trường đầu ra bắt buộc của phân tích comment

**User Story:** Là chủ shop, tôi muốn mỗi comment vẫn được phân loại đầy đủ và pipeline vẫn nhận được ghi chú phiên cùng từ khóa, để các tính năng phân tích và tự khám phá từ khóa tiếp tục hoạt động.

#### Acceptance Criteria

1. WHEN Comment_Analyzer phân tích một batch comment, THE Comment_Analyzer SHALL trả về một danh sách Comment_Result, mỗi phần tử gồm `id`, `sentiment`, `intent_tag`, `question_tag`, `product_tag`, và `has_phone`.
2. THE Comment_Analyzer SHALL giới hạn `sentiment` trong tập giá trị `positive`, `neutral`, `negative`.
3. THE Comment_Analyzer SHALL giới hạn `intent_tag` trong tập giá trị `Chốt đơn`, `Hỏi thông tin`, `Phản hồi SP`, `Yêu cầu hỗ trợ`, hoặc giá trị rỗng (null).
4. THE Comment_Analyzer SHALL giới hạn `question_tag` trong tập 17 nhãn câu hỏi tiếng Việt đã định nghĩa hiện tại, hoặc giá trị rỗng (null).
5. WHEN Comment_Analyzer phân tích một batch comment, THE Comment_Analyzer SHALL trả về Session_Note mô tả ngữ cảnh phiên hiện tại.
6. WHEN Comment_Analyzer phân tích một batch comment, THE Comment_Analyzer SHALL trả về Extracted_Keywords gồm tối đa 5 từ khóa ở dạng chữ thường.
7. THE Comment_Analyzer SHALL NOT trả về trường `audio_cues` trong kết quả.

### Requirement 3: Phân tích insight phiên qua Laravel AI SDK + DeepSeek

**User Story:** Là người vận hành phiên LIVE, tôi muốn bản tóm tắt và cảnh báo phiên được sinh qua Laravel AI SDK với DeepSeek và structured output, để insight có cấu trúc ổn định và đáng tin cậy.

#### Acceptance Criteria

1. WHEN Analyze_Job kích hoạt phân tích insight, THE Analyze_Job SHALL gọi Session_Analyzer thông qua AI_SDK bằng `(new Agent)->prompt(...)` kèm `schema()`.
2. WHEN Insights_Endpoint nhận yêu cầu làm mới insight hợp lệ, THE Insights_Endpoint SHALL gọi Session_Analyzer thông qua AI_SDK bằng `(new Agent)->prompt(...)` kèm `schema()`.
3. THE Session_Analyzer SHALL khai báo provider là `deepseek` và model được lấy từ cấu hình `DeepSeek_Provider` thay vì giá trị `deepseek-v4-flash`.
4. WHEN Session_Analyzer hoàn tất phân tích, THE Session_Analyzer SHALL trả về Structured_Output gồm `summary` (chuỗi) và `alerts` (danh sách), mỗi alert gồm `type`, `title`, `desc`, `action`.
5. WHERE kết quả insight hợp lệ được trả về, THE Analyze_Job SHALL lưu `summary` vào trường `ai_insights` và `alerts` vào trường `ai_alerts` của phiên.
6. WHERE kết quả insight hợp lệ được trả về, THE Insights_Endpoint SHALL lưu `summary` vào trường `ai_insights` và `alerts` vào trường `ai_alerts` của phiên.
7. THE Analyze_Job SHALL NOT gọi Runware_Service để phân tích insight.
8. THE Insights_Endpoint SHALL NOT gọi Runware_Service để phân tích insight.

### Requirement 4: Cấu hình model DeepSeek thật

**User Story:** Là quản trị viên hệ thống, tôi muốn cấu hình AI trỏ tới một model DeepSeek có thật, để các lời gọi AI không thất bại vì tên model không tồn tại.

#### Acceptance Criteria

1. THE DeepSeek_Provider SHALL lấy tên model từ biến môi trường `DEEPSEEK_MODEL` với giá trị mặc định `deepseek-chat`.
2. THE DeepSeek_Provider SHALL lấy khóa xác thực từ biến môi trường `DEEPSEEK_API_KEY`.
3. THE Comment_Analyzer SHALL NOT tham chiếu tên model `deepseek-v4-flash`.
4. THE Session_Analyzer SHALL NOT tham chiếu tên model `deepseek-v4-flash`.
5. THE DeepSeek_Provider SHALL được đặt làm provider mặc định (`default`) trong cấu hình AI.

### Requirement 5: Bảo toàn nghiệp vụ phân loại và lưu kết quả comment

**User Story:** Là chủ shop, tôi muốn việc kiểm tra hợp lệ và lưu kết quả phân loại comment giữ nguyên như trước, để chất lượng dữ liệu và độ tin cậy của lead không bị ảnh hưởng sau khi di chuyển.

#### Acceptance Criteria

1. WHEN Analyze_Job nhận một Comment_Result, THE Analyze_Job SHALL kiểm tra hợp lệ `sentiment`, `intent_tag`, `question_tag`, `product_tag`, `has_phone` trước khi ghi vào cơ sở dữ liệu.
2. IF `product_tag` do AI trả về không khớp với danh sách tên sản phẩm đã đăng ký của phiên, THEN THE Analyze_Job SHALL đặt `product_tag` về giá trị rỗng (null).
3. WHERE một comment đã có `has_phone = true` trong cơ sở dữ liệu, THE Analyze_Job SHALL giữ `has_phone = true` bất kể giá trị AI trả về.
4. WHEN một comment trong batch không xuất hiện trong kết quả AI, THE Analyze_Job SHALL đánh dấu comment đó là đã xử lý với `sentiment = neutral`.
5. WHEN Analyze_Job lưu kết quả của batch, THE Analyze_Job SHALL cập nhật các comment trong một giao dịch cơ sở dữ liệu (database transaction).

### Requirement 6: Đếm lead duy nhất và cập nhật tín dụng AI

**User Story:** Là chủ shop, tôi muốn số lead và tín dụng AI vẫn được tính chính xác, để báo cáo và hạn mức gói dịch vụ không bị sai lệch.

#### Acceptance Criteria

1. WHEN Analyze_Job xử lý các comment có `intent_tag = "Chốt đơn"`, THE Analyze_Job SHALL đếm số Lead mới dựa trên `tiktok_user_id` duy nhất chưa từng được ghi nhận là Lead trong phiên.
2. WHEN Analyze_Job hoàn tất lưu một batch, THE Analyze_Job SHALL tăng `used_ai_credits` của thuê bao đang hoạt động theo số comment đã gửi cho AI.
3. IF AI_Credits của thuê bao không phải `-1` và `used_ai_credits` đã đạt hoặc vượt hạn mức, THEN THE Analyze_Job SHALL dừng xử lý và đặt phiên sang trạng thái `error` với thông báo hết tín dụng AI.
4. IF AI_Credits của thuê bao không phải `-1` và `used_ai_credits` đã đạt hoặc vượt hạn mức, THEN THE Insights_Endpoint SHALL trả về phản hồi lỗi báo hết tín dụng AI và không gọi AI.

### Requirement 7: Bộ nhớ ngữ cảnh và tự khám phá từ khóa

**User Story:** Là chủ shop, tôi muốn AI duy trì ngữ cảnh giữa các batch và tự bổ sung từ khóa theo dõi, để phân tích chính xác hơn theo thời gian thực.

#### Acceptance Criteria

1. WHEN Analyze_Job chuẩn bị phân tích một batch, THE Analyze_Job SHALL nạp Session_Note đã lưu trước đó từ `ai_context_summary` làm ngữ cảnh đầu vào.
2. WHEN Analyze_Job nhận Session_Note từ kết quả AI, THE Analyze_Job SHALL lưu tối đa 500 ký tự đầu của Session_Note vào `ai_context_summary`.
3. WHEN Analyze_Job nhận Extracted_Keywords từ kết quả AI, THE Analyze_Job SHALL thêm các từ khóa mới (chưa tồn tại, đã chuẩn hóa chữ thường) vào danh sách từ khóa của phiên.
4. THE Analyze_Job SHALL giới hạn tổng số từ khóa của phiên ở mức tối đa 30.

### Requirement 8: Throttle, tự nối tiếp job và vô hiệu hóa cache

**User Story:** Là người vận hành, tôi muốn pipeline tiếp tục tự điều phối hiệu quả, để hệ thống không gọi AI quá dày và luôn xử lý hết comment tồn đọng.

#### Acceptance Criteria

1. WHILE còn comment chưa được xử lý AI trong phiên, THE Analyze_Job SHALL điều phối một lần chạy Analyze_Job kế tiếp cho cùng phiên.
2. IF khoảng cách từ lần phân tích insight gần nhất nhỏ hơn 30 giây, THEN THE Analyze_Job SHALL bỏ qua việc chạy phân tích insight tự động trong lần xử lý hiện tại.
3. IF Insights_Endpoint nhận yêu cầu làm mới trong vòng 30 giây kể từ lần làm mới trước, THEN THE Insights_Endpoint SHALL trả về phản hồi lỗi giới hạn tần suất (429) và không gọi AI.
4. WHEN Analyze_Job hoặc Insights_Endpoint cập nhật dữ liệu phiên, THE thành phần tương ứng SHALL vô hiệu hóa (xóa) cache liên quan của phiên.
5. WHILE một lần chạy Analyze_Job cho một phiên đang diễn ra, THE Analyze_Job SHALL ngăn một lần chạy đồng thời khác xử lý cùng phiên đó thông qua khóa (lock).

### Requirement 9: Xử lý lỗi AI và poison-pill

**User Story:** Là người vận hành, tôi muốn lỗi từ provider AI được xử lý an toàn, để hàng đợi không bị nghẽn và phiên không kẹt vĩnh viễn.

#### Acceptance Criteria

1. IF lời gọi AI ném ngoại lệ do lỗi xác thực (auth), THEN THE Analyze_Job SHALL đánh dấu job thất bại (fail) và không thử lại.
2. IF lời gọi AI ném ngoại lệ do giới hạn tần suất (rate limit), timeout, hoặc lỗi kết nối, THEN THE Analyze_Job SHALL cho phép hàng đợi thử lại theo cấu hình `tries` và `backoff` hiện có.
3. IF một lỗi AI không thể tự phục hồi hoặc đã đạt số lần thử tối đa, THEN THE Analyze_Job SHALL đánh dấu các comment trong batch là đã xử lý với `sentiment = neutral` để tránh nghẽn hàng đợi.
4. IF lời gọi AI insight thất bại trong Analyze_Job, THEN THE Analyze_Job SHALL ghi log cảnh báo và tiếp tục phần còn lại của quy trình mà không làm hỏng batch.
5. IF lời gọi AI insight thất bại trong Insights_Endpoint, THEN THE Insights_Endpoint SHALL trả về phản hồi lỗi dịch vụ AI đang bận.

### Requirement 10: Loại bỏ hoàn toàn tính năng phân tích audio

**User Story:** Là chủ sản phẩm, tôi muốn gỡ bỏ tính năng phân tích âm thanh khỏi luồng AI, vì DeepSeek chỉ hỗ trợ văn bản và tính năng audio không còn dùng được.

#### Acceptance Criteria

1. THE Analyze_Job SHALL NOT gọi `TikTokService::getSnapshot()` để lấy audio phục vụ phân tích AI.
2. THE Analyze_Job SHALL NOT đính kèm dữ liệu audio vào lời gọi AI.
3. THE Comment_Analyzer SHALL NOT chứa hướng dẫn liên quan đến audio trong prompt.
4. WHEN Analyze_Job cập nhật phiên sau một batch, THE Analyze_Job SHALL NOT ghi dữ liệu vào trường `last_audio_cues`.
5. THE Insights_Endpoint và các phản hồi dữ liệu phiên SHALL NOT trả về trường `last_audio_cues` ra giao diện.
6. THE giao diện chi tiết phiên LIVE SHALL NOT hiển thị thông tin `last_audio_cues`.
7. WHERE cờ thuê bao `audio_analysis` được tham chiếu trong luồng AI, THE hệ thống SHALL ngừng phụ thuộc vào cờ này để quyết định việc lấy hay phân tích audio.

### Requirement 11: Gỡ RunwareAiService khỏi luồng AI

**User Story:** Là kỹ sư hệ thống, tôi muốn loại bỏ service Runware khỏi luồng AI, để codebase chỉ còn một con đường gọi AI thống nhất qua Laravel AI SDK.

#### Acceptance Criteria

1. THE Analyze_Job SHALL NOT phụ thuộc (inject hoặc tham chiếu) vào Runware_Service.
2. THE Insights_Endpoint SHALL NOT phụ thuộc (inject hoặc tham chiếu) vào Runware_Service.
3. WHEN toàn bộ luồng AI đã chuyển sang AI_SDK, THE codebase SHALL NOT còn bất kỳ lời gọi nào tới Runware_Service trong các đường dẫn thực thi AI.

### Requirement 12: Kiểm thử bằng cơ chế fake của AI SDK

**User Story:** Là kỹ sư, tôi muốn kiểm thử luồng AI bằng cơ chế `Agent::fake()` của SDK, để test ổn định và không phụ thuộc HTTP thật.

#### Acceptance Criteria

1. WHEN bộ kiểm thử xác minh luồng phân tích comment, THE bộ kiểm thử SHALL dùng cơ chế fake của AI_SDK để giả lập phản hồi AI thay vì mock HTTP của Runware.
2. WHEN bộ kiểm thử xác minh luồng phân tích insight, THE bộ kiểm thử SHALL dùng cơ chế fake của AI_SDK để giả lập phản hồi AI.
3. WHEN bộ kiểm thử chạy, THE bộ kiểm thử SHALL xác minh các hành vi nghiệp vụ được bảo toàn gồm: kiểm tra hợp lệ kết quả, đếm lead duy nhất, cập nhật tín dụng AI, lưu Session_Note, và tự khám phá từ khóa.
