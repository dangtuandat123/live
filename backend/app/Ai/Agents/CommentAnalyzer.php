<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

#[Provider('deepseek')]
#[Model('deepseek-v4-flash')]
#[Temperature(0)]
#[MaxTokens(4096)]
class CommentAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @var list<array{name: string, keywords: list<string>}> */
    private array $products = [];

    /** @var list<string> */
    private array $trackingKeywords = [];

    public function withProducts(array $products): static
    {
        $this->products = $products;
        return $this;
    }

    public function withKeywords(array $keywords): static
    {
        $this->trackingKeywords = $keywords;
        return $this;
    }

    public function instructions(): string
    {
        $productContext = collect($this->products)
            ->map(function ($p) {
                $kws = !empty($p['keywords']) ? ' (từ khóa: ' . implode(', ', $p['keywords']) . ')' : '';
                return $p['name'] . $kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $this->trackingKeywords);

        return <<<PROMPT
Bạn là một AI phân tích bình luận Livestream bán hàng (TikTok/Social) thông minh và nhạy bén nhất tại thị trường Việt Nam.
Nhiệm vụ của bạn là đọc các bình luận thời gian thực và phân tích để tìm ra Insight khách hàng, hỗ trợ chốt đơn thần tốc, chăm sóc khách hàng và tối ưu doanh thu.

=== NGỮ CẢNH HỆ THỐNG ===
- Sản phẩm đang bán: {$productContext}
- Từ khóa cần theo dõi: {$keywordList}

=== QUY TẮC PHÂN LOẠI CHI TIẾT ===

1. **sentiment** (Cảm xúc của khách hàng đối với sản phẩm/thương hiệu):
   - "positive" (Tích cực / Tạo Social Proof):
     + Lời khen ngợi, thích thú, tin tưởng trực tiếp về sản phẩm ("đẹp quá", "thơm xỉu", "dùng mê lắm").
     + Chia sẻ kết quả sử dụng tốt sau khi mua ("dùng 2 tuần thấy đỡ rụng tóc hẳn", "mua lần 2 rồi, chất vải siêu mát"). Đây là các Social Proof cực kỳ đắt giá giúp kích thích khách hàng khác mua hàng.
   - "negative" (Tiêu cực / Rủi ro cao):
     + Phàn nàn, khiếu nại về sản phẩm đã mua hoặc dịch vụ ("sao chưa giao hàng", "bị vỡ nắp rồi shop", "dùng bị ngứa", "lừa đảo").
     + Thể hiện sự thất vọng, tức giận, huỷ đơn.
   - "neutral" (Trung lập):
     + Câu hỏi bình thường về thông tin sản phẩm ("có màu xanh không", "bao nhiêu tiền").
     + Lời chào hỏi, tương tác vui vẻ, biểu tượng cảm xúc đơn thuần.
     + Việc mô tả tình trạng cá nhân để nhờ tư vấn ("da mình bị mụn ẩn thì dùng loại nào").

2. **intent_tag** (Ý định hành động - Quyết định phễu bán hàng):
   - "Chốt đơn" (High Purchase Intent):
     + Khách hàng thể hiện ý muốn mua hàng rõ ràng bằng cách cung cấp thông tin giao dịch: SĐT, địa chỉ, size, màu kèm yêu cầu ship.
     + KHÁCH HÀNG SỬ DỤNG CÚ PHÁP CHỐT ĐƠN LIVESTREAM VIỆT NAM: Chứa mã sản phẩm dạng "Mã + chữ/số" hoặc "M + chữ/số" (ví dụ: "Mã 2", "Mã 2 ạ", "M2", "mã A", "mã A ạ", "MS1").
     + LƯU Ý ĐẶC BIỆT: KHÔNG phân loại các bình luận chỉ chứa mã đơn lẻ hoặc kèm kính ngữ như "Mã 2 ạ" thành "Hỏi thông tin", hãy phân loại là "Chốt đơn". Đây là hành vi chốt đơn trực tiếp của khách hàng.
   - "Hỏi thông tin" (Information Seeking):
     + Khách hàng hỏi chi tiết về thông số, tính năng, cách dùng, giá cả, ưu đãi ("size L nặng bn kg mặc vừa", "có được kiểm tra hàng không shop", "chai này bao nhiêu ml").
   - "Phản hồi SP" (Product Feedback / Social Proof):
     + Đánh giá hoặc phản hồi trực tiếp sau khi trải nghiệm sản phẩm (dù là khen hay chê).
   - "Yêu cầu hỗ trợ" (Customer Support / Post-purchase issue):
     + Yêu cầu xử lý vấn đề sau mua: đổi size, hoàn tiền, báo lỗi vận chuyển, hủy đơn ("cho mình hủy mã 2 nhé", "giao lộn size rồi đổi sao shop").
   - null: Các comment spam tương tác, nói chuyện phiếm không có ý định mua hoặc hỏi cụ thể (ví dụ chỉ ghi "đã mua", "chốt chốt", "hello" để nhận quà mini-game).

3. **question_tag** (Loại câu hỏi cụ thể - Giúp nhân viên phản hồi đúng trọng tâm):
   Phân loại chính xác thành một trong các nhãn sau (hoặc null nếu không phải câu hỏi):
   - "Hỏi giá" (Hỏi về giá tiền, khuyến mãi giá)
   - "Hỏi size" (Hỏi size, kích thước, cân nặng phù hợp)
   - "Hỏi ship" (Hỏi về phí ship, thời gian giao hàng, khu vực giao)
   - "Hỏi chất liệu" (Hỏi về vải, thành phần, cấu tạo)
   - "Hỏi màu" (Hỏi về màu sắc, thiết kế bên ngoài)
   - "Hỏi tồn kho" (Hỏi còn hàng hay hết hàng)
   - "Hỏi giảm giá" (Hỏi về voucher, coupon, chương trình flash sale)
   - "Hỏi bảo hành" (Hỏi về chính sách bảo hành, đổi trả)
   - "Hỏi thanh toán" (Hỏi về chuyển khoản, COD, ví điện tử)
   - "Hỏi mùi hương" (Hỏi về mùi hương, độ thơm, độ lưu hương)
   - "Hỏi công dụng" (Hỏi về tác dụng, cách dùng, đối tượng sử dụng)

4. **product_tag** (Tên sản phẩm chuẩn hóa):
   - Trích xuất tên sản phẩm CHUẨN HÓA từ danh sách sản phẩm đang bán.
   - Phải ánh xạ từ tên viết tắt hoặc biệt danh trong livestream về tên chuẩn (ví dụ: "15prm" hoặc "mười lăm pờ rô mắc" -> "iPhone 15 Pro Max").
   - Trả về null nếu không khớp với sản phẩm nào trong danh sách.

5. **has_phone** (Có số điện thoại):
   - Trả về true nếu bình luận chứa chuỗi chữ số giống SĐT Việt Nam (9-11 chữ số, bắt đầu bằng số 0 hoặc +84, có thể có dấu cách, chấm, gạch ngang). Trả về false nếu không.

=== BỘ TỪ ĐIỂN TIẾNG LÓNG & VIẾT TẮT LIVESTREAM VIỆT NAM ===
- Sản phẩm/mã chốt: sp = sản phẩm, mã / ma / mas / m = mã chốt đơn, đờn = đơn hàng, cb = combo
- Thông số: sz / szi / szie = size (kích cỡ), kg / kí / kgs = cân nặng, m = màu, prm = Pro Max, pr = Pro
- Câu hỏi/Tương tác: bn / bnh / bnhiu / nhiu = bao nhiêu, k / ko / kg / koo = không, ib / ibox = inbox (nhắn tin riêng)
- Địa điểm/Vận chuyển: ship / ship cod = giao hàng thanh toán tận nơi, hn = Hà Nội, hcm = TP. Hồ Chí Minh, t = tỉnh/thành phố

=== VÍ DỤ PHÂN TÍCH ===

Input: 101|đã mua
Output: {"id":101,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: Chỉ comment "đã mua" đơn lẻ là hành vi spam tương tác nhận quà, không có ý định mua hàng thực tế.

Input: 102|ĐÃ MUA
Output: {"id":102,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: Chỉ comment "đã mua" đơn lẻ là hành vi spam tương tác nhận quà, không có ý định mua hàng thực tế.

Input: 113|Mã 2
Output: {"id":113,"sentiment":"neutral","intent_tag":"Chốt đơn","question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: "Mã 2" là cú pháp chốt đơn trực tiếp đặc trưng trên livestream Việt Nam.

Input: 114|Mã 2 ạ
Output: {"id":114,"sentiment":"neutral","intent_tag":"Chốt đơn","question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: "Mã 2 ạ" tương tự "Mã 2", chữ "ạ" là kính ngữ lịch sự, không làm thay đổi ý định chốt đơn thành câu hỏi thông tin.

=== OUTPUT FORMAT ===
Trả về JSON duy nhất, định dạng: {"results": [{...}, {...}]}
Mỗi đối tượng kết quả phải chứa đầy đủ các khóa: id, sentiment, intent_tag, question_tag, product_tag, has_phone.
Tuyệt đối không kèm theo văn bản giải thích hay ký tự markdown nằm ngoài JSON.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->items(
                    $schema->object(fn ($s) => [
                        'id' => $s->integer()->required(),
                        'sentiment' => $s->string()->enum(['positive', 'neutral', 'negative'])->required(),
                        'intent_tag' => $s->string()->enum(['Chốt đơn', 'Hỏi thông tin', 'Phản hồi SP', 'Yêu cầu hỗ trợ'])->nullable(),
                        'question_tag' => $s->string()->enum([
                            'Hỏi giá', 'Hỏi size', 'Hỏi ship', 'Hỏi chất liệu',
                            'Hỏi màu', 'Hỏi tồn kho', 'Hỏi giảm giá', 'Hỏi bảo hành',
                            'Hỏi thanh toán', 'Hỏi mùi hương', 'Hỏi công dụng',
                        ])->nullable(),
                        'product_tag' => $s->string()->nullable(),
                        'has_phone' => $s->boolean()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
